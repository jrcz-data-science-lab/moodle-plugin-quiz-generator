<?php

require '../../config.php';

$genid = required_param('genid', PARAM_INT);
$id = required_param('id', PARAM_INT);

require_sesskey();

$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/autogenquiz:view', $context);

// Reads the questions[...] array from the submitted form
$questions = $_POST['questions'] ?? [];
// If nothing is received, redirect back to generate.php and show an error
if (empty($questions)) {
    redirect(
        new moodle_url('/mod/autogenquiz/generate.php', ['id' => $id, 'fileid' => 0, 'saved' => 0]),
        'No questions received.',
        null,
        core\output\notification::NOTIFY_ERROR
    );
}

// Normalize the data
$cleaned = [];
$i = 1;
foreach ($questions as $q) {
    // skips any question with empty text
    if (empty($q['question'])) {
        continue;
    }
    // rebuilds a clean, consistent array
    $cleaned[] = [
        'id' => $i,
        'type' => 'tf',
        'question' => trim($q['question']),
        'answer' => ucfirst(strtolower($q['answer'] ?? 'True')),
    ];
    ++$i;
}

// Save
$record = $DB->get_record('autogenquiz_generated', ['id' => $genid], '*', MUST_EXIST); // Loads the existing generation entry
$record->parsed_response = json_encode($cleaned, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); // Overwrites parsed_response with the normalized JSON version of the teacher-edited questions
$record->updated_at = time(); // Updates updated_at timestamp
$DB->update_record('autogenquiz_generated', $record); // Saves back to DB

// Redirects to generate.php: the teacher returns to the same page, sees their updated questions, and a success message.
redirect(
    new moodle_url('/mod/autogenquiz/generate.php', ['id' => $id, 'fileid' => $record->taskid, 'saved' => 1, 'genid' => $genid]),
    'Questions saved successfully.',
    null,
    core\output\notification::NOTIFY_SUCCESS
);
