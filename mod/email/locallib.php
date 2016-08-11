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
 * Internal library of functions for module email
 *
 * All the email specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_email
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Return the text as bold if it has been viewed.
 *
 * @package mod_email
 * 
 * @param string $text the text to make bold.
 * @param bool $viewed make the text bold if false.
 */
function email_makebold($text, $viewed) {
    if (!$viewed) {
        $ret = "<strong>".$text."</strong>";
    } else {
        $ret = $text;
    }
    return $ret;
}

/**
 * Return the name of the message sender.
 *
 * @package mod_email
 * 
 * @param int $messageid to return the recipients for.
 */
function email_get_sender($messageid) {
    global $DB, $CFG;
    $name = "";
    $emu = $DB->get_record("email_message_users", array('messageid' => $messageid, 'type' => 'from'), '*', MUST_EXIST);
    $user = $DB->get_record('user',array("id" => $emu->userid));
    return fullname($user);
}

/**
 * Return the names of the message recipients.
 *
 * @package mod_email
 * 
 * @param int $messageid to return the recipients for.
 */
function email_get_recipients($messageid) {
    global $DB, $CFG;
    $names = "";
    $emus = $DB->get_records("email_message_users", array('messageid' => $messageid, 'type'=>'to'));
    foreach ($emus as $emu) {
        $user = $DB->get_record('user',array("id" => $emu->userid));
        $names .= fullname($user).", ";
    }
    if (!empty($names)) {
        $names = substr($names, 0, -2);
    }
    return $names;
}

/**
 * Return the go to folder select box menu item.
 *
 * @package mod_email
 * 
 */
function email_menu_gotofolder($url, $emailid, $folderid) {
    global $OUTPUT;
    
    $usersfolders = email_menu_get_usersfolders($emailid);
    $options = array();
    foreach ($usersfolders as $usersfolder) {
        $name = '';
        for ($i=0; $i < $usersfolder->depth; $i++){
            $name .= " - ";
        }
        $name .= $usersfolder->name;
        $options[$usersfolder->id] = $name;
    }
    
    $select = new single_select($url, 'f', $options, $folderid, null, "gotofolderform");
    $select->set_label(get_string('goto', 'email'));
    $select->class = "emailmenuoption";
    return $OUTPUT->render($select);
}

function email_menu_movetofolders($url, $emailid, $folderid) {
    global $OUTPUT;

    $usersfolders = email_menu_get_usersfolders($emailid);
    $options = array();
    foreach ($usersfolders as $usersfolder) {
        $name = '';
        for ($i=0; $i < $usersfolder->depth; $i++){
            $name .= " - ";
        }
        $name .= $usersfolder->name;
        $options[$usersfolder->id] = $name;
    }

    $select = new single_select($url, 'f', $options, $folderid, null, "gotofolderform");
    $select->set_label(get_string('goto', 'email'));
    $select->class = "emailmenuoption";
    return $OUTPUT->render($select);
}

function email_menu_get_usersfolders($emailid, $parentfolderid = 0, $depth = 0) {
    global $DB, $USER;

    $params = array('userid' => $USER->id, 'emailid' => $emailid, 'deleted' => 0, 'parentfolderid' => $parentfolderid);
    $folders = array_values($DB->get_records('email_folder', $params, "name", "id, name"));

    $pos = 0;
    foreach ($folders as $k => $folder) {
        $folder->depth = $depth;
        $subfolders = email_menu_get_usersfolders($emailid, $folder->id, ($depth + 1));
        $count = count($subfolders);
        $pos++;
        if ($count > 0) {
            array_splice($folders, $pos, 0, $subfolders);
            $pos += $count;
        }
    }
    
    return $folders;
}

/**
 * Return the messages per page menu item.
 *
 * @package mod_email
 * 
 */
function email_menu_perpage($url, $perpage) {
    // Display the number of messages to display per page option.
    global $OUTPUT;
    $options = array(5 => 5, 10 => 10, 25 => 25, 50 => 50);
    $select = new single_select($url, 'perpage', $options, $perpage, null, "perpageform");
    $select->set_label(get_string('displayperpage', 'email'));
    $select->class = "emailmenuoption";
    return $OUTPUT->render($select);
}

/**
 * Return the menu item for actions you can perform with selected messages.
 *
 * @package mod_email
 * 
 */
