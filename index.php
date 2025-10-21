<?php
require('../../config.php');

$id = required_param('id', PARAM_INT); // Course ID

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_login($course);
$PAGE->set_pagelayout('incourse');

$params = array('id' => $id);
$PAGE->set_url('/mod/autogenquiz/index.php', $params);
$PAGE->set_title($course->shortname.': '.get_string('modulenameplural', 'autogenquiz'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('modulenameplural', 'autogenquiz'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'autogenquiz'));

if (!$autogenquizzes = get_all_instances_in_course('autogenquiz', $course)) {
    notice(get_string('thereareno', 'moodle', get_string('modulenameplural', 'autogenquiz')),
           new moodle_url('/course/view.php', array('id' => $course->id)));
    exit;
}

$table = new html_table();
$table->head = array(get_string('name'));
$table->align = array('left');

foreach ($autogenquizzes as $autogenquiz) {
    $link = html_writer::link(
        new moodle_url('/mod/autogenquiz/view.php', array('id' => $autogenquiz->coursemodule)),
        $autogenquiz->name
    );
    $table->data[] = array($link);
}

echo html_writer::table($table);
echo $OUTPUT->footer();