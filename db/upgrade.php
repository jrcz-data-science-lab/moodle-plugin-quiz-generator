<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_autogenquiz_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Add new table autogenquiz_files if it doesn't exist.
    if ($oldversion < 2025102701) {

        // Define table autogenquiz_files.
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

        // Create the table if it doesn’t exist.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Savepoint reached.
        upgrade_mod_savepoint(true, 2025102701, 'autogenquiz');
    }

    return true;
}
