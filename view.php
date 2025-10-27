<?php
require('../../config.php');

use core\output\notification;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);

$PAGE->set_url('/mod/autogenquiz/view.php', ['id' => $id]);

echo $OUTPUT->header();
echo $OUTPUT->heading('AutoGenQuiz Activity');
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            Upload your file to generate quiz
        </div>
        <div class="card-body">
            <form action="process_upload.php" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="quizfile" class="form-label">Select file</label>
                    <input class="form-control" type="file" name="quizfile" id="quizfile" required>
                </div>
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <button type="submit" class="btn btn-primary">Upload</button>
            </form>
        </div>
    </div>
</div>

<?php
// Show uploaded files for this activity only.
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
