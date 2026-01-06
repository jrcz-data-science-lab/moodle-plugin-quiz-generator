<?php

require '../../config.php';
require_once __DIR__ . '/locallib.php';
require_once __DIR__ . '/ai_request_fib.php';

$id = required_param('id', PARAM_INT);
$fileid = optional_param('fileid', 0, PARAM_INT);
$genid = optional_param('genid', 0, PARAM_INT);
$saved = optional_param('saved', 0, PARAM_INT);

[$cm, $course, $context] = autogenquiz_require_module_context($id, 'mod/autogenquiz:view');

global $DB;

if (!$fileid && $genid) {
    $fileid = autogenquiz_resolve_fileid_from_genid($genid);
}

if (!$fileid) {
    autogenquiz_redirect_missing_file($id);
}

$PAGE->set_url('/mod/autogenquiz/generate_fib.php', ['id' => $id, 'fileid' => $fileid]);
$PAGE->set_title('AutoGenQuiz - Generate Fill in the Blank');
$PAGE->set_heading('Generate Fill in the Blank Questions');

echo $OUTPUT->header();
echo $OUTPUT->heading('AutoGenQuiz Generator');

/* ---------- Instruction ---------- */
echo '
<div class="upload-instructions border rounded mb-3" 
    style="background:#f8f9fa; border-left:5px solid #0d6efd;">

    <div class="instruction-header bg-light d-flex justify-content-between align-items-center px-3 py-2"
        style="cursor:pointer;" onclick="toggleGenInstruction()">

        <p id="gen-short-text" class="mb-0" style="font-size:15px; color:#333; display:block;">
            Click <strong style="color:#0d6efd;">Generate</strong> to create questions.
            You may edit the generated questions and click 
            <strong style="color:#0d6efd;">Save Changes</strong>.
            Or click <strong style="color:#0d6efd;">Import to Question Bank</strong> directly.<br><br>
            <span class="text-muted">For more details, click the triangle.</span>
        </p>

        <span id="gen-toggle-icon" style="font-size:18px;">&#9654;</span>
    </div>

    <div id="gen-instruction-content" style="display:none; padding:15px 20px;">
        <p>The system generates <strong style="color:#0d6efd;">10 questions by default</strong>.</p>
        <p>Each question contains <strong style="color:#0d6efd;">one blank</strong> marked as <code>_____</code>.</p>
        <p>All generated questions and answers can be edited.</p>
        <p>After editing, click <strong style="color:#0d6efd;">Save Changes</strong>.</p>
        <p>You may always click <strong style="color:#0d6efd;">Import to Question Bank</strong>.</p>
    </div>
</div>

<script>
function toggleGenInstruction() {
    const content = document.getElementById("gen-instruction-content");
    const shortText = document.getElementById("gen-short-text");
    const icon = document.getElementById("gen-toggle-icon");

    const isHidden = content.style.display === "none";

    if (isHidden) {
        content.style.display = "block";
        shortText.style.display = "none";
        icon.innerHTML = "&#9660;";
    } else {
        content.style.display = "none";
        shortText.style.display = "block";
        icon.innerHTML = "&#9654;";
    }
}
</script>
';

echo '<div class="alert alert-info mt-2">
    Generating questions may take up to one minute depending on the server connection.
</div>';

if ($saved) {
    echo $OUTPUT->notification('Changes saved successfully.', core\output\notification::NOTIFY_SUCCESS);
}

/* ---------- Editable Form ---------- */
function render_editable_form_fib(int $id, int $fileid, int $genid, array $items): void
{
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/mod/autogenquiz/save_generated_fib.php'),
    ]);

    echo html_writer::input_hidden_params(
        new moodle_url('', [
            'genid' => $genid,
            'id' => $id,
            'fileid' => $fileid,
            'sesskey' => sesskey(),
        ])
    );

    echo '<div id="question-list">';
    $i = 1;

    foreach ($items as $q) {
        $qtext = s(trim($q['question'] ?? ''));
        $answers = $q['answers'] ?? [];

        if ($qtext === '') {
            continue;
        }

        echo '<div class="card mb-3 p-3" id="q_' . $i . '">';
        echo '<div class="d-flex justify-content-between align-items-center">';
        echo '<h6 class="fw-bold mb-0">Question ' . $i . '</h6>';
        echo '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeQuestion(' . $i . ')">Delete</button>';
        echo '</div>';

        echo '<textarea class="form-control mt-2" name="questions[' . $i . '][question]" rows="2">' . $qtext . '</textarea>';

        foreach ($answers as $ans) {
            echo '<input type="text" class="form-control mt-2" name="questions[' . $i . '][answers][]" value="' . s($ans) . '">';
        }

        echo '</div>';
        $i++;
    }

    echo '</div>';

    echo '<div class="mt-3">';
    echo '<button type="submit" class="btn btn-primary me-2">Save Changes</button>';

    $importurl = new moodle_url('/mod/autogenquiz/import_to_bank_fib.php', [
        'genid' => $genid,
        'id' => $id,
    ]);
    echo '<a href="' . $importurl . '" class="btn btn-success">Import to Question Bank</a>';

    echo '</div>';

    echo html_writer::end_tag('form');

    echo <<<JS
