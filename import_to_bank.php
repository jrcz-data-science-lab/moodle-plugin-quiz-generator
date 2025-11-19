<?php

require '../../config.php';
require_once $CFG->libdir.'/questionlib.php'; // Loads the question engine functions (required to create questions)

// Required parameters
$id = required_param('id', PARAM_INT);
$genid = required_param('genid', PARAM_INT);

// Standard Moodle security and setup: This ensures only users who are allowed to add questions (teachers, managers) can import.
$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);
require_capability('moodle/question:add', $modulecontext);

global $DB, $USER, $PAGE, $OUTPUT;

$PAGE->set_url('/mod/autogenquiz/import_to_bank.php', ['id' => $id, 'genid' => $genid]);
$PAGE->set_title('Import to Question Bank');
$PAGE->set_heading('Import to Question Bank');

// Load the generated questions
$rec = $DB->get_record('autogenquiz_generated', ['id' => $genid], '*', MUST_EXIST);
// Converts the saved JSON (cleaned by save_generated.php) into PHP array
$items = json_decode($rec->parsed_response, true);
if (!is_array($items)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification('Failed to decode generated questions.');
    echo $OUTPUT->footer();
    exit;
}

// Get the default question category (activity-level): If the category doesn't exist, Moodle automatically creates it.
$category = question_get_default_category($modulecontext->id, true);
if (!$category) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification('Failed to load or create default question category.');
    echo $OUTPUT->footer();
    exit;
}

// Prepare the True/False question type handler
$qtype = question_bank::get_qtype('truefalse');

// Loop through each AI-generated question item and create a Moodle question
foreach ($items as $item) {
    // Extract question text
    $qtext = trim($item['question'] ?? '');
    if ($qtext === '') {
        continue;
    }

    // Determine the correct answer: Convert "True" or "False" into Moodle’s expected boolean (1 or 0)
    $istrue = strtolower(trim($item['answer'] ?? 'true')) === 'true';

    // Create a new question object
    $question = new stdClass();
    $question->id = 0;
    $question->category = $category->id;
    $question->qtype = 'truefalse';
    $question->createdby = $USER->id;
    $question->modifiedby = $USER->id;
    $question->contextid = $modulecontext->id;

    // Prepare the form data for saving the question
    $form = new stdClass();
    $form->id = 0;
    $form->parent = 0;
    $form->category = $category->id.','.$category->contextid;

    $form->name = core_text::substr($qtext, 0, 200); // Question name = first 200 chars of the question text

    $form->questiontext = [
        'text' => $qtext,
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];

    // Set the feedback fields as empty
    $form->generalfeedback = [
        'text' => '',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];

    $form->correctanswer = $istrue ? 1 : 0;
    $form->feedbacktrue = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
    $form->feedbackfalse = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];

    // Save the question
    $qtype->save_question($question, $form);
}

// Mark as imported
$DB->set_field('autogenquiz_generated', 'imported_to_bank', 1, ['id' => $genid]);

// Teacher returns to generate.php with success notification.
redirect(
    new moodle_url('/mod/autogenquiz/generate.php', ['id' => $id, 'fileid' => $rec->taskid]),
    'Imported successfully to the Question Bank.',
    null,
    core\output\notification::NOTIFY_SUCCESS
);