function email_menu_withselected($url, $withselected) {
    global $OUTPUT;
    $options = array("" => "", "read" => "Mark Read", "unread" => "Mark Unread", "trash" => "Send to Trash");

    $select = new single_select($url, 'withselected', $options, $withselected, null, "withselectedform");
    $select->set_label(get_string('withselected', 'email'));
    $select->class = "emailmenuoption";
    
    return $OUTPUT->render($select);
}

/**
 * Return the menu item for moving selected messages.
 *
 * @package mod_email
 * 
 */
function email_menu_moveselected($url, $emailid, $folderid) {
    global $DB, $USER, $OUTPUT;
    $userfolders = array('' => '');
    $params = array('userid' => $USER->id, 'emailid' => $emailid, 'type' => EMAIL_INBOX);
    $inboxfolders = $DB->get_records_menu('email_folder', $params, "name", "id, name");
    $params['type'] = EMAIL_TRASH;
    $trashfolders = $DB->get_records_menu('email_folder', $params, "name", "id, name");
    $userfolders = $userfolders + $inboxfolders + $trashfolders;
    $select = new single_select($url, 'gotofolder', $userfolders, $folderid, null, "gotofolderform");
    $select->set_label(get_string('moveselected', 'email'));
    $select->class = "emailmenuoption";
    return $OUTPUT->render($select);
}

/**
 * Display a row of message information.
 *
 * @package mod_email
 * 
 */
function email_messageelement($label, $content) {
    global $OUTPUT;
    return $OUTPUT->container("<div class='mitemtitle'><label>$label</label></div><div class='melement'>$content</div>", "mitem");
}

/**
 * Display the action menu for the message view.
 *
 * @package mod_email
 * 
 */
function email_menu_messageactions($folderid, $page, $messageid) {
    global $OUTPUT;
    
    $baseurl = new moodle_url("view.php", array('f' => $folderid, 'page' => $page));
    echo $OUTPUT->container($OUTPUT->action_link($baseurl, "Back"), "menulink");

    $baseurl = new moodle_url("message.php", array('f' => $folderid, 'p' => $page));
    $baseurl->param('m', $messageid);
    $baseurl->param('action', 'reply');
    echo $OUTPUT->container($OUTPUT->action_link($baseurl, "Reply"), "menulink");
    $baseurl->param('action', 'replyall');
    echo $OUTPUT->container($OUTPUT->action_link($baseurl, "Replay to all"), "menulink");
    $baseurl->param('action', 'forward');
    echo $OUTPUT->container($OUTPUT->action_link($baseurl, "Forward"), "menulink");
}


function email_get_prev_messageid($folder, $currentmessageid) {
    global $SESSION;

    if (!isset($SESSION->email_folder_messageids) || !isset($SESSION->email_folder_messageids[$folder->id])) {
        // Load the table id order into the session.
        $perpage = email_get_perpage();
        email_get_folder_table_html($folder, $perpage);
    }

    $messageids = $SESSION->email_folder_messageids[$folder->id];
    foreach($messageids as $key => $m) {
        if ($m == $currentmessageid) {
            if ($key <= 0) {
                return 0;
            }
            return $messageids[$key - 1];
        }
    }
}

function email_get_next_messageid($folder, $currentmessageid) {
    global $SESSION;

    if (!isset($SESSION->email_folder_messageids) || !isset($SESSION->email_folder_messageids[$folder->id])) {
        // Load the table id order into the session.
        $perpage = email_get_perpage();
        email_get_folder_table_html($folder, $perpage);
    }

    $messageids = $SESSION->email_folder_messageids[$folder->id];
    $maxindex = count($messageids) - 1;
    foreach($messageids as $key => $m) {
        if ($m == $currentmessageid) {
            if ($key >= $maxindex) {
                return 0;
            }
            return $messageids[$key + 1];
        }
    }
}


/**
 * Return the manage folders interface html.
 *
 * @package mod_email
 *
 */
