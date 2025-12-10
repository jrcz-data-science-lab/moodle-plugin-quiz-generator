<?php

require '../../config.php';

// Read parameters
$id = required_param('id', PARAM_INT);
$fileid = optional_param('fileid', 0, PARAM_INT);
$genid = optional_param('genid', 0, PARAM_INT); // reserved for future use (saved generation id)
$saved = optional_param('saved', 0, PARAM_INT); // reserved for future use (saved flag)

// Load course and course module
$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/autogenquiz:view', $context);

global $DB;

// Page setup (same as generate.php, only heading text changed)
$PAGE->set_url('/mod/autogenquiz/generate_mcsa.php', ['id' => $id, 'fileid' => $fileid]);
$PAGE->set_title('AutoGenQuiz - Generate Multiple Choice');
$PAGE->set_heading('Generate Multiple Choice (Single Answer)');

echo $OUTPUT->header();
echo $OUTPUT->heading('AutoGenQuiz Generator');

// URLs for form action and back button
$formurl = new moodle_url('/mod/autogenquiz/generate_mcsa.php', ['id' => $id, 'fileid' => $fileid]);
$backurl = new moodle_url('/mod/autogenquiz/select_type.php', ['id' => $id, 'fileid' => $fileid]);
?>

<!-- Collapsible instruction block (copied from generate.php) -->
<div class="upload-instructions border rounded mb-3"
    style="background:#f8f9fa; border-left:5px solid #0d6efd;">

    <!-- Header -->
    <div class="instruction-header bg-light d-flex justify-content-between align-items-center px-3 py-2"
        style="cursor:pointer;" onclick="toggleGenInstruction()">

        <!-- Short instruction (visible ONLY when collapsed) -->
        <p id="gen-short-text" class="mb-0" style="font-size:15px; color:#333; display:block;">
            Click <strong style="color:#0d6efd;">Generate</strong> to create questions.
            You may edit the generated questions and click
            <strong style="color:#0d6efd;">Save Changes</strong> to confirm.
            Or click <strong style="color:#0d6efd;">Import to Question Bank</strong> directly to save them.<br><br>
            <span class="text-muted">For more details, click the triangle.</span>
        </p>

        <span id="gen-toggle-icon" style="font-size:18px;">&#9654;</span>
    </div>

    <!-- Expanded content (visible ONLY when expanded) -->
    <div id="gen-instruction-content" style="display:none; padding:15px 20px;">
        <p>The system generates <strong style="color:#0d6efd;">10 questions by default</strong>,
            but you may enter any number you prefer.</p>

        <p>All generated questions can be edited — please verify each answer instead of fully relying on the AI.</p>

        <p>After editing, click <strong style="color:#0d6efd;">Save Changes</strong> to store your updates.</p>

        <p>Whether you edit the questions or not, you can always click
            <strong style="color:#0d6efd;">Import to Question Bank</strong> to save them.
        </p>
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
            icon.innerHTML = "&#9660;"; // ▼
        } else {
            content.style.display = "none";
            shortText.style.display = "block";
            icon.innerHTML = "&#9654;"; // ▶
        }
    }
</script>

<!-- One-minute warning (copied from generate.php) -->
<div class="alert alert-info mt-2">
    Generating questions may take up to one minute depending on the server connection.
    If it is unusually slow, please contact the LLM administrator or the technical department.
</div>

<?php
// Success message (reserved for future; same position as generate.php)
if ($saved) {
    echo $OUTPUT->notification('Changes saved successfully.', core\output\notification::NOTIFY_SUCCESS);
}
?>

<!-- Form: same layout as generate.php, only text changed to MC -->
<div class="card mb-3">
    <div class="card-body">
        <form method="post" action="<?php echo $formurl; ?>" id="generateForm">
            <div class="mb-3">
                <label class="form-label fw-semibold">Number of Multiple Choice Questions</label>
                <input type="number" name="question_count" class="form-control" min="1" max="20" value="10" required>
            </div>

            <input type="hidden" name="fileid" value="<?php echo (int)$fileid; ?>">

            <button type="submit" id="genbtn" class="btn btn-primary">
                Generate
            </button>

            <a href="<?php echo $backurl; ?>" class="btn btn-secondary ms-2">Back</a>
        </form>
    </div>
</div>

<script>
    // Show loading overlay on form submission (copied from generate.php)
    (function() {
        const form = document.getElementById("generateForm");

        if (form) {
            form.addEventListener("submit", function(e) {
                // Only show overlay if form is valid
                if (form.checkValidity()) {
                    // Create overlay
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
                        '<p class="card-text">Generating questions from your document.<br>This process may take up to one minute. Please do not close this page.</p>' +
                        '</div>' +
                        '</div>';
                    overlay.id = "loadingOverlay";
                    document.body.appendChild(overlay);
                }
                // Allow form to submit normally
                return true;
            });
        }
    })();
</script>

<?php
// Handle POST = generate new MCSA questions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $DB;

    $count = required_param('question_count', PARAM_INT);
    $fid = required_param('fileid', PARAM_INT);

    $file = $DB->get_record('autogenquiz_files', ['id' => $fid]);
    if (!$file) {
        echo $OUTPUT->notification('Source file is missing.', core\output\notification::NOTIFY_ERROR);
        echo $OUTPUT->footer();
        exit;
    }

    // Create task record
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
            The system could not connect to the question-generation server.
            Please contact the LLM administrator or technical department.
        </div>';
        echo $OUTPUT->footer();
        exit;
    }

    // Extract raw JSON text
    $raw = $data['response'] ?? $data['message']['content'] ?? $res;
    $raw = trim(preg_replace(['/^```(json)?/i', '/```$/'], '', $raw));

    // Decode JSON
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

    // Ensure format is correct
    foreach ($arr as &$q) {
        $q['type'] = 'mcsa';
        if (!isset($q['options']) || !is_array($q['options'])) {
            $q['options'] = ["Option A", "Option B", "Option C", "Option D"];
        }
        if (!isset($q['correct'])) {
            $q['correct'] = 0;
        }
    }

    // Save generation record
    $gen = (object)[
        'taskid' => $taskid,
        'rawtext' => $file->confirmed_text,
        'llm_response' => $res,
        'parsed_response' => json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        'is_approved' => 0,
        'imported_to_bank' => 0,
    ];
    $newid = $DB->insert_record('autogenquiz_generated', $gen, true);

    // Show results (UI will be implemented in Step 3)
    echo html_writer::tag('h5', 'Generated Questions (MCSA)');
    echo '<pre>' . s(json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';

    echo $OUTPUT->footer();
    exit;
}
