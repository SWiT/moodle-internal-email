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

// Replace email with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(dirname(dirname(__FILE__))).'/lib/tablelib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$f = optional_param('f', 0, PARAM_INT); // folder ID

$perpage = email_get_perpage();

// Switch to a different folder if requested.
$gotofolder = optional_param('gotofolder', 0, PARAM_INT);
if ($gotofolder > 0) {
    $f = $gotofolder;
}

// Get all the email objects.
if ($id) {
    $cm     = get_coursemodule_from_id('email', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $email  = $DB->get_record('email', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($f) {
    require_login();
    $folder  = $DB->get_record('email_folder', array('id' => $f, 'userid' => $USER->id), '*', MUST_EXIST);
    $email  = $DB->get_record('email', array('id' => $folder->emailid), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $email->course), '*', MUST_EXIST);
    $cm     = get_coursemodule_from_instance('email', $email->id, $course->id, false, MUST_EXIST);
} else {
    print_error('errormissingid','email');
}

//Make sure the user is logged in.
require_login($course, true, $cm);

// Handle bulk actions on messages.
$withselected = optional_param('withselected', '', PARAM_TEXT); // What to do with selected messages.
$emuid = optional_param_array('emuid', array(), PARAM_INT);
if (!empty($withselected) && !empty($emuid)) {
    switch($withselected){
        case "read":
            $emu = new stdClass();
            $emu->viewed = 1;
            foreach ($emuid as $v) {
                $emu->id = $v;
                $DB->update_record('email_message_users', $emu);
            }
            break;

        case "unread":
            $emu = new stdClass();
            $emu->viewed = 0;
            foreach ($emuid as $v) {
                $emu->id = $v;
                $DB->update_record('email_message_users', $emu);
            }
            break;

        case "trash":
            // Get trash folder id.
            $params = array('emailid' => $email->id, 'userid' => $USER->id, 'type' => EMAIL_TRASH);
            $trash = $DB->get_record('email_folder', $params, '*', MUST_EXIST);
            // Update the email_message_users record.
            $emu = new stdClass();
            $emu->folderid = $trash->id;
            foreach ($emuid as $v) {
                $emu->id = $v;
                $DB->update_record('email_message_users', $emu);
            }
            break;

        case "delete":
            // Mark the email_message_users record as deleted.
            $emu = new stdClass();
            $emu->deleted = 1;
            foreach ($emuid as $v) {
                $emu->id = $v;
                $DB->update_record('email_message_users', $emu);
            }
            break;

        case "move":
            $moveto = optional_param('moveto', 0, PARAM_INT);
            if ($moveto > 0) {
                $df = $DB->get_record('email_folder', array('id' => $moveto, 'emailid' => $email->id, 'userid' => $USER->id), '*', MUST_EXIST);
                $emu = new stdClass();
                $emu->folderid = $df->id;
                foreach ($emuid as $v) {
                    $emu->id = $v;
                    $DB->update_record('email_message_users', $emu);
                }
            }
            break;
    }
}

$event = \mod_email\event\course_module_viewed::create(array(
        'objectid' => $PAGE->cm->instance,
        'context' => $PAGE->context,
    ));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $email);
$event->trigger();

// Print the page header.

$PAGE->set_url('/mod/email/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($course->shortname.": ".$email->name));
$PAGE->set_heading(format_string($course->fullname));

if (!isset($folder)) {
    // View the users inbox.
    $params = array('userid' => $USER->id, 'emailid' => $email->id, 'type' => EMAIL_INBOX);
    try {
        $folder  = $DB->get_record('email_folder', $params, '*', MUST_EXIST);
    } catch (Exception $e) {
        // Error the inbox for the user was not found.
        $url = new moodle_url("/user/index.php?id=".$course->id); // Course participants list.
        print_error('errornofolder', 'email', $url);
    }
}

$folderurl = new moodle_url('/mod/email/view.php', array('f' => $folder->id));
$PAGE->navbar->add($folder->name, $folderurl);

// Output starts here.
echo $OUTPUT->header();

// View folder.
$baseurl = new moodle_url('/mod/email/view.php');
$folderurl = new moodle_url('/mod/email/view.php', array('f' => $folder->id));

echo $OUTPUT->heading($course->shortname . ": " .$folder->name);

echo "<hr/>";

list($tablehtml, $messagecount) = email_get_folder_table_html($folder, $perpage);

// Display the folder's menu.
echo $OUTPUT->container_start("emailfoldermenu");
    echo $OUTPUT->container($messagecount. " messages", "emailmenuoption");

    $composeurl = new moodle_url('/mod/email/compose.php', array('id' => $cm->id));
    echo $OUTPUT->single_button($composeurl, "Compose Message", "get", array('class' => 'emailmenuoption'));

    echo email_menu_gotofolder($baseurl, $email->id, $folder->id);

    $manfoldersurl = new moodle_url('/mod/email/folders.php', array('id' => $cm->id));
    echo $OUTPUT->single_button($manfoldersurl, "Manage Folders", "get", array('class' => 'emailmenuoption'));

    echo email_menu_perpage($folderurl, $perpage);
echo $OUTPUT->container_end();

// Add the rendered table to the form and display the form.
$params = array('messagestable' => $tablehtml, 'emailid' => $email->id, 'folderid' => $folder->id);
$mform = new \mod_email\select_messages_form(null, $params);
echo $mform->render();

// Finish the page.
echo $OUTPUT->footer();
