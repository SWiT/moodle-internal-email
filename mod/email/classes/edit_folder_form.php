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

class edit_folder_form extends \moodleform {
    //Add elements to form
    public function definition() {
        global $CFG;
        $mform =& $this->_form; // Don't forget the underscore!
        $mform->addElement('text', 'foldername', get_string('editsubfolder', 'email'));
        $mform->setType('foldername', PARAM_NOTAGS);    //Set type of element
        $this->add_action_buttons();
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
