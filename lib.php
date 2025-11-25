<?php

defined('MOODLE_INTERNAL') || exit;

/**
 * Add a new autogenquiz instance.
 */
function autogenquiz_add_instance($data, $mform = null)
{
    global $DB;

    $data->timecreated = time();
    $data->timemodified = time();

    $data->id = $DB->insert_record('autogenquiz', $data);

    $cm = get_coursemodule_from_id('autogenquiz', $data->cmid);
    $context = context_module::instance($cm->id);
    question_make_default_categories([$context]);

    return $data->id;
}

/**
 * Update an existing autogenquiz instance.
 */
function autogenquiz_update_instance($data, $mform = null)
{
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    return $DB->update_record('autogenquiz', $data);
}

/**
 * Delete an autogenquiz instance.
 */
function autogenquiz_delete_instance($id)
{
    global $DB;

    if (!$autogenquiz = $DB->get_record('autogenquiz', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('autogenquiz', ['id' => $id]);

    return true;
}

/**
 * Supported features.
 */
function autogenquiz_supports($feature)
{
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}

/**
 * Extend settings navigation with autogenquiz settings.
 */
function autogenquiz_extend_settings_navigation(settings_navigation $settingsnav, ?navigation_node $autogenquiznode = null)
{
    global $PAGE;

    if (!$autogenquiznode) {
        return;
    }

    $context = $PAGE->context;

    if (has_capability('mod/autogenquiz:managequestionbank', $context)) {
        // Official question bank entry point
        $url = new moodle_url('/question/edit.php', ['cmid' => $PAGE->cm->id]);

        $autogenquiznode->add(
            get_string('questionbank', 'question'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'questionbank'
        );
    }
}

function autogenquiz_get_question_types()
{
    return core_question\local\bank\helper::get_all_question_types();
}