<script>
function removeQuestion(id) {
    const el = document.getElementById('q_' + id);
    if (el) el.remove();
}
</script>
JS;
}

/* ---------- Generate Form ---------- */
$formurl = new moodle_url('/mod/autogenquiz/generate_fib.php', ['id' => $id, 'fileid' => $fileid]);

echo '<div class="card mb-3"><div class="card-body">';
echo '<form method="post" action="' . $formurl . '" id="generateForm">';

echo '<div class="mb-3"><label class="form-label fw-semibold">Number of Fill in the Blank Questions</label>';
echo '<input type="number" name="question_count" class="form-control" min="1" max="20" value="10" required></div>';

echo '<input type="hidden" name="fileid" value="' . $fileid . '">';

echo '<button type="submit" id="genbtn" class="btn btn-primary">Generate</button>';

echo '<a href="' . new moodle_url('/mod/autogenquiz/select_type.php', [
    'id' => $id,
    'fileid' => $fileid
]) . '" class="btn btn-secondary ms-2">Back</a>';

echo '</form></div></div>';

/* ---------- Processing Overlay ---------- */
echo '
<script>
(function() {
    const form = document.getElementById("generateForm");
    if (form) {
        form.addEventListener("submit", function() {
            if (form.checkValidity()) {
                const overlay = document.createElement("div");
                overlay.style.cssText = "position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(255,255,255,0.9); z-index:9999; display:flex; align-items:center; justify-content:center;";
                overlay.innerHTML =
                    \'<div class="card shadow-lg" style="max-width:400px;">\' +
                    \'<div class="card-body text-center p-4">\' +
                    \'<div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;"></div>\' +
                    \'<h5 class="card-title mb-2">Processing</h5>\' +
                    \'<p class="card-text">Generating questions from your document.<br>Please do not close this page.</p>\' +
                    \'</div></div>\';
                document.body.appendChild(overlay);
            }
        });
    }
})();
</script>
';

/* ---------- POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $count = required_param('question_count', PARAM_INT);
    $fid = required_param('fileid', PARAM_INT);

    $file = $DB->get_record('autogenquiz_files', ['id' => $fid], '*', MUST_EXIST);

    $taskid = autogenquiz_create_task($fid, (int)$course->id, 'sent_request');
    $res = autogenquiz_generate_fib_questions($file->confirmed_text, $count);

    $raw = null;
    $err = null;
    $arr = autogenquiz_parse_ai_to_array($res, $raw, $err);

    if (!is_array($arr)) {
        echo $OUTPUT->notification('Failed to parse AI output.', core\output\notification::NOTIFY_ERROR);
        echo '<pre>' . s((string)$raw) . '</pre>';
        echo $OUTPUT->footer();
        exit;
    }

    foreach ($arr as &$q) {
        $q['type'] = 'fib';
        $q['answers'] = array_values($q['answers'] ?? []);
    }

    $genid = autogenquiz_save_generation($taskid, $file->confirmed_text, $res, $arr);

    echo html_writer::tag('h5', 'Generated Questions');
    render_editable_form_fib($id, $fid, $genid, $arr);

    echo $OUTPUT->footer();
    exit;
}

if ($genid) {
    $rec = $DB->get_record('autogenquiz_generated', ['id' => $genid], '*', IGNORE_MISSING);
    if ($rec) {
        $arr = json_decode($rec->parsed_response, true) ?: [];
        echo html_writer::tag('h5', 'Generated Questions');
        render_editable_form_fib($id, $fileid, $rec->id, $arr);
    }
}

echo $OUTPUT->footer();
