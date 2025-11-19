<?php
require('../../config.php');

$id = required_param('id', PARAM_INT); // Get required course ID from the URL

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST); // Load the course record

require_login($course); // Makes sure the user is logged into this course.
$PAGE->set_pagelayout('incourse'); // Sets the standard layout for course pages.

// Setup page metadata: URL of the page, Browser title, Page heading, Breadcrumb navigation
$params = array('id' => $id);
$PAGE->set_url('/mod/autogenquiz/index.php', $params);
$PAGE->set_title($course->shortname.': '.get_string('modulenameplural', 'autogenquiz'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('modulenameplural', 'autogenquiz'));

// Output header + page title
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'autogenquiz'));

// Get all AutoGenQuiz instances inside this course: get_all_instances_in_course() is a Moodle helper, It returns an array of all activities of this plugin inside this course.
if (!$autogenquizzes = get_all_instances_in_course('autogenquiz', $course)) {
    notice(get_string('thereareno', 'moodle', get_string('modulenameplural', 'autogenquiz')),
           new moodle_url('/course/view.php', array('id' => $course->id)));
    exit;
}

$table = new html_table(); // create a table
$table->head = array(get_string('name'));
$table->align = array('left');

// For each activity: Build a link to its view.php page, Use the activity name as label, Add to table rows
foreach ($autogenquizzes as $autogenquiz) {
    $link = html_writer::link(
        new moodle_url('/mod/autogenquiz/view.php', array('id' => $autogenquiz->coursemodule)),
        $autogenquiz->name
    );
    $table->data[] = array($link);
}

// Print table and footer
echo html_writer::table($table);
echo $OUTPUT->footer();