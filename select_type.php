<?php
require '../../config.php';

// Read params
$id = required_param('id', PARAM_INT);
$fileid = required_param('fileid', PARAM_INT); // extracted text source

// Standard Moodle access check
$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/autogenquiz:view', $context);

$PAGE->set_url('/mod/autogenquiz/select_type.php', ['id' => $id, 'fileid' => $fileid]);
$PAGE->set_title('Select Question Type');
$PAGE->set_heading('Select Question Type');

echo $OUTPUT->header();
echo $OUTPUT->heading('Choose Question Type', 3);

// Buttons → redirect to corresponding generate page
$tfurl = new moodle_url('/mod/autogenquiz/generate.php', [
  'id' => $id,
  'fileid' => $fileid,
  'type' => 'tf'
]);

$mcsaurl = new moodle_url('/mod/autogenquiz/generate_mcsa.php', [
  'id' => $id,
  'fileid' => $fileid,
  'type' => 'mcsa'
]);

$fiburl = new moodle_url('/mod/autogenquiz/generate_fib.php', [
  'id' => $id,
  'fileid' => $fileid
]);

echo '<div class="card p-4" style="max-width:500px;">';
echo '<p>Please choose the question type you want to generate.</p>';

echo '<a class="btn btn-primary mb-2" style="width:100%;" href="' . $tfurl . '">
        Generate True/False Questions
      </a>';

echo '<a class="btn btn-success" style="width:100%;" href="' . $mcsaurl . '">
        Generate Multiple Choice (Single Answer)
      </a>';

echo '<a class="btn btn-warning mt-2" style="width:100%;" href="' . $fiburl . '">
        Generate Fill in the Blank
      </a>';

echo '</div>';

echo '<a class="btn btn-secondary mt-3" href="' .
  new moodle_url('/mod/autogenquiz/view.php', ['id' => $id]) .
  '">Back</a>';

echo $OUTPUT->footer();
