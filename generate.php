<?php

require '../../config.php'; // Loads Moodle environment
require_once __DIR__.'/ai_request.php'; //  Loads the AI generation function

// Read parameters
$id = required_param('id', PARAM_INT);
$fileid = optional_param('fileid', 0, PARAM_INT);
$genid = optional_param('genid', 0, PARAM_INT);
$saved = optional_param('saved', 0, PARAM_INT);

$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, true, $cm); // Require login

$context = context_module::instance($cm->id);

require_capability('mod/autogenquiz:view', $context); // Check view permission

global $DB;

// Resolve fileid from genid
// If user clicks a saved generation (genid), then system needs to find its associated file.
// This step ensures the UI always knows which file the generation belongs to.
if (!$fileid && $genid) {
    $rec = $DB->get_record('autogenquiz_generated', ['id' => $genid]);
    if ($rec) {
        $task = $DB->get_record('autogenquiz_tasks', ['id' => $rec->taskid]);
        if ($task) {
            $fileid = $task->fileid;
        }
    }
}

// Redirect if fileid is missing
if (!$fileid) {
    redirect(
        new moodle_url('/mod/autogenquiz/view.php', ['id' => $id]),
        'Missing file reference. Please re-upload the file.',
        null,
        core\output\notification::NOTIFY_ERROR
    );
}

// output: page header, heading, "View Question Bank" button
$PAGE->set_url('/mod/autogenquiz/generate.php', ['id' => $id, 'fileid' => $fileid]);
$PAGE->set_title('AutoGenQuiz - Generate');
$PAGE->set_heading('Generate True/False Questions');

echo $OUTPUT->header();
echo $OUTPUT->heading('AutoGenQuiz Generator');
echo '<div class="alert alert-info mt-2">
    Generating questions may take up to one minute depending on the server connection. 
    If it is unusually slow, please contact the LLM administrator or the technical department.
</div>';

echo '<a href="'.new moodle_url('/question/edit.php', ['cmid' => $cm->id]).
    '" class="btn btn-outline-secondary mb-3">View Question Bank</a>';

// Show success message after saving
if ($saved) {
    echo $OUTPUT->notification('Changes saved successfully.', core\output\notification::NOTIFY_SUCCESS);
}

// Editable form for showing AI-generated questions
function render_editable_form(int $id, int $fileid, int $genid, array $cleanjson, int $cmid): void
{
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/mod/autogenquiz/save_generated.php'),
    ]);

    echo html_writer::input_hidden_params(
        new moodle_url('', [
            'genid' => $genid,
            'id' => $id,
            'fileid' => $fileid,
            'sesskey' => sesskey(),
        ])
    );

    echo '<div id="question-list">';
    $i = 1;

    // one card per question
    foreach ($cleanjson as $q) {
        $qtext = s(trim($q['question'] ?? ''));
        if ($qtext === '') {
            continue;
        }

        $ans = ucfirst(strtolower(trim($q['answer'] ?? 'True')));
        if (!in_array($ans, ['True', 'False'], true)) {
            $ans = 'True';
        }

        echo '<div class="card mb-3 p-3" id="q_'.$i.'">';
        echo '<div class="d-flex justify-content-between align-items-center">';
        echo '<h6 class="fw-bold mb-0">Question '.$i.'</h6>';
        echo '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeQuestion('.$i.')">Delete</button>';
        echo '</div>';

        echo '<textarea class="form-control mt-2" name="questions['.$i.'][question]" rows="2">'.$qtext.'</textarea>';

        echo '<div class="mt-2"><label class="me-2 fw-semibold">Answer:</label>';
        echo '<select name="questions['.$i.'][answer]" class="form-select d-inline-block" style="width:auto;">';

        // dropdown for answer (True/False)
        foreach (['True', 'False'] as $opt) {
            $sel = $ans === $opt ? ' selected' : '';
            echo '<option value="'.$opt.'"'.$sel.'>'.$opt.'</option>';
        }

        echo '</select></div></div>';
        ++$i;
    }

    echo '</div>';

    echo '<div class="mt-3">';
    echo '<button type="submit" class="btn btn-primary me-2">Save Changes</button>';

    // Import button
    $importurl = new moodle_url('/mod/autogenquiz/import_to_bank.php', [
        'genid' => $genid,
        'id' => $id,
    ]);
    echo '<a href="'.$importurl.'" class="btn btn-success">Import to Question Bank</a>';

    // View plugin’s private question bank
    echo '<a href="'.new moodle_url('/question/edit.php', ['cmid' => $cmid]).
        '" class="btn btn-secondary ms-2">View Question Bank</a>';

    echo '</div>';

    echo html_writer::end_tag('form');

    echo <<<JS

