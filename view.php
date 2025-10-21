<?php
require('../../config.php');

use core\output\notification;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);

echo $OUTPUT->header();
echo $OUTPUT->heading('AutoGenQuiz Activity');

// 上传表单
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

echo $OUTPUT->footer();
