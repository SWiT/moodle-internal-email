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
 * Displays the folder of internal email messages or a single internal email message.
 *
 *
 * @package    mod_email
 * @copyright  2016 Oakland University
 * @author     Matthew SWitlik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$f = optional_param('f', 0, PARAM_INT); // folder ID

if ($id) {
    $cm     = get_coursemodule_from_id('email', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $email  = $DB->get_record('email', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    print_error('errormissingid','email');
}

require_login($course, true, $cm);

// Print the page header.

$PAGE->set_url('/mod/email/folders.php', array('id' => $cm->id));
$PAGE->set_title(format_string($course->shortname.": ".$email->name." Folder Management"));
$PAGE->set_heading(format_string($course->fullname));

$PAGE->navbar->add("Compose New Message");

$composehtml = email_get_composehtml($cm, $course, $email);

// Output starts here.
echo $OUTPUT->header();

// View message.
echo $OUTPUT->container_start("emailfoldermanagement");
    echo $OUTPUT->heading($course->shortname . ": Compose New Message"). "<hr/>";
    $backurl = new moodle_url("view.php?id=".$cm->id);
    echo $OUTPUT->container($OUTPUT->action_link($backurl, "Back"), "menulink");

    echo $composehtml;

    echo $OUTPUT->container($OUTPUT->action_link($backurl, "Back"), "menulink");
echo $OUTPUT->container_end();



// Finish the page.
echo $OUTPUT->footer();

/**
 * Return the manage folders interface html.
 *
 * @package mod_email
 *
 */
function email_get_composehtml($cm, $course, $email) {
    global $DB, $USER, $OUTPUT ;
    $composehtml = "";

    // Get a list of all users in the course.
    $coursecontext = context_course::instance($course->id);
    $userlist = get_enrolled_users($coursecontext, '', 0, 'u.*', 'lastname');
    $contacts = array(0 => get_string('allparticipants', 'email'),);
    foreach ($userlist as $user) {
        $contacts[$user->id] = fullname($user);
    }

    // Determine if new message, reply, reply all, or forward.

    
    $composeurl = new moodle_url('/mod/email/compose.php', array('id' => $cm->id));
    $mform = new \mod_email\compose_message_form($composeurl, array('contacts' => $contacts));

    if ($mform->is_cancelled()) {
        // The form was cancelled.
        $viewfoldersurl = new moodle_url('/mod/email/view.php', array('id' => $cm->id));
        redirect($viewfoldersurl);
    } else if ($formdata = $mform->get_data()) {

        // The form was submitted with data.

        // Add the new message.
        $message = new stdClass();
        $message->emailid     = $email->id;
        $message->timecreated = time();
        $message->subject     = $formdata->subject;
        $message->body        = $formdata->body['text'];
        $message->bodyformat  = $formdata->body['format'];;
        $message->bodytrust   = 0;
        $message->status      = 'sent';
        $message->timesent    = time();
        $messageid = $DB->insert_record('email_message', $message);

        // Add the message recipients.
        foreach ($formdata->to as $touser) {
            $messageusers = new stdClass();
            $messageusers->messageid = $messageid;
            $messageusers->type = 'to';
            $messageusers->userid = $touser;
            $folder = email_get_users_inbox($touser, $email->id, $course->id);
            $messageusers->folderid = $folder->id;
            $messageusers->viewed = 0;
            $messageusers->timeviewed = 0;
            $messageusers->deleted = 0;
            $DB->insert_record('email_message_users', $messageusers);
        }

        // Return to the users inbox
        $viewfoldersurl = new moodle_url('/mod/email/view.php', array('id' => $cm->id));
        redirect($viewfoldersurl);

    } else {

        // Set the default data?
        $composeform = new stdClass();
        $composeform->to = array();
        $composeform->subject = '';
        $composeform->body =    '';
        $composeform->bodyformat = editors_get_preferred_format();
        $composeform->bodytrust  = 0;
        //$composeform = file_prepare_standard_editor($composeform, 'body', $attachmentoptions, $this->modcontext, 'mod_email', 'attachments', $this->subwiki->id);
            
        $mform->set_data($composeform);

        // Display the form.
        $composehtml .= $mform->render();
    }

    
    $composehtml .= "<br/>";
    
    return $composehtml;
}



function email_get_form_options($email, $context){
    $bodyoptions = array('subdirs'=>0
                    , 'maxbytes'=>$email->maxbytes
                    , 'maxfiles'=>50
                    , 'changeformat'=>0
                    , 'context'=>$context
                    , 'noclean'=>0
                    , 'trusttext'=>true
                    , 'enable_filemanagement'=>true
                    );

    $attachmentoptions = array('subdirs'=>0
                            , 'maxfiles'=>50
                            , 'maxbytes'=>$email->maxbytes
                            , 'context'=>$context
                            );

    return array('attachmentsoptions'=>$attachmentoptions, 'bodyoptions'=>$bodyoptions);
}