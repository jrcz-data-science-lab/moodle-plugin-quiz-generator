<?php

defined('MOODLE_INTERNAL') || exit;

require_once $CFG->dirroot.'/course/moodleform_mod.php';

// The class name format is fixed: mod_[pluginname]_mod_form. Defines the form class for your module.
class mod_autogenquiz_mod_form extends moodleform_mod
{
    public function definition()
    {
        $mform = $this->_form;

        // General section: Creates a header named General. All Moodle activity forms start with this section.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Name field
        $mform->addElement('text', 'name', get_string('modulename', 'autogenquiz'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Introduction field: Adds Moodle’s built-in fields: “Description” text area and “Display description on course page” checkbox
        $this->standard_intro_elements();

        // Standard course module elements
        $this->standard_coursemodule_elements();

        // Action buttons
        $this->add_action_buttons();
    }
}