function email_get_managefolders($emailid, $parentfolderid, $baseurl, $f, $action) {
    global $DB, $USER, $OUTPUT ;
    $foldershtml = "";

    $folders = $DB->get_records("email_folder", array('emailid' => $emailid, 'userid' => $USER->id, 'parentfolderid' => $parentfolderid, 'deleted' => 0));
    foreach ($folders as $folder) {
        // Skip the Draft folder.
        if ($folder->type == EMAIL_DRAFT) {
            continue;
        }

        $baseurl->param('f', $folder->id);
        $foldershtml .= $OUTPUT->container_start("emailfoldersitem");
        $content = "";
        
        // If not editting or folder is not selected.
         if (($f == $folder->id) && $action == "edit")  {
            $baseurl->param('action', $action);
            $mform = new \mod_email\edit_folder_form($baseurl);
            if (!$mform->is_cancelled()) {
                if ($fromform = $mform->get_data()) {
                    //var_dump($fromform);
                    if (!empty(trim($fromform->foldername))) {
                        $folder->name = trim($fromform->foldername);
                        $DB->update_record("email_folder", $folder);
                    }
                    $baseurl->param('f', 0);
                    $baseurl->param('action', '');
                    redirect($baseurl);
                } else {
                    $mform->set_data(array('foldername' => $folder->name));
                }
                $content = $mform->render();
            } else {
                $content = email_outputfolderrow($folder, $baseurl);
            }
        } else if ($f == $folder->id && $action == "delete") {
            $confirm = optional_param('confirm', 0, PARAM_INT); // Confirmation of the delete action.
            if ($confirm == 0) {
                if ($folder->parenttype == EMAIL_TRASH) {
                    $content = $OUTPUT->container("Are you sure you want to permanently delete <strong>" . $folder->name . "</strong> and all messages and folders with in?");
                } else {
                    $content = $OUTPUT->container("Are you sure you want to move <strong>" . $folder->name . "</strong> to the Trash?");
                }
                $baseurl->param('confirm', '1');
                $content .= $OUTPUT->single_button($baseurl, "Delete folder", "get", array('class' => 'emailmenuoption'));
                $baseurl->param('confirm', '0');
                $baseurl->param('f', 0);
                $baseurl->param('action', '');
                $content .= $OUTPUT->single_button($baseurl, "Cancel", "get", array('class' => 'emailmenuoption'));
            } else {
                // Delete the folder or move it to the trash.
                if ($folder->parenttype == EMAIL_TRASH) {
                    // Mark the folder, any subfolders, and messages as permanently deleted.
                    email_delete_folder($folder->id);

                } else {
                    // Move to Trash.
                    $params = array('emailid' => $emailid, 'userid' => $USER->id, 'parentfolderid' => 0, 'type' => EMAIL_TRASH);
                    $trashfolder = $DB->get_record("email_folder", $params);
                    $folder->parenttype = EMAIL_TRASH;
                    $folder->parentfolderid = $trashfolder->id;
                    $DB->update_record("email_folder", $folder);
                }
                $baseurl->param('f', 0);
                $baseurl->param('action', '');
                redirect($baseurl);
            }
        } else {
            $content = email_outputfolderrow($folder, $baseurl);
        }
        
        
        $foldershtml .= $content;
        
        
        // Output any subfolders.
        $foldershtml .= email_get_managefolders($emailid, $folder->id, $baseurl, $f, $action);

        // Display form if this folderid is selected.
        if ($f == $folder->id) {
            $foldershtml .= $OUTPUT->container_start("emailfoldersitem");
            $baseurl->param('f', $f);
            $baseurl->param('action', $action);

            if ($action == "add") {
                $baseurl->param('action', $action);
                $mform = new \mod_email\add_folder_form($baseurl);
                if (!$mform->is_cancelled()) {
                    if ($fromform = $mform->get_data()) {
                        //var_dump($fromform);
                        if (!empty(trim($fromform->foldername))) {
                            $data = new stdClass;
                            $data->name = trim($fromform->foldername);
                            $data->userid = $USER->id;
                            $data->parentfolderid = $folder->id;
                            $data->parentfoldertype = $folder->parentfoldertype;
                            $data->emailid = $emailid;
                            $DB->insert_record("email_folder", $data);
                        }
                        $baseurl->param('f', 0);
                        redirect($baseurl);
                    }
                    $foldershtml .= $mform->render();
                }
            }
            $foldershtml .= $OUTPUT->container_end();
        }

        $foldershtml .= $OUTPUT->container_end();
    }
    return $foldershtml;
}


