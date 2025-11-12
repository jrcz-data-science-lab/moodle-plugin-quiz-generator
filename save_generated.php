<?php
require('../../config.php');

$genid = required_param('genid', PARAM_INT);
$id = required_param('id', PARAM_INT);
require_sesskey();

$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/autogenquiz:view', $context);

$questions = $_POST['questions'] ?? [];
if (empty($questions)) {
    redirect(
        new moodle_url('/mod/autogenquiz/generate.php', ['id' => $id, 'fileid' => 0, 'saved' => 0]),
        'No questions received.',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Normalize
$cleaned = [];
$i = 1;
foreach ($questions as $q) {
    if (empty($q['question'])) continue;
    $cleaned[] = [
        'id' => $i,
        'type' => 'tf',
        'question' => trim($q['question']),
        'answer' => ucfirst(strtolower($q['answer'] ?? 'True'))
    ];
    $i++;
}

// Save
$record = $DB->get_record('autogenquiz_generated', ['id' => $genid], '*', MUST_EXIST);
$record->parsed_response = json_encode($cleaned, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$record->updated_at = time();
$DB->update_record('autogenquiz_generated', $record);

// Redirect back to the same page to continue editing
redirect(
    new moodle_url('/mod/autogenquiz/generate.php', ['id' => $id, 'fileid' => $record->taskid, 'saved' => 1, 'genid' => $genid]),
    'Questions saved successfully.',
    null,
    \core\output\notification::NOTIFY_SUCCESS
);