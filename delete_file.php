<?php
require('../../config.php');
require_once($CFG->libdir . '/filelib.php');

$id = required_param('id', PARAM_INT);
$fileid = required_param('fileid', PARAM_INT);
require_sesskey();

$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/autogenquiz:view', $context);

$record = $DB->get_record('autogenquiz_files', ['id' => $fileid], '*', MUST_EXIST);
$fs = get_file_storage();

if ($storedfile = $fs->get_file($context->id, 'mod_autogenquiz', 'uploadedfiles', $record->userid, '/', $record->filename)) {
    $storedfile->delete();
}
$DB->delete_records('autogenquiz_files', ['id' => $fileid]);

redirect(new moodle_url('/mod/autogenquiz/view.php', ['id' => $id]),
    get_string('filedeleted', 'autogenquiz'));
