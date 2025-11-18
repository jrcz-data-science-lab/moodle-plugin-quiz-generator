<?php
require('../../config.php');
require_once($CFG->libdir . '/questionlib.php');

$id    = required_param('id', PARAM_INT);
$genid = required_param('genid', PARAM_INT);

$cm      = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course  = get_course($cm->course);
require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);
require_capability('moodle/question:add', $modulecontext);

global $DB, $USER, $PAGE, $OUTPUT;

$PAGE->set_url('/mod/autogenquiz/import_to_bank.php', ['id' => $id, 'genid' => $genid]);
$PAGE->set_title('Import to Question Bank');
$PAGE->set_heading('Import to Question Bank');

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
    echo $OUTPUT->notification('Failed to load or create default question category.');
    echo $OUTPUT->footer();
    exit;
}

$qtype = question_bank::get_qtype('truefalse');

foreach ($items as $item) {
    $qtext = trim($item['question'] ?? '');
    if ($qtext === '') {
        continue;
    }

    $istrue = strtolower(trim($item['answer'] ?? 'true')) === 'true';

    $question = new stdClass();
    $question->id         = 0;
    $question->category   = $category->id;
    $question->qtype      = 'truefalse';
    $question->createdby  = $USER->id;
    $question->modifiedby = $USER->id;
    $question->contextid  = $modulecontext->id;

    $form = new stdClass();
    $form->id       = 0;
    $form->parent   = 0;
    $form->category = $category->id . ',' . $category->contextid;

    $form->name = core_text::substr($qtext, 0, 200);

    $form->questiontext = [
        'text'   => $qtext,
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];

    $form->generalfeedback = [
        'text'   => '',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];

    $form->correctanswer = $istrue ? 1 : 0;
    $form->feedbacktrue = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
    $form->feedbackfalse = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];

    $qtype->save_question($question, $form);
}

$DB->set_field('autogenquiz_generated', 'imported_to_bank', 1, ['id' => $genid]);

redirect(
    new moodle_url('/mod/autogenquiz/generate.php', ['id' => $id, 'fileid' => $rec->taskid]),
    'Imported successfully to the Question Bank.',
    null,
    \core\output\notification::NOTIFY_SUCCESS
);

