<?php
require('../../config.php');

$id = required_param('id', PARAM_INT);
$fileid = required_param('fileid', PARAM_INT);

$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);

$PAGE->set_url('/mod/autogenquiz/generate.php', ['id' => $id, 'fileid' => $fileid]);
$PAGE->set_title('AutoGenQuiz - Generate Questions');
$PAGE->set_heading('Generate Questions');

echo $OUTPUT->header();
echo $OUTPUT->heading('Question Generation Setup');
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <p class="fs-5 text-muted">
                This page will allow you to choose question types and adjust the number of questions generated from the confirmed text.
            </p>
            <p class="text-secondary">
                (Feature in development — placeholder page for demonstration.)
            </p>
            <hr>
            <p>
                <a href="<?php echo new moodle_url('/mod/autogenquiz/view.php', ['id' => $id]); ?>" class="btn btn-secondary">
                    ← Back to Uploads
                </a>
            </p>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
