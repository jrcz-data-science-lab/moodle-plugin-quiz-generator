<?php

require '../../config.php';
require_once $CFG->libdir . '/questionlib.php';
require_once __DIR__ . '/locallib.php';

$id    = required_param('id', PARAM_INT);
$genid = required_param('genid', PARAM_INT);

[$cm, $course, $modulecontext] = autogenquiz_require_module_context($id, 'moodle/question:add');

global $DB, $USER, $PAGE, $OUTPUT;

$PAGE->set_url('/mod/autogenquiz/import_to_bank_mcsa.php', ['id' => $id, 'genid' => $genid]);
$PAGE->set_title('Import MCSA to Question Bank');
$PAGE->set_heading('Import Multiple Choice Questions');

$rec = $DB->get_record('autogenquiz_generated', ['id' => $genid], '*', MUST_EXIST);
$items = json_decode($rec->parsed_response, true);

if (!is_array($items)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification('Failed to decode generated questions.');
    echo $OUTPUT->footer();
    exit;
}

$category = question_get_default_category($modulecontext->id, true);
if (!$category) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification('Failed to load default question category.');
    echo $OUTPUT->footer();
    exit;
}

$qtype = question_bank::get_qtype('multichoice');

foreach ($items as $item) {

    $qtext = trim($item['question'] ?? '');
    if ($qtext === '') {
        continue;
    }

    $options = $item['options'] ?? [];
    $correct = $item['correct'] ?? 0;

    $question = new stdClass();
    $question->id        = 0;
    $question->category  = $category->id;
    $question->qtype     = 'multichoice';
    $question->createdby = $USER->id;
    $question->modifiedby = $USER->id;
    $question->contextid  = $modulecontext->id;

    $form = new stdClass();

    $form->id       = 0;
    $form->parent   = 0;
    $form->category = $category->id . ',' . $category->contextid;

    $form->name = core_text::substr($qtext, 0, 200);

    $form->questiontext = [
        'text' => $qtext,
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];

    $form->generalfeedback = [
        'text' => '',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];

    $form->correctfeedback = [
        'text' => '',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];

    $form->partiallycorrectfeedback = [
        'text' => '',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];

    $form->incorrectfeedback = [
        'text' => '',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];

    $form->single = 1;
    $form->shuffleanswers = 1;
    $form->answernumbering = 'abc';

    $form->answer = [];
    $form->fraction = [];
    $form->feedback = [];

    foreach ($options as $idx => $opt) {
        $form->answer[$idx] = [
            'text' => $opt,
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ];

        $form->fraction[$idx] = ((int)$idx === (int)$correct) ? 1.0 : 0.0;

        $form->feedback[$idx] = [
            'text' => '',
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ];
    }

    $qtype->save_question($question, $form);
}

$DB->set_field('autogenquiz_generated', 'imported_to_bank', 1, ['id' => $genid]);

$realfileid = autogenquiz_get_fileid_from_taskid((int)$rec->taskid);

redirect(
    new moodle_url('/mod/autogenquiz/generate_mcsa.php', ['id' => $id, 'fileid' => $realfileid]),
    'Multiple-choice questions imported successfully.',
    null,
    core\output\notification::NOTIFY_SUCCESS
);
