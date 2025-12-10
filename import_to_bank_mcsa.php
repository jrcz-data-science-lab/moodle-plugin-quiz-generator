<?php

require '../../config.php';
require_once $CFG->libdir . '/questionlib.php';

// Required parameters
$id    = required_param('id', PARAM_INT);
$genid = required_param('genid', PARAM_INT);

// Standard Moodle setup
$cm     = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);

// Same as T/F → require ability to add questions
require_capability('moodle/question:add', $modulecontext);

global $DB, $USER, $PAGE, $OUTPUT;

// Page info
$PAGE->set_url('/mod/autogenquiz/import_to_bank_mcsa.php', ['id' => $id, 'genid' => $genid]);
$PAGE->set_title('Import MCSA to Question Bank');
$PAGE->set_heading('Import Multiple Choice Questions');

// Load generation record
$rec = $DB->get_record('autogenquiz_generated', ['id' => $genid], '*', MUST_EXIST);
$items = json_decode($rec->parsed_response, true);

if (!is_array($items)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification('Failed to decode generated questions.');
    echo $OUTPUT->footer();
    exit;
}

// Load task to retrieve original fileid
$task = $DB->get_record('autogenquiz_tasks', ['id' => $rec->taskid], '*', MUST_EXIST);

// --- IMPORTANT ---
// Same method as T/F to get correct category
$category = question_get_default_category($modulecontext->id, true);
if (!$category) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification('Failed to load default question category.');
    echo $OUTPUT->footer();
    exit;
}

// Get the MULTICHOICE qtype handler (required)
$qtype = question_bank::get_qtype('multichoice');

// Loop through each generated question
foreach ($items as $item) {

    $qtext = trim($item['question'] ?? '');
    if ($qtext === '') {
        continue;
    }

    $options = $item['options'] ?? [];
    $correct = $item['correct'] ?? 0;

    // --- Create question object ---
    $question = new stdClass();
    $question->id        = 0;
    $question->category  = $category->id;
    $question->qtype     = 'multichoice';
    $question->createdby = $USER->id;
    $question->modifiedby = $USER->id;
    $question->contextid  = $modulecontext->id;

    // --- Build form data (VERY IMPORTANT for qtype->save_question) ---
    $form = new stdClass();

    $form->id       = 0;
    $form->parent   = 0;
    $form->category = $category->id . ',' . $category->contextid;

    // Question name = first 200 chars
    $form->name = core_text::substr($qtext, 0, 200);

    $form->questiontext = [
        'text' => $qtext,
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];

    // General feedback (empty)
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

    // MULTICHOICE-SPECIFIC FORM FIELDS
    $form->single = 1;  // Single-answer question
    $form->shuffleanswers = 1;
    $form->answernumbering = 'abc';

    // Build answers following Moodle expected structure
    $form->answer = [];
    $form->fraction = [];
    $form->feedback = [];

    foreach ($options as $idx => $opt) {
        $form->answer[$idx] = [
            'text' => $opt,
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ];

        // Only one correct answer
        $form->fraction[$idx] = ($idx == $correct) ? 1.0 : 0.0;

        $form->feedback[$idx] = [
            'text' => '',
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ];
    }

    // Save the question
    $qtype->save_question($question, $form);
}

// mark imported
$DB->set_field('autogenquiz_generated', 'imported_to_bank', 1, ['id' => $genid]);

// redirect back
redirect(
    new moodle_url('/mod/autogenquiz/generate_mcsa.php', ['id' => $id, 'fileid' => $task->fileid]),
    'Multiple-choice questions imported successfully.',
    null,
    core\output\notification::NOTIFY_SUCCESS
);