<script>
function removeQuestion(id) {
    const el = document.getElementById('q_' + id);
    if (el) el.remove();
}
</script>
JS;
}

// Form: Before generating, user enters the number of questions. This posts back to the same page.
$formurl = new moodle_url('/mod/autogenquiz/generate.php', ['id' => $id, 'fileid' => $fileid]);

echo '<div class="card mb-3"><div class="card-body">';
echo '<form method="post" action="'.$formurl.'">';

echo '<div class="mb-3"><label class="form-label fw-semibold">Number of True/False Questions</label>';
echo '<input type="number" name="question_count" class="form-control" min="1" max="20" value="5" required></div>';

echo '<input type="hidden" name="fileid" value="'.$fileid.'">';

echo '<button type="submit" class="btn btn-primary">Generate True/False</button>';
echo '<a href="'.new moodle_url('/mod/autogenquiz/view.php', ['id' => $id]).'" class="btn btn-secondary ms-2">Back</a>';

echo '</form></div></div>';

// Handle POST = generate new questions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $count = required_param('question_count', PARAM_INT); // Read number of questions
    $fid = required_param('fileid', PARAM_INT);

    $file = $DB->get_record('autogenquiz_files', ['id' => $fid]);
    if (!$file) {
        echo $OUTPUT->notification('Source file is missing.', core\output\notification::NOTIFY_ERROR);
        echo $OUTPUT->footer();
        exit;
    }

    // Create a task record
    $task = (object) [
        'fileid' => $fid,
        'courseid' => $course->id,
        'status' => 'sent_request',
        'created_at' => time(),
        'updated_at' => time(),
    ];
    $taskid = $DB->insert_record('autogenquiz_tasks', $task, true);

    // Call AI generation function
    $res = autogenquiz_generate_tf_questions($file->confirmed_text, $count);
    $data = json_decode($res, true);

    if (!empty($data['connection_error'])) {
        echo '<div class="alert alert-danger mt-2">
        The system could not connect to the question-generation server. 
        This plugin is temporarily unavailable. Please contact the LLM administrator or technical department.
    </div>';

        echo $OUTPUT->footer();
        exit;
    }

    // Parse AI response
    $raw = $data['response'] ?? $data['message']['content'] ?? $res;
    $raw = trim(preg_replace(['/^```(json)?/i', '/```$/'], '', $raw));

    // Try to decode JSON
    $arr = json_decode($raw, true);
    if (!is_array($arr) && preg_match('/\[[\s\S]*\]/', $raw, $m)) {
        $arr = json_decode($m[0], true);
    }

    // Error if parsing failed
    if (!is_array($arr)) {
        echo $OUTPUT->notification('Failed to parse AI output.', core\output\notification::NOTIFY_ERROR);
        echo '<pre>'.s($raw).'</pre>';
        echo $OUTPUT->footer();
        exit;
    }

    // Ensure each question has type and answer
    foreach ($arr as &$q) {
        $q['type'] = 'tf';
        if (!isset($q['answer'])) {
            $q['answer'] = 'True';
        }
    }

    // Save generation record
    $gen = (object) [
        'taskid' => $taskid,
        'rawtext' => $file->confirmed_text,
        'llm_response' => $res,
        'parsed_response' => json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        'is_approved' => 0,
        'imported_to_bank' => 0,
    ];
    // Insert into autogenquiz_generated table
    $newid = $DB->insert_record('autogenquiz_generated', $gen, true);

    // output: generated questions in editable form
    echo html_writer::tag('h5', 'Generated Questions');
    render_editable_form($id, $fid, $newid, $arr, $cm->id);

    echo $OUTPUT->footer();
    exit;
}

// If genid is provided, load and show saved generation
if ($genid) {
    $rec = $DB->get_record('autogenquiz_generated', ['id' => $genid], '*', IGNORE_MISSING);
    if ($rec) {
        $arr = json_decode($rec->parsed_response, true) ?: [];
        echo html_writer::tag('h5', 'Generated Questions');
        render_editable_form($id, $fileid, $rec->id, $arr, $cm->id);
    } else {
        echo $OUTPUT->notification('Generation record not found.', core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->footer();
