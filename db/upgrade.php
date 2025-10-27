<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_autogenquiz_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Add autogenquiz_files table if missing (from prior upgrade).
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

    // Add confirmed_text column.
    if ($oldversion < 2025102702) {
        $table = new xmldb_table('autogenquiz_files');
        $field = new xmldb_field('confirmed_text', XMLDB_TYPE_TEXT, null, null, null, null, null);

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2025102702, 'autogenquiz');
    }

    return true;
}
