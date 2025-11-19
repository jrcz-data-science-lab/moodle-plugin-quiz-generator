<?php
defined('MOODLE_INTERNAL') || die(); // Security check. Stops the script if it is accessed directly. This ensures the file only runs inside Moodle.

$plugin->component = 'mod_autogenquiz'; // Defines the plugin’s full component name. For activity modules, it always starts with mod_.
$plugin->version   = 2025111201; // Moodle uses this number to run upgrade steps. If the database structure changes, this number should be incremented.
$plugin->requires  = 2022041900; // Moodle 4.0+ : If someone tries to install the plugin on an older Moodle, Moodle will stop and show an error.
$plugin->maturity  = MATURITY_ALPHA; // Defines the development stage of the plugin.
$plugin->release   = '0.3'; // This is NOT used for upgrades — only for display.