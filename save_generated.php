<?php

require '../../config.php';
require_once __DIR__ . '/locallib.php';

$genid = required_param('genid', PARAM_INT);
$id = required_param('id', PARAM_INT);
$fileid = required_param('fileid', PARAM_INT);

require_sesskey();

[$cm, $course, $context] = autogenquiz_require_module_context($id, 'mod/autogenquiz:view');

global $DB;

$questions = $_POST['questions'] ?? [];
if (empty($questions)) {
    redirect(
        new moodle_url('/mod/autogenquiz/generate.php', ['id' => $id, 'fileid' => $fileid, 'saved' => 0, 'genid' => $genid]),
        'No questions received.',
        null,
        core\output\notification::NOTIFY_ERROR
    );
}

$cleaned = [];
$i = 1;
foreach ($questions as $q) {
    if (empty($q['question'])) {
        continue;
    }
    $cleaned[] = [
        'id' => $i,
        'type' => 'tf',
        'question' => trim($q['question']),
        'answer' => ucfirst(strtolower($q['answer'] ?? 'True')),
    ];
    ++$i;
}

$record = $DB->get_record('autogenquiz_generated', ['id' => $genid], '*', MUST_EXIST);
$record->parsed_response = json_encode($cleaned, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$DB->update_record('autogenquiz_generated', $record);

$realfileid = autogenquiz_get_fileid_from_taskid((int)$record->taskid);

redirect(
    new moodle_url('/mod/autogenquiz/generate.php', ['id' => $id, 'fileid' => $realfileid, 'saved' => 1, 'genid' => $genid]),
    'Questions saved successfully.',
    null,
    core\output\notification::NOTIFY_SUCCESS
);
