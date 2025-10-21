<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_autogenquiz_mod_form extends moodleform_mod {
    function definition() {
        $mform = $this->_form;
        
        // General section
        $mform->addElement('header', 'general', get_string('general', 'form'));
        
        // Name field
        $mform->addElement('text', 'name', get_string('modulename', 'autogenquiz'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        
        // Introduction field
        $this->standard_intro_elements();
        
        // Standard course module elements
        $this->standard_coursemodule_elements();
        
        // Action buttons
        $this->add_action_buttons();
    }
}