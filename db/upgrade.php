<?php

defined('MOODLE_INTERNAL') || exit;

/**
 * Upgrade steps for mod_autogenquiz.
 *
 * @param int $oldversion
 *
 * @return bool
 */
function xmldb_autogenquiz_upgrade($oldversion)
{
    global $DB;

    $dbman = $DB->get_manager();

    // --- 2025102701: Create autogenquiz_files table if missing.
    if ($oldversion < 2025102701) {
        $table = new xmldb_table('autogenquiz_files');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('autogenquizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filesize', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filehash', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'uploaded');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('autogenquizid_fk', XMLDB_KEY_FOREIGN, ['autogenquizid'], 'autogenquiz', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2025102701, 'autogenquiz');
    }

    // --- 2025102702: Add confirmed_text column to autogenquiz_files.
    if ($oldversion < 2025102702) {
        $table = new xmldb_table('autogenquiz_files');
        $field = new xmldb_field('confirmed_text', XMLDB_TYPE_TEXT, null, null, null, null, null);

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2025102702, 'autogenquiz');
    }

    // --- 2025110303: Create autogenquiz_tasks table.
    if ($oldversion < 2025110303) {
        $table = new xmldb_table('autogenquiz_tasks');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('fileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fileid_fk', XMLDB_KEY_FOREIGN, ['fileid'], 'autogenquiz_files', ['id']);
        $table->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2025110303, 'autogenquiz');
    }

    if ($oldversion < 2025111201) {
        $table = new xmldb_table('autogenquiz_generated');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('taskid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('rawtext', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('prompt', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('llm_response', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('parsed_response', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('is_approved', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('approved_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('approved_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('imported_to_bank', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('taskid_fk', XMLDB_KEY_FOREIGN, ['taskid'], 'autogenquiz_tasks', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2025111201, 'autogenquiz');
    }

    return true;
}
