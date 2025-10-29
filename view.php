<?php
require('../../config.php');

use core\output\notification;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);

$PAGE->set_url('/mod/autogenquiz/view.php', ['id' => $id]);

echo $OUTPUT->header();
echo $OUTPUT->heading('');
?>

<style>
/* --- Instruction and Modal Styling --- */
.upload-instructions {
    background: #f8f9fa;
    border-left: 5px solid #0d6efd;
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 20px;
    font-size: 15px;
    color: #333;
}

.upload-instructions strong {
    color: #0d6efd;
}

.modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1050;
    justify-content: center;
    align-items: center;
}

.modal-box {
    background: white;
    border-radius: 12px;
    padding: 25px 30px;
    max-width: 400px;
    text-align: center;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.modal-box h5 {
    color: #dc3545;
    font-weight: bold;
    margin-bottom: 15px;
}

.modal-box button {
    margin-top: 10px;
}
</style>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            Upload your file to generate a quiz
        </div>
        <div class="card-body">

            <!-- Collapsible Instruction Section -->
            <div class="upload-instructions border rounded mb-3">
                <div class="instruction-header bg-light d-flex justify-content-between align-items-center px-3 py-2" 
                    style="cursor: pointer;" onclick="toggleInstruction()">
                    <h5 class="fw-bold mb-0 text-danger">Click to learn about AutoGenQuiz</h5>
                    <span id="toggle-icon" style="font-size: 18px;">&#9654;</span> <!-- ▶ right arrow -->
                </div>
                <div id="instruction-content" class="instruction-content" style="display: none; padding: 15px 20px;">
                    <p>
                        The <strong>AutoGenQuiz</strong> plugin allows you to upload teaching materials and automatically extract their text content to help generate quiz questions later.
                    </p>
                    <p class="mb-1"><strong>Before uploading:</strong></p>
                    <ul>
                        <li>Accepted file formats: <strong>PDF (.pdf)</strong> or <strong>PowerPoint (.pptx)</strong>.</li>
                        <li>Maximum file size: <strong>10 MB</strong>.</li>
                        <li>Upload <strong>only one file</strong> at a time.</li>
                    </ul>
                    <p class="mt-3 mb-1"><strong>After uploading:</strong></p>
                    <ul>
                        <li>You will see your uploaded file listed with its <strong>name</strong>, <strong>uploader</strong>, and <strong>upload time</strong>.</li>
                        <li>Below each file, the system displays the <strong>extracted text</strong> from your document.</li>
                        <li>The extracted text is read-only at first. Click <strong>Edit</strong> to make corrections, then click <strong>Save</strong> to confirm your changes.</li>
                        <li>Once the text is confirmed, click <strong>Ready</strong> to move to the next step — where you will choose question type and quantity.</li>
                        <li>If you no longer need the file, click <strong>Delete</strong> to remove it permanently.</li>
                    </ul>
                    <p class="mt-3 mb-0 text-muted">
                        Please ensure your uploaded file meets the requirements above to avoid errors during upload.
                    </p>
                </div>
            </div>

            <script>
            function toggleInstruction() {
                const content = document.getElementById('instruction-content');
                const icon = document.getElementById('toggle-icon');
                const isHidden = content.style.display === 'none';

                if (isHidden) {
                    content.style.display = 'block';
                    icon.innerHTML = '&#9660;'; // ▼ down arrow
                } else {
                    content.style.display = 'none';
                    icon.innerHTML = '&#9654;'; // ▶ right arrow
                }
            }
            </script>


            <!-- Upload Form -->
            <form id="uploadForm" action="process_upload.php" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="quizfile" class="form-label fw-semibold">Select file</label>
                    <input class="form-control" type="file" name="quizfile" id="quizfile" required>
                </div>
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <button type="submit" class="btn btn-primary">Upload</button>
            </form>
        </div>
    </div>
</div>

<!-- Modal Warning -->
<div id="fileErrorModal" class="modal-overlay">
    <div class="modal-box">
        <h5>Invalid File</h5>
        <p id="fileErrorMessage">Please upload only PDF or PPTX files under 10 MB.</p>
        <button class="btn btn-secondary" onclick="closeModal()">OK</button>
    </div>
</div>

<script>
const allowedTypes = [
    "application/pdf",
    "application/vnd.openxmlformats-officedocument.presentationml.presentation"
];
const maxFileSize = 10 * 1024 * 1024; // 10MB

document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('quizfile');
    const files = fileInput.files;

    if (files.length !== 1) {
        e.preventDefault();
        showModal('Please select exactly one file to upload.');
        return;
    }

    const file = files[0];
    if (!allowedTypes.includes(file.type)) {
        e.preventDefault();
        showModal('Invalid file type. Only PDF or PPTX files are allowed.');
        return;
    }

    if (file.size > maxFileSize) {
        e.preventDefault();
        showModal('File is too large. Maximum size is 10 MB.');
        return;
    }
});

