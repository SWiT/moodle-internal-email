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

class select_messages_form extends \moodleform {
    //Add elements to form
    public function definition() {
        global $CFG, $DB, $USER;
        $mform =& $this->_form; // Don't forget the underscore!

        $data = $this->_customdata['messagestable'];
        $folderid = $this->_customdata['folderid'];
        $emailid = $this->_customdata['emailid'];

        $mform->addElement('html', $data);

        // Get the options based on if this is a Trash folder or not.

        $options = array( ""        => ""
                        , "read"    => "Mark Read"
                        , "unread"  => "Mark Unread"
                        , "trash"   => "Send to Trash"
                        , "move"    => "Move to..."
                    );
        $mform->addElement('select', 'withselected', get_string('withselected', 'email'), $options);

        $userfolders = array('' => '');
        $params = array('userid' => $USER->id, 'emailid' => $emailid, 'type' => EMAIL_INBOX);
        $inboxfolders = $DB->get_records_menu('email_folder', $params, "name", "id, name");
        $params['type'] = EMAIL_TRASH;
        $trashfolders = $DB->get_records_menu('email_folder', $params, "name", "id, name");
        $userfolders = $userfolders + $inboxfolders + $trashfolders;
        $mform->addElement('select', 'moveto', get_string('moveto', 'email'), $userfolders);

        $mform->addElement('hidden', 'f', $folderid);
        $mform->setType('f', PARAM_INT);

        $mform->addElement('submit', 'gobutton', 'Go');        
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
