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