function showModal(message) {
    const modal = document.getElementById('fileErrorModal');
    document.getElementById('fileErrorMessage').innerText = message;
    modal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('fileErrorModal').style.display = 'none';
}
</script>

<?php
// --- Existing section: show uploaded files ---
$files = $DB->get_records('autogenquiz_files', ['autogenquizid' => $cm->instance], 'timecreated DESC');

if ($files) {
    echo html_writer::start_div('container mt-4');
    echo html_writer::tag('h4', 'Uploaded Files');

    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::tag('tr',
        html_writer::tag('th', 'Filename') .
        html_writer::tag('th', 'Uploaded By') .
        html_writer::tag('th', 'Uploaded Time') .
        html_writer::tag('th', 'Action')
    );
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($files as $file) {
        $user = $DB->get_record('user', ['id' => $file->userid], '*', MUST_EXIST);
        $fullname = fullname($user);
        $time = userdate($file->timecreated);
        $readyurl = new moodle_url('/mod/autogenquiz/generate.php', ['id' => $cm->id, 'fileid' => $file->id]);
        $deleteurl = new moodle_url('/mod/autogenquiz/delete_file.php', ['id' => $cm->id, 'fileid' => $file->id, 'sesskey' => sesskey()]);

        echo html_writer::tag('tr',
            html_writer::tag('td', s($file->filename)) .
            html_writer::tag('td', s($fullname)) .
            html_writer::tag('td', s($time)) .
            html_writer::tag('td',
                html_writer::link($readyurl, 'Ready', ['class' => 'btn btn-success me-2']) .
                html_writer::link($deleteurl, 'Delete', [
                    'class' => 'btn btn-danger',
                    'onclick' => "return confirm('Are you sure you want to delete this file?');"
                ])
            )
        );

        $confirmed = $file->confirmed_text ?? '';
        $formid = 'form_' . $file->id;

        $form  = html_writer::start_tag('form', [
            'id' => $formid,
            'action' => new moodle_url('/mod/autogenquiz/save_text.php'),
            'method' => 'post'
        ]);
        $form .= html_writer::tag('textarea', s($confirmed), [
            'id' => 'text_' . $file->id,
            'name' => 'confirmed_text',
            'rows' => 6,
            'class' => 'form-control mb-2 border-0 bg-transparent',
            'style' => 'resize:none; cursor:default; outline:none; pointer-events:none;',
            'readonly' => 'readonly'
        ]);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'fileid', 'value' => $file->id]);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $form .= html_writer::tag('button', 'Edit', [
            'type' => 'button',
            'class' => 'btn btn-secondary me-2',
            'onclick' => "
                const t = document.getElementById('text_{$file->id}');
                t.removeAttribute('readonly');
                t.classList.remove('border-0','bg-transparent');
                t.style.cursor='text';
                t.style.pointerEvents='auto';
                t.focus();
                this.disabled = true;
            "
        ]);
        $form .= html_writer::tag('button', 'Save', ['type' => 'submit', 'class' => 'btn btn-primary']);
        $form .= html_writer::end_tag('form');

        echo html_writer::tag('tr', html_writer::tag('td', $form, ['colspan' => 4]));
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
