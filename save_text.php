<?php

require '../../config.php';

// Read submitted form parameters
$fileid = required_param('fileid', PARAM_INT);
$id = required_param('id', PARAM_INT);
$confirmed = required_param('confirmed_text', PARAM_RAW);

require_sesskey(); // CSRF protection

// Standard Moodle access checks
$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/autogenquiz:view', $context);

// Load the file record
$record = $DB->get_record('autogenquiz_files', ['id' => $fileid], '*', MUST_EXIST);

// Update the confirmed text
$record->confirmed_text = $confirmed;
$DB->update_record('autogenquiz_files', $record);

// Redirect back with success message
redirect(
    new moodle_url('/mod/autogenquiz/view.php', ['id' => $id]),
    get_string('textsavesuccess', 'autogenquiz'),
    null,
    core\output\notification::NOTIFY_SUCCESS
);
