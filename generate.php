<?php

require '../../config.php';
require_once __DIR__ . '/ai_request.php';
require_once __DIR__ . '/locallib.php';

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

$PAGE->set_url('/mod/autogenquiz/generate.php', ['id' => $id, 'fileid' => $fileid]);
$PAGE->set_title('AutoGenQuiz - Generate');
$PAGE->set_heading('Generate True/False Questions');

echo $OUTPUT->header();
echo $OUTPUT->heading('AutoGenQuiz Generator');

echo '
<div class="upload-instructions border rounded mb-3" 
    style="background:#f8f9fa; border-left:5px solid #0d6efd;">

    <div class="instruction-header bg-light d-flex justify-content-between align-items-center px-3 py-2"
        style="cursor:pointer;" onclick="toggleGenInstruction()">

        <p id="gen-short-text" class="mb-0" style="font-size:15px; color:#333; display:block;">
            Click <strong style="color:#0d6efd;">Generate</strong> to create questions.
            You may edit the generated questions and click 
            <strong style="color:#0d6efd;">Save Changes</strong> to confirm.
            Or click <strong style="color:#0d6efd;">Import to Question Bank</strong> directly to save them.<br><br>
            <span class="text-muted">For more details, click the triangle.</span>
        </p>

        <span id="gen-toggle-icon" style="font-size:18px;">&#9654;</span>
    </div>

    <div id="gen-instruction-content" style="display:none; padding:15px 20px;">
        <p>The system generates <strong style="color:#0d6efd;">10 questions by default</strong>, 
        but you may enter any number you prefer.</p>

        <p>All generated questions can be edited — please verify each answer instead of fully relying on the AI.</p>

        <p>After editing, click <strong style="color:#0d6efd;">Save Changes</strong> to store your updates.</p>

        <p>Whether you edit the questions or not, you can always click
        <strong style="color:#0d6efd;">Import to Question Bank</strong> to save them.</p>
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
    If it is unusually slow, please contact the LLM administrator or the technical department.
</div>';

if ($saved) {
    echo $OUTPUT->notification('Changes saved successfully.', core\output\notification::NOTIFY_SUCCESS);
}

function render_editable_form(int $id, int $fileid, int $genid, array $cleanjson, int $cmid): void
{
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/mod/autogenquiz/save_generated.php'),
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

    foreach ($cleanjson as $q) {
        $qtext = s(trim($q['question'] ?? ''));
        if ($qtext === '') {
            continue;
        }

        $ans = ucfirst(strtolower(trim($q['answer'] ?? 'True')));
        if (!in_array($ans, ['True', 'False'], true)) {
            $ans = 'True';
        }

        echo '<div class="card mb-3 p-3" id="q_' . $i . '">';
        echo '<div class="d-flex justify-content-between align-items-center">';
        echo '<h6 class="fw-bold mb-0">Question ' . $i . '</h6>';
        echo '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeQuestion(' . $i . ')">Delete</button>';
        echo '</div>';

        echo '<textarea class="form-control mt-2" name="questions[' . $i . '][question]" rows="2">' . $qtext . '</textarea>';

        echo '<div class="mt-2"><label class="me-2 fw-semibold">Answer:</label>';
        echo '<select name="questions[' . $i . '][answer]" class="form-select d-inline-block" style="width:auto;">';

        foreach (['True', 'False'] as $opt) {
            $sel = $ans === $opt ? ' selected' : '';
            echo '<option value="' . $opt . '"' . $sel . '>' . $opt . '</option>';
        }

        echo '</select></div></div>';
        ++$i;
    }

    echo '</div>';

    echo '<div class="mt-3">';
    echo '<button type="submit" class="btn btn-primary me-2">Save Changes</button>';

    $importurl = new moodle_url('/mod/autogenquiz/import_to_bank.php', [
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

$formurl = new moodle_url('/mod/autogenquiz/generate.php', ['id' => $id, 'fileid' => $fileid]);

echo '<div class="card mb-3"><div class="card-body">';
echo '<form method="post" action="' . $formurl . '" id="generateForm">';

echo '<div class="mb-3"><label class="form-label fw-semibold">Number of True/False Questions</label>';
echo '<input type="number" name="question_count" class="form-control" min="1" max="20" value="10" required></div>';

echo '<input type="hidden" name="fileid" value="' . $fileid . '">';

echo '<button type="submit" id="genbtn" class="btn btn-primary">
        Generate True/False
    </button>';

echo '<a href="' . new moodle_url('/mod/autogenquiz/select_type.php', [
    'id' => $id,
    'fileid' => $fileid
]) . '" class="btn btn-secondary ms-2">Back</a>';

echo '</form></div></div>';

echo '
<script>
(function() {
    const form = document.getElementById("generateForm");
    
    if (form) {
        form.addEventListener("submit", function(e) {
            if (form.checkValidity()) {
                const overlay = document.createElement("div");
                overlay.style.cssText = "position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(255,255,255,0.9); z-index:9999; display:flex; align-items:center; justify-content:center;";
                overlay.innerHTML = \'<div class="card shadow-lg" style="max-width:400px;">' .
    '<div class="card-body text-center p-4">' .
    '<div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;"></div>' .
    '<h5 class="card-title mb-2">Processing</h5>' .
    '<p class="card-text">Generating questions from your document.<br>This process may take up to one minute. Please do not close this page.</p>' .
    '</div></div>\';
                overlay.id = "loadingOverlay";
                document.body.appendChild(overlay);
            }
            return true;
        });
    }
})();
</script>
';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $count = required_param('question_count', PARAM_INT);
    $fid = required_param('fileid', PARAM_INT);

    $file = $DB->get_record('autogenquiz_files', ['id' => $fid]);

    if (!$file) {
        echo $OUTPUT->notification('Source file is missing.', core\output\notification::NOTIFY_ERROR);
        echo $OUTPUT->footer();
        exit;
    }

    $taskid = autogenquiz_create_task($fid, (int)$course->id, 'sent_request');

    $res = autogenquiz_generate_tf_questions($file->confirmed_text, $count);

    $raw = null;
    $err = null;
    $arr = autogenquiz_parse_ai_to_array($res, $raw, $err);

    if ($err === 'connection_error') {
        echo '<div class="alert alert-danger mt-2">
        The system could not connect to the question-generation server. 
        This plugin is temporarily unavailable. Please contact the LLM administrator or technical department.
    </div>';
        echo $OUTPUT->footer();
        exit;
    }

    if (!is_array($arr)) {
        echo $OUTPUT->notification('Failed to parse AI output.', core\output\notification::NOTIFY_ERROR);
        echo '<pre>' . s((string)$raw) . '</pre>';
        echo $OUTPUT->footer();
        exit;
    }

    $arr = autogenquiz_normalize_tf_items($arr);
    $newid = autogenquiz_save_generation($taskid, (string)$file->confirmed_text, $res, $arr);

    echo html_writer::tag('h5', 'Generated Questions');
    render_editable_form($id, $fid, $newid, $arr, $cm->id);

    echo $OUTPUT->footer();
    exit;
}

if ($genid) {
    $rec = $DB->get_record('autogenquiz_generated', ['id' => $genid], '*', IGNORE_MISSING);
    if ($rec) {
        $arr = json_decode($rec->parsed_response, true) ?: [];
        echo html_writer::tag('h5', 'Generated Questions');
        render_editable_form($id, $fileid, $rec->id, $arr, $cm->id);
    } else {
        echo $OUTPUT->notification('Generation record not found.', core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->footer();