function email_outputfolderrow($folder, $baseurl) {
    global $OUTPUT;

    $content = "<label>" . $folder->name . "</label>";

    // Edit this folder.
    $content .= $OUTPUT->container_start("folderoptions");
    $baseurl->param('action', 'add');
    $content .= $OUTPUT->action_icon($baseurl, new pix_icon('t/add', get_string('add'), '', array('class' => 'iconsmall')));
    $content .= $OUTPUT->container_end();

    if ($folder->type == EMAIL_FOLDER) {
        // Edit a folder.
        $baseurl->param('action', 'edit');
        $content .= $OUTPUT->action_icon($baseurl, new pix_icon('t/edit', get_string('settings'), '', array('class' => 'iconsmall')));
        // Delete a folder.
        $baseurl->param('action', 'delete');
        $content .= $OUTPUT->action_icon($baseurl, new pix_icon('t/delete', get_string('delete'), '', array('class' => 'iconsmall')));
    }
    return $content;
}


function email_delete_folder($folderid) {
    global $DB, $USER;
    // Mark this folder deleted.
    $folder = $DB->get_record("email_folder", array('id' => $folderid));
    $folder->deleted = 1;
    $DB->update_record("email_folder", $folder);

    // Mark all messages for this user in this folder as deleted.
    $usermessages = $DB->get_records("email_message_users", array('userid' => $USER->id, 'folderid' => $folder->id));
    foreach ($usermessages as $usermessage) {
        $usermessage->deleted = 1;
        $DB->update_record("email_message_users", $usermessage);
    }

    // Delete all subfolders.
    $subfolders = $DB->get_records("email_folder", array('parentfolderid' => $folder->id));
    foreach ($subfolders as $subfolder) {
        email_delete_folder($subfolder->id);
    }
}


function email_get_folder_table_html($folder, $perpage) {
    global $PAGE, $DB, $SESSION;

    // Create the table of folders messages.
    $messagestable = new \mod_email\folder_messages_table($folder, $perpage);

    // Render the message table.
    $renderer = $PAGE->get_renderer("mod_email");
    $tablehtml = $renderer->render_folder_messages_table($messagestable);

    // Get the ids of messages in the folder for next and previous links when viewing the message.
    $sql = "SELECT EM.id";
    $sql .= " FROM " . $messagestable->sql->from;
    $sql .= " WHERE " . $messagestable->sql->where;
    $sql .= " ORDER BY " . $messagestable->get_sql_sort();
    $data = $DB->get_records_sql($sql, $messagestable->sql->params);

    // Count the messages in the folder.
    $messagecount = count($data);

    // Store the message ids in the session.
    $messageids = array();
    foreach ($data as $row) {
        $messageids[] = $row->id;
    }
    $SESSION->email_folder_messageids[$folder->id] = $messageids;

    return array($tablehtml, $messagecount);
}

function email_get_perpage() {
    $perpage = optional_param('perpage', 0, PARAM_INT); // Number of messages to display.
    if ($perpage === 0) {
        $perpage = get_user_preferences("email_perpage", EMAIL_DEFAULT_PERPAGE);
    } else {
        set_user_preference("email_perpage", $perpage);
    }
    return $perpage;
}

function email_get_users_inbox($userid, $emailid) {
    global $DB;
    $params = array('userid' => $userid, 'emailid' => $emailid, 'type' => EMAIL_INBOX);
    $folder  = $DB->get_record('email_folder', $params);
    return $folder;
}

function email_create_default_folders($userid, $emailid) {
    global $DB;

    $folder = new stdClass();
    $folder->userid = $userid;
    $folder->parentfolderid = EMAIL_ROOT_FOLDER;
    $folder->emailid = $emailid;
    $folder->deleted = 0;

    $folder->name = "Inbox";
    $folder->type = EMAIL_INBOX;
    $folder->parenttype = EMAIL_INBOX;
    $DB->insert_record('email_folder', $folder);

    $folder->name = "Draft";
    $folder->type = EMAIL_DRAFT;
    $folder->parenttype = EMAIL_DRAFT;
    $DB->insert_record('email_folder', $folder);

    $folder->name = "Sent";
    $folder->type = EMAIL_SENT;
    $folder->parenttype = EMAIL_SENT;
    $DB->insert_record('email_folder', $folder);

    $folder->name = "Trash";
    $folder->type = EMAIL_TRASH;
    $folder->parenttype = EMAIL_TRASH;
    $DB->insert_record('email_folder', $folder);
}

/**
 * This functions return if mail has attachments
 *
 * @param object $mail Mail
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_has_attachments($message) {

    $cm = get_coursemodule_from_instance('email', $message->emailid);
    $context = context_module::instance($cm->id);

    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_email', 'attachments', $message->id);
    if(count($files)>1){
        return true;
    }

    $files = $fs->get_area_files($context->id, 'mod_email', 'body', $message->id);
    return (count($files)>1);
}