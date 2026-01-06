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
$clean = [];

foreach ($questions as $q) {
    if (empty($q['question']) || empty($q['answers'])) {
        continue;
    }
    $clean[] = [
        'type' => 'fib',
        'question' => trim($q['question']),
        'answers' => array_values(array_filter($q['answers'])),
    ];
}

$rec = $DB->get_record('autogenquiz_generated', ['id' => $genid], '*', MUST_EXIST);
$rec->parsed_response = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$DB->update_record('autogenquiz_generated', $rec);

redirect(
    new moodle_url('/mod/autogenquiz/generate_fib.php', [
        'id' => $id,
        'fileid' => $fileid,
        'genid' => $genid,
        'saved' => 1,
    ]),
    'Changes saved.',
    null,
    core\output\notification::NOTIFY_SUCCESS
);
