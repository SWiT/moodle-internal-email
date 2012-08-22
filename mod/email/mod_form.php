<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * The main newmodule configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package   mod_newmodule
 * @copyright 2011 Oakland University eLearning and Instructional Support
 * @author    Matthew Gary Switlik
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_email_mod_form extends moodleform_mod {

    function definition() {

        global $COURSE, $CFG, $OUTPUT, $DB;
        $mform =& $this->_form;

        $email = $DB->record_exists('email', array('course'=>$this->current->course));
        $update = optional_param('update', 0, PARAM_INT);
        if(!$email || $update>0){
            //Header
            $mform->addElement('header', 'general', get_string('emailname', 'email'));

            // Adding the standard "name" field
            $mform->addElement('text', 'name', get_string('modulename', 'email')." name", array('size'=>'64'));
            if (!empty($CFG->formatstringstriptags)) {
                $mform->setType('name', PARAM_TEXT);
            } else {
                $mform->setType('name', PARAM_CLEAN);
            }
            $mform->addRule('name', null, 'required', null, 'client');
            $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

            // Max file upload size
            if ( isset($form->maxbytes) ) {
                $maxbytes = $mform->maxbytes;
            } else if (empty($COURSE->maxbytes)) {
                $maxbytes = $CFG->maxbytes;
            } else {
                $maxbytes = $COURSE->maxbytes;
            }
            $sizes = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
            $sizes[$maxbytes] = get_string("courseuploadlimit") . " (" . display_size($maxbytes) . ")";
            $sizes[0] = get_string("uploadnotallowed");
            $mform->addElement('select', 'maxbytes', get_string('maxsizeattachment', 'email'), $sizes);

            //-------------------------------------------------------------------------------
            $this->standard_grading_coursemodule_elements();

            $this->standard_coursemodule_elements();
            //-------------------------------------------------------------------------------

            // add standard buttons, common to all modules
            $this->add_action_buttons();
        }else{
            //An email activity is already set for this course.
            $mform->addElement('html', $OUTPUT->error_text(get_string('alreadyenabled', 'email')));
            $mform->addElement($mform->createElement('cancel'));
            $this->standard_hidden_coursemodule_elements();
        }

    }
}

?>
