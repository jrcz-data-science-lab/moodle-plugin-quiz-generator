<?php

require '../../config.php';
require_once $CFG->libdir.'/filelib.php'; // file handling library

// Read required parameters
$id = required_param('id', PARAM_INT);
$fileid = required_param('fileid', PARAM_INT); // ID of the file record to delete
require_sesskey(); // prevents CSRF attacks

// Standard Moodle access checks: user is logged in, activity exists, user has permission to view (and indirectly manage) files
$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/autogenquiz:view', $context);

// Load the file record from the plugin table
$record = $DB->get_record('autogenquiz_files', ['id' => $fileid], '*', MUST_EXIST);

// Delete the file from Moodle’s File API
$fs = get_file_storage();

if ($storedfile = $fs->get_file($context->id, 'mod_autogenquiz', 'uploadedfiles', $record->userid, '/', $record->filename)) {
    $storedfile->delete();
}

// Delete database record
$DB->delete_records('autogenquiz_files', ['id' => $fileid]);

// Redirect back with a notification
redirect(new moodle_url('/mod/autogenquiz/view.php', ['id' => $id]),
    get_string('filedeleted', 'autogenquiz'));
