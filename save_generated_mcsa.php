<?php

require '../../config.php';

// Read required params
$genid  = required_param('genid', PARAM_INT);
$id     = required_param('id', PARAM_INT);
$fileid = required_param('fileid', PARAM_INT);

require_sesskey();

// Load context
$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/autogenquiz:view', $context);

global $DB;

// Retrieve generation record
$gen = $DB->get_record('autogenquiz_generated', ['id' => $genid], '*', MUST_EXIST);

$items = $_POST['questions'] ?? [];

$clean = [];

foreach ($items as $i => $q) {
    $questiontext = trim($q['question'] ?? '');

    if ($questiontext === '') {
        continue;
    }

    $correctIndex = isset($q['correct']) ? (int)$q['correct'] : 0;
    $options = $q['options'] ?? [];

    $clean[] = [
        'type' => 'mcsa',
        'question' => $questiontext,
        'options' => array_values($options),
        'correct' => $correctIndex
    ];
}

if (empty($clean)) {
    redirect(
        new moodle_url('/mod/autogenquiz/generate_mcsa.php', [
            'id' => $id,
            'fileid' => $fileid,
            'genid' => $genid,
            'saved' => 0
        ]),
        'No valid questions to save.',
        2,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Update stored parsed_response
$gen->parsed_response = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$DB->update_record('autogenquiz_generated', $gen);

// Mark as approved
$DB->set_field('autogenquiz_generated', 'is_approved', 1, ['id' => $genid]);

// Redirect back to generate_mcsa with success message
redirect(
    new moodle_url('/mod/autogenquiz/generate_mcsa.php', [
        'id' => $id,
        'fileid' => $fileid,
        'genid' => $genid,
        'saved' => 1
    ]),
    'Changes saved.',
    1,
    \core\output\notification::NOTIFY_SUCCESS
);
