<?php

require '../../config.php';

// Read parameters
$id = required_param('id', PARAM_INT);
$fileid = optional_param('fileid', 0, PARAM_INT);
$genid = optional_param('genid', 0, PARAM_INT);
$saved = optional_param('saved', 0, PARAM_INT);

// Load course and course module
$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/autogenquiz:view', $context);

global $DB;

// Page Setup
$PAGE->set_url('/mod/autogenquiz/generate_mcsa.php', ['id' => $id, 'fileid' => $fileid]);
$PAGE->set_title('AutoGenQuiz - Generate Multiple Choice');
$PAGE->set_heading('Generate Multiple Choice (Single Answer)');

echo $OUTPUT->header();
echo $OUTPUT->heading('AutoGenQuiz Generator');

// URLs
$formurl = new moodle_url('/mod/autogenquiz/generate_mcsa.php', ['id' => $id, 'fileid' => $fileid]);
$backurl = new moodle_url('/mod/autogenquiz/select_type.php', ['id' => $id, 'fileid' => $fileid]);


/* ============================================================
   FUNCTION: Render editable UI for MCSA questions
   ============================================================ */
function render_editable_form_mcsa(int $id, int $fileid, int $genid, array $items): void
{

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/mod/autogenquiz/save_generated_mcsa.php'),
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
        $text = s(trim($q['question'] ?? ''));
        $options = $q['options'] ?? [];
        $correct = $q['correct'] ?? 0;

        echo '<div class="card mb-3 p-3" id="q_' . $i . '">';
        echo '<div class="d-flex justify-content-between align-items-center">';
        echo '<h6 class="fw-bold mb-0">Question ' . $i . '</h6>';
        echo '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeQuestion(' . $i . ')">Delete</button>';
        echo '</div>';

        echo '<textarea class="form-control mt-2" name="questions[' . $i . '][question]" rows="2">' . $text . '</textarea>';

        echo '<div class="mt-3">';
        foreach ($options as $idx => $op) {
            $label = chr(65 + $idx); // A B C D
            $checked = ($correct == $idx) ? 'checked' : '';

            echo '<div class="input-group mb-2">';
            echo '<div class="input-group-text">';
            echo '<input type="radio" name="questions[' . $i . '][correct]" value="' . $idx . '" ' . $checked . '>';
            echo '</div>';
            echo '<input type="text" class="form-control" name="questions[' . $i . '][options][' . $idx . ']" value="' . s($op) . '">';
            echo '<span class="input-group-text">' . $label . '</span>';
            echo '</div>';
        }
        echo '</div>';

        echo '</div>';
        $i++;
    }

    echo '</div>';

    echo '<div class="mt-3">';
    echo '<button type="submit" class="btn btn-primary me-2">Save Changes</button>';

    $importurl = new moodle_url('/mod/autogenquiz/import_to_bank_mcsa.php', ['genid' => $genid, 'id' => $id]);
    echo '<a href="' . $importurl . '" class="btn btn-success">Import to Question Bank</a>';

    echo '</div>';

    echo html_writer::end_tag('form');

    echo "
    <script>
    function removeQuestion(id) {
        const el = document.getElementById('q_' + id);
        if (el) el.remove();
    }
    </script>
    ";
}


/* ============================================================
   UI: Instructions + Form for entering quantity
   ============================================================ */
?>

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
        <p>All generated questions can be edited — please verify each answer.</p>
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

<div class="alert alert-info mt-2">
    Generating questions may take up to one minute depending on the server connection.
</div>

<?php
if ($saved) {
    echo $OUTPUT->notification('Changes saved successfully.', core\output\notification::NOTIFY_SUCCESS);
}
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="post" action="<?php echo $formurl; ?>" id="generateForm">
            <div class="mb-3">
                <label class="form-label fw-semibold">Number of Multiple Choice Questions</label>
                <input type="number" name="question_count" class="form-control" min="1" max="20" value="10" required>
            </div>

            <input type="hidden" name="fileid" value="<?php echo (int)$fileid; ?>">

            <button type="submit" id="genbtn" class="btn btn-primary">Generate MCSA</button>
            <a href="<?php echo $backurl; ?>" class="btn btn-secondary ms-2">Back</a>
        </form>
    </div>
