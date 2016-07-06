<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of newPHPClass
 *
 * @author switlik
 */
namespace mod_email;

require_once("$CFG->libdir/formslib.php");

class compose_message_form extends \moodleform {
    //Add elements to form
    public function definition() {
        global $CFG;
        $mform =& $this->_form; // Don't forget the underscore!

        $contacts = $this->_customdata['contacts'];

        $options = array(
            'multiple' => true,
            'noselectionstring' => get_string('selectrecipient', 'email'),
        );
        $mform->addElement('autocomplete', 'to', get_string('to', 'email'), $contacts, $options);

        
        $mform->addElement('text', 'subject', get_string('subject', 'email'));
        $mform->setType('subject', PARAM_NOTAGS);    //Set type of element

        $mform->addElement('editor', 'body', get_string("body", "email"));
        $mform->setType('body', PARAM_RAW);

        $mform->addElement('filemanager', 'attachments', get_string('attachments', 'email'));

        $this->add_action_buttons(true, get_string('send', 'email'));
    }
    
    //Custom validation should be added here
    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty(trim($data['foldername']))) {
            $errors['foldername'] = get_string('foldernameempty','email');
        }

        return $errors;
    }
}
