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

    $baseurl = new moodle_url('/mod/email/compose.php', array('id' => $cm->id));

    $coursecontext = context_course::instance($course->id);
    $userlist = get_enrolled_users($coursecontext, '', 0, 'u.*', 'lastname');
    $tousers = array(0 => get_string('allparticipants', 'email'),);
    foreach ($userlist as $user) {
        $tousers[$user->id] = fullname($user);
    }
        
    $customdata = array('tousers' => $tousers);
    $mform = new \mod_email\compose_message_form($baseurl, $customdata);

    $composehtml .= $mform->render();
    $composehtml .= "<br/>";
    
    return $composehtml;
}