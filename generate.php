<?php
require('../../config.php');
require_once(__DIR__ . '/ai_request.php');

$id = required_param('id', PARAM_INT);
$fileid = required_param('fileid', PARAM_INT);

$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/autogenquiz:view', $context);

$record = $DB->get_record('autogenquiz_files', ['id' => $fileid], '*', MUST_EXIST);
$extracted = $record->confirmed_text;

$PAGE->set_url('/mod/autogenquiz/generate.php', ['id' => $id, 'fileid' => $fileid]);
$PAGE->set_title('AutoGenQuiz - Generate');
$PAGE->set_heading('Generate True/False Questions');
echo $OUTPUT->header();
echo $OUTPUT->heading('Generate True/False Questions');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $count = required_param('question_count', PARAM_INT);

    // --- Create task record (sent_request) ---
    $task = new stdClass();
    $task->fileid = $fileid;
    $task->courseid = $course->id;
    $task->status = 'sent_request';
    $task->created_at = time();
    $task->updated_at = time();
    $taskid = $DB->insert_record('autogenquiz_tasks', $task, true);

    // --- Call AI ---
    $result = autogenquiz_generate_tf_questions($extracted, $count);

    // --- Try to parse raw response ---
    $data = json_decode($result, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        if (isset($data['response'])) {
            $raw = $data['response'];
        } elseif (isset($data['message']['content'])) {
            $raw = $data['message']['content'];
        } else {
            $raw = $result;
        }
    } else {
        $raw = $result;
    }

    // --- Clean markdown wrappers ---
    $raw = trim($raw);
    $raw = preg_replace('/^```(json)?/i', '', $raw);
    $raw = preg_replace('/```$/', '', $raw);
    $raw = trim($raw);

    // --- Try direct decode ---
    $cleanjson = json_decode($raw, true);

    // --- Try to extract array if failed ---
    if (!is_array($cleanjson) && preg_match('/\[[\s\S]*\]/', $raw, $m)) {
        $cleanjson = json_decode($m[0], true);
    }

    // --- Try to wrap single object ---
    if (!is_array($cleanjson) && preg_match('/\{[\s\S]*\}/', $raw, $m)) {
        $cleanjson = json_decode('[' . $m[0] . ']', true);
    }

    // --- Still failed ---
    if (!is_array($cleanjson)) {
        echo html_writer::tag('div', 'Failed to parse AI output.', ['class' => 'alert alert-danger']);
        echo '<pre>' . s($raw) . '</pre>';
        $DB->set_field('autogenquiz_tasks', 'status', 'failed_parse', ['id' => $taskid]);
        echo $OUTPUT->footer();
        exit;
    }

    // --- Success: mark task success ---
    $DB->set_field('autogenquiz_tasks', 'status', 'success', ['id' => $taskid]);

    // --- Display questions ---
    echo html_writer::start_tag('div', ['class' => 'mt-4']);
    echo html_writer::tag('h5', 'Generated True/False Questions:');
    echo html_writer::start_tag('div', ['class' => 'list-group']);

    // --- Ensure exactly $count items ---
    $actual = count($cleanjson);
    if ($actual < $count) {
        for ($i = $actual + 1; $i <= $count; $i++) {
            $cleanjson[] = [
                'id' => $i,
                'type' => 'tf',
                'question' => "Placeholder question {$i}.",
                'answer' => 'True'
            ];
        }
    } elseif ($actual > $count) {
        $cleanjson = array_slice($cleanjson, 0, $count);
    }

    // --- Render ---
    $index = 1;
    foreach ($cleanjson as $q) {
        $questionraw = trim($q['question'] ?? '');
        if ($questionraw === '' || $questionraw === '(no question)') continue;

        $typeraw = strtolower($q['type'] ?? 'tf');
        if ($typeraw !== 'tf') continue;

        $answer = $q['answer'] ?? $q['Answer'] ?? $q['ANSWER'] ?? '';
        if (is_bool($answer)) $answer = $answer ? 'True' : 'False';
        $answer = trim((string)$answer);

        echo html_writer::start_div('list-group-item');
        echo html_writer::tag('h6', "{$index}. [T/F] " . s($questionraw));
        if ($answer !== '') {
            echo html_writer::tag('p', '<strong>Answer:</strong> ' . s($answer), ['class' => 'text-success']);
        }
        echo html_writer::end_div();
        $index++;
    }

    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');
    echo $OUTPUT->footer();
    exit;
}
?>

<form method="post">
    <div class="mb-3">
        <label class="form-label fw-semibold">Number of True/False Questions</label>
        <input type="number" name="question_count" class="form-control" min="1" max="20" value="5" required>
    </div>
    <button type="submit" class="btn btn-primary">Generate True/False</button>
    <a href="<?php echo new moodle_url('/mod/autogenquiz/view.php', ['id' => $id]); ?>" class="btn btn-secondary ms-2">Back</a>
</form>

<?php
echo $OUTPUT->footer();
?>
