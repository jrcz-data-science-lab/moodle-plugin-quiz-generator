<?php

require '../../config.php';
require_once $CFG->libdir . '/questionlib.php';
require_once __DIR__ . '/locallib.php';

$id = required_param('id', PARAM_INT);
$genid = required_param('genid', PARAM_INT);

[$cm, $course, $context] = autogenquiz_require_module_context($id, 'moodle/question:add');

global $DB, $USER;

$rec = $DB->get_record('autogenquiz_generated', ['id' => $genid], '*', MUST_EXIST);
$items = json_decode($rec->parsed_response, true);

$category = question_get_default_category($context->id, true);
$qtype = question_bank::get_qtype('shortanswer');

foreach ($items as $item) {

    if (empty($item['question']) || empty($item['answers'])) {
        continue;
    }

    $question = new stdClass();
    $question->id = 0;
    $question->category = $category->id;
    $question->qtype = 'shortanswer';
    $question->createdby = $USER->id;
    $question->modifiedby = $USER->id;
    $question->contextid = $context->id;

    $form = new stdClass();
    $form->id = 0;
    $form->parent = 0;
    $form->category = $category->id . ',' . $category->contextid;

    $form->name = core_text::substr($item['question'], 0, 200);

    $form->questiontext = [
        'text' => $item['question'],
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];

    $form->generalfeedback = [
        'text' => '',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];

    $form->usecase = 0;

    $form->answer = [];
    $form->fraction = [];
    $form->feedback = [];

    foreach ($item['answers'] as $i => $ans) {
        $form->answer[$i] = trim($ans);
        $form->fraction[$i] = 1.0;
        $form->feedback[$i] = [
            'text' => '',
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ];
    }

    $qtype->save_question($question, $form);
}

$DB->set_field('autogenquiz_generated', 'imported_to_bank', 1, ['id' => $genid]);

redirect(
    new moodle_url('/mod/autogenquiz/generate_fib.php', [
        'id' => $id,
        'fileid' => autogenquiz_get_fileid_from_taskid((int)$rec->taskid),
    ]),
    'Fill-in-the-blank questions imported successfully.',
    null,
    core\output\notification::NOTIFY_SUCCESS
);