</div>

<script>
    (function() {
        const form = document.getElementById("generateForm");

        if (form) {
            form.addEventListener("submit", function(e) {
                if (form.checkValidity()) {
                    const overlay = document.createElement("div");
                    overlay.style.cssText =
                        "position:fixed; top:0; left:0; width:100%; height:100%;" +
                        "background-color:rgba(255,255,255,0.9); z-index:9999;" +
                        "display:flex; align-items:center; justify-content:center;";
                    overlay.innerHTML =
                        '<div class="card shadow-lg" style="max-width:400px;">' +
                        '<div class="card-body text-center p-4">' +
                        '<div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;"></div>' +
                        '<h5 class="card-title mb-2">Processing</h5>' +
                        '<p class="card-text">Generating questions...<br>Please do not close this page.</p>' +
                        '</div></div>';
                    overlay.id = "loadingOverlay";
                    document.body.appendChild(overlay);
                }
                return true;
            });
        }
    })();
</script>

<?php
/* ============================================================
   POST: Send request + parse + render editable UI
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $count = required_param('question_count', PARAM_INT);
    $fid = required_param('fileid', PARAM_INT);

    $file = $DB->get_record('autogenquiz_files', ['id' => $fid]);
    if (!$file) {
        echo $OUTPUT->notification('Source file is missing.', core\output\notification::NOTIFY_ERROR);
        echo $OUTPUT->footer();
        exit;
    }

    // Create task
    $task = (object)[
        'fileid' => $fid,
        'courseid' => $course->id,
        'status' => 'sent_request',
        'created_at' => time(),
        'updated_at' => time(),
    ];
    $taskid = $DB->insert_record('autogenquiz_tasks', $task, true);

    require_once __DIR__ . '/ai_request.php';
    $res = autogenquiz_generate_mcsa_questions($file->confirmed_text, $count);
    $data = json_decode($res, true);

    if (!empty($data['connection_error'])) {
        echo '<div class="alert alert-danger mt-2">
                Connection error. Please contact administrator.
              </div>';
        echo $OUTPUT->footer();
        exit;
    }

    $raw = $data['response'] ?? $data['message']['content'] ?? $res;
    $raw = trim(preg_replace(['/^```(json)?/i', '/```$/'], '', $raw));

    $arr = json_decode($raw, true);
    if (!is_array($arr) && preg_match('/\[[\s\S]*\]/', $raw, $m)) {
        $arr = json_decode($m[0], true);
    }

    if (!is_array($arr)) {
        echo $OUTPUT->notification('Failed to parse AI output.', core\output\notification::NOTIFY_ERROR);
        echo '<pre>' . s($raw) . '</pre>';
        echo $OUTPUT->footer();
        exit;
    }

    foreach ($arr as &$q) {
        $q['type'] = 'mcsa';
        if (empty($q['options']) || !is_array($q['options'])) {
            $q['options'] = ["Option A", "Option B", "Option C", "Option D"];
        }
        if (!isset($q['correct'])) {
            $q['correct'] = 0;
        }
    }

    $gen = (object)[
        'taskid' => $taskid,
        'rawtext' => $file->confirmed_text,
        'llm_response' => $res,
        'parsed_response' => json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        'is_approved' => 0,
        'imported_to_bank' => 0,
    ];
    $newid = $DB->insert_record('autogenquiz_generated', $gen, true);

    echo html_writer::tag('h5', 'Generated Questions (MCSA)');
    render_editable_form_mcsa($id, $fid, $newid, $arr);

    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->footer();
