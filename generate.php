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
$PAGE->set_heading('Generate Questions');
echo $OUTPUT->header();
echo $OUTPUT->heading('AI Question Generation');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = required_param('question_type', PARAM_ALPHA);
    $count = required_param('question_count', PARAM_INT);
    $result = autogenquiz_generate_questions($extracted, $type, $count);

    // Parse LLM JSON response
    $data = json_decode($result, true);
    $cleanjson = null;

    if (isset($data['response'])) {
        $raw = $data['response'];
        $raw = preg_replace('/^```json\s*/', '', trim($raw));
        $raw = preg_replace('/```$/', '', $raw);
        $cleanjson = json_decode($raw, true);
    } else {
        $cleanjson = $data;
    }

    if (!is_array($cleanjson)) {
        echo html_writer::tag('div', 'Failed to parse AI output.', ['class' => 'alert alert-danger']);
    } else {
        echo html_writer::start_tag('div', ['class' => 'mt-4']);
        echo html_writer::tag('h5', 'Generated Questions:');
        echo html_writer::start_tag('div', ['class' => 'list-group']);
        foreach ($cleanjson as $q) {
            $qid = $q['id'] ?? '?';
            $type = strtoupper($q['type'] ?? 'unknown');
            $question = s($q['question'] ?? '(no question)');
            $options = $q['options'] ?? [];
            $answer = $q['answer'] ?? [];

            echo html_writer::start_div('list-group-item');
            echo html_writer::tag('h6', "{$qid}. [{$type}] {$question}");
            if ($options) {
                echo html_writer::start_tag('ul', ['class' => 'mb-1']);
                foreach ($options as $opt) {
                    echo html_writer::tag('li', s($opt));
                }
                echo html_writer::end_tag('ul');
            }
            if ($answer) {
                $ans = is_array($answer) ? implode(', ', $answer) : $answer;
                echo html_writer::tag('p', '<strong>Answer:</strong> ' . s($ans), ['class' => 'text-success']);
            }
            echo html_writer::end_div();
        }
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('div');
    }
    echo $OUTPUT->footer();
    exit;
}
?>

<form method="post">
    <div class="mb-3">
        <label class="form-label fw-semibold">Question Type</label>
        <select name="question_type" class="form-select" required>
            <option value="tf">True / False</option>
            <option value="mcq">Multiple Choice</option>
            <option value="short">Short Answer</option>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold">Number of Questions</label>
        <input type="number" name="question_count" class="form-control" min="1" max="20" value="5" required>
    </div>
    <button type="submit" class="btn btn-primary">Generate</button>
    <a href="<?php echo new moodle_url('/mod/autogenquiz/view.php', ['id' => $id]); ?>" class="btn btn-secondary ms-2">Back</a>
</form>

<?php
echo $OUTPUT->footer();
