<?php

defined('MOODLE_INTERNAL') || exit;

function autogenquiz_require_module_context(int $id, string $capability = 'mod/autogenquiz:view'): array
{
    $cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
    $course = get_course($cm->course);
    require_login($course, true, $cm);
    $context = context_module::instance($cm->id);
    require_capability($capability, $context);
    return [$cm, $course, $context];
}

function autogenquiz_resolve_fileid_from_genid(int $genid): int
{
    global $DB;
    if (!$genid) {
        return 0;
    }
    $rec = $DB->get_record('autogenquiz_generated', ['id' => $genid], '*', IGNORE_MISSING);
    if (!$rec) {
        return 0;
    }
    $task = $DB->get_record('autogenquiz_tasks', ['id' => $rec->taskid], '*', IGNORE_MISSING);
    if (!$task) {
        return 0;
    }
    return (int)$task->fileid;
}

function autogenquiz_get_taskid_from_genid(int $genid): int
{
    global $DB;
    $rec = $DB->get_record('autogenquiz_generated', ['id' => $genid], 'taskid', MUST_EXIST);
    return (int)$rec->taskid;
}

function autogenquiz_get_fileid_from_taskid(int $taskid): int
{
    global $DB;
    $task = $DB->get_record('autogenquiz_tasks', ['id' => $taskid], 'fileid', MUST_EXIST);
    return (int)$task->fileid;
}

function autogenquiz_create_task(int $fileid, int $courseid, string $status = 'sent_request'): int
{
    global $DB;
    $task = (object)[
        'fileid' => $fileid,
        'courseid' => $courseid,
        'status' => $status,
        'created_at' => time(),
        'updated_at' => time(),
    ];
    return (int)$DB->insert_record('autogenquiz_tasks', $task, true);
}

function autogenquiz_parse_ai_to_array(string $res, ?string &$rawout = null, ?string &$errorout = null): ?array
{
    $data = json_decode($res, true);

    if (is_array($data) && !empty($data['connection_error'])) {
        $errorout = 'connection_error';
        return null;
    }

    $raw = is_array($data) ? ($data['response'] ?? ($data['message']['content'] ?? $res)) : $res;
    $raw = trim($raw);
    $raw = trim(preg_replace(['/^```(json)?/i', '/```$/'], '', $raw));

    $rawout = $raw;

    $arr = json_decode($raw, true);
    if (!is_array($arr) && preg_match('/\[[\s\S]*\]/', $raw, $m)) {
        $arr = json_decode($m[0], true);
    }

    if (!is_array($arr)) {
        $errorout = 'parse_error';
        return null;
    }

    return $arr;
}

function autogenquiz_normalize_tf_items(array $arr): array
{
    foreach ($arr as &$q) {
        $q['type'] = 'tf';
        if (!isset($q['answer'])) {
            $q['answer'] = 'True';
        }
    }
    return $arr;
}

function autogenquiz_normalize_mcsa_items(array $arr): array
{
    foreach ($arr as &$q) {
        $q['type'] = 'mcsa';
        if (empty($q['options']) || !is_array($q['options'])) {
            $q['options'] = ['Option A', 'Option B', 'Option C', 'Option D'];
        }
        if (!isset($q['correct'])) {
            $q['correct'] = 0;
        }
    }
    return $arr;
}

function autogenquiz_save_generation(int $taskid, string $rawtext, string $llmresponse, array $parsed): int
{
    global $DB;
    $gen = (object)[
        'taskid' => $taskid,
        'rawtext' => $rawtext,
        'llm_response' => $llmresponse,
        'parsed_response' => json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        'is_approved' => 0,
        'imported_to_bank' => 0,
    ];
    return (int)$DB->insert_record('autogenquiz_generated', $gen, true);
}

function autogenquiz_redirect_missing_file(int $id): void
{
    redirect(
        new moodle_url('/mod/autogenquiz/view.php', ['id' => $id]),
        'Missing file reference. Please re-upload the file.',
        null,
        core\output\notification::NOTIFY_ERROR
    );
}

function autogenquiz_normalize_fib_items(array $arr): array
{
    foreach ($arr as &$q) {
        $q['type'] = 'fib';
        $q['answers'] = array_values($q['answers'] ?? []);
    }
    return $arr;
}
