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
    $result = autogenquiz_generate_tf_questions($extracted, $count);

    $data = json_decode($result, true);
    $raw = isset($data['response']) ? $data['response'] : (is_string($result) ? $result : json_encode($data));

    $raw = trim($raw);
    $raw = preg_replace('/^```(json)?/i', '', $raw);
    $raw = preg_replace('/```$/', '', $raw);
    $raw = str_replace(["\r", "\n"], '', $raw);
    $raw = preg_replace("/'/", '"', $raw);
    $raw = preg_replace('/\/\/.*?(\n|$)/', '', $raw);
    $raw = preg_replace('/#.*?(\n|$)/', '', $raw);
    $raw = preg_replace('/: *true/', ': "True"', $raw);
    $raw = preg_replace('/: *false/', ': "False"', $raw);
    $raw = preg_replace('/([^\\\\])"s([^"]*?)"/', '$1\"s$2\"', $raw);

    if (preg_match('/(\[[\s\S]*?\])/', $raw, $m)) {
        $jsonstr = $m[1];
    } elseif (preg_match('/(\{.*\})/', $raw, $m)) {
        $jsonstr = '[' . $m[1] . ']';
    } else {
        $jsonstr = '[]';
    }

    $open = substr_count($jsonstr, '{');
    $close = substr_count($jsonstr, '}');
    if ($open > $close) $jsonstr .= str_repeat('}', $open - $close);
    if (!str_ends_with($jsonstr, ']')) $jsonstr .= ']';

    $jsonstr = preg_replace('/"id\d*"\s*:/', '"id":', $jsonstr);
    $jsonstr = preg_replace('/"id\d*,\s*"type"/', '"id":', $jsonstr);
    $jsonstr = preg_replace('/,\s*"id"\s*:/', ',{"id":', $jsonstr);
    $jsonstr = preg_replace('/,\s*\}/', '}', $jsonstr);
    $jsonstr = preg_replace('/,\s*\]/', ']', $jsonstr);

    $last_bracket = strrpos($jsonstr, ']');
    if ($last_bracket !== false) {
        $jsonstr = substr($jsonstr, 0, $last_bracket + 1);
    } else {
        $jsonstr .= ']';
    }

    $jsonstr = preg_replace('/\].*$/s', ']', $jsonstr);

    $jsonstr = preg_replace('/["\']\s*[\w\/\.\-]+\.txt["\']/', '', $jsonstr);
    $jsonstr = preg_replace('/[^}\]]+$/', '', $jsonstr);

    $cleanjson = json_decode($jsonstr, true);

    if (!is_array($cleanjson)) {
        echo html_writer::tag('div', 'Failed to parse AI output.', ['class' => 'alert alert-danger']);
        echo '<pre>' . s($jsonstr) . '</pre>';
    } else {
        echo html_writer::start_tag('div', ['class' => 'mt-4']);
        echo html_writer::tag('h5', 'Generated True/False Questions:');
        echo html_writer::start_tag('div', ['class' => 'list-group']);
        
        // --- Ensure exactly $count questions ---
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

        $index = 1;
        foreach ($cleanjson as $q) {
            $questionraw = $q['question'] ?? '';
            $questionraw = trim($questionraw);

            if ($questionraw === '' || $questionraw === '(no question)') {
                continue;
            }

            $typeraw = strtolower($q['type'] ?? 'tf');
            if ($typeraw !== 'tf') {
                continue;
            }

            $answer = $q['answer'] ?? $q['Answer'] ?? $q['ANSWER'] ?? '';
            $answer = trim($answer);

            echo html_writer::start_div('list-group-item');

            echo html_writer::tag('h6', "{$index}. [T/F] " . s($questionraw));

            if ($answer !== '') {
                echo html_writer::tag(
                    'p',
                    '<strong>Answer:</strong> ' . s($answer),
                    ['class' => 'text-success']
                );
            }

            echo html_writer::end_div();
            $index++;
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
        <label class="form-label fw-semibold">Number of True/False Questions</label>
        <input type="number" name="question_count" class="form-control" min="1" max="20" value="5" required>
    </div>
    <button type="submit" class="btn btn-primary">Generate True/False</button>
    <a href="<?php echo new moodle_url('/mod/autogenquiz/view.php', ['id' => $id]); ?>" class="btn btn-secondary ms-2">Back</a>
</form>

<?php
echo $OUTPUT->footer();
