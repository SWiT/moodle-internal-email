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

$f = optional_param('f', 0, PARAM_INT); // folder ID
$p = optional_param('p', 0, PARAM_INT); // page number
$m = optional_param('m', 0, PARAM_INT); // message ID

if ($m && $f) {
    $folder = $DB->get_record('email_folder', array('id' => $f), '*', MUST_EXIST);
    $message = $DB->get_record('email_message', array('id' => $m), '*', MUST_EXIST);
    $params = array('messageid' => $message->id, 'folderid' => $folder->id, 'userid' => $USER->id);
    $messageuser = $DB->get_record('email_message_users', $params, '*', MUST_EXIST);
    $email = $DB->get_record('email', array('id' => $folder->emailid), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $email->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('email', $email->id, $course->id, false, MUST_EXIST);
} else {
    print_error('errormessageids','email');
}

require_login($course, true, $cm);

$event = \mod_email\event\course_module_viewed::create(array(
        'objectid' => $PAGE->cm->instance,
        'context' => $PAGE->context,
    ));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $email);
$event->trigger();

// Print the page header.

$PAGE->set_url('/mod/email/message.php', array('m' => $message->id));
$PAGE->set_title(format_string($course->shortname.": ".$email->name));
$PAGE->set_heading(format_string($course->fullname));

$folderurl = new moodle_url('/mod/email/view.php', array('f' => $folder->id));
$PAGE->navbar->add($folder->name, $folderurl);

// Output starts here.
echo $OUTPUT->header();
   
// View message.
echo $OUTPUT->container_start("emailmessage");
echo $OUTPUT->heading($course->shortname . ": " .$folder->name. ": " .$message->subject)."<hr/>";

email_menu_messageactions($folder->id, $p, $message->id);

echo email_messageelement(get_string('from', 'email'), email_get_sender($message->id));
echo email_messageelement(get_string('to', 'email'), email_get_recipients($message->id));
echo email_messageelement(get_string('sent', 'email'), userdate($message->timesent));

echo email_messageelement(get_string('subject', 'email'), $message->subject);
echo email_messageelement(get_string('body', 'email'), $message->body);


if ($previd = email_get_prev_messageid($folder, $message->id)) {
    $baseurl = new moodle_url("message.php", array('f' => $folder->id, 'p' => $p, 'm' => $previd));
    echo $OUTPUT->container($OUTPUT->action_link($baseurl, "Previous"), "menulink");
}

if ($nextid = email_get_next_messageid($folder, $message->id)) {
    $baseurl = new moodle_url("message.php", array('f' => $folder->id, 'p' => $p, 'm' => $nextid));
    echo $OUTPUT->container($OUTPUT->action_link($baseurl, "Next"), "menulink");
}

echo $OUTPUT->container_end();

// Finish the page.
echo $OUTPUT->footer();

// Mark the message as viewed.
if ($messageuser->viewed == 0 || $messageuser->timeviewed == 0) {
    $messageuser->viewed = 1;
    $messageuser->timeviewed = time();
    $DB->update_record('email_message_users', $messageuser);
}
