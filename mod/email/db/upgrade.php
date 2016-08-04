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
 * This file keeps track of upgrades to the email module
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations. The upgrade
 * function in this file will attempt to perform all the necessary actions to
 * upgrade your older installation to the current version. If there's something
 * it cannot do itself, it will tell you what you need to do.  The commands in
 * here will all be database-neutral, using the functions defined in DLL libraries.
 *
 * @package    mod_email
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A helper function for getting the root parent folder from the old DB schema
 *
 * @param int $folderid
 * @return object of the folders parent.
 */
function email_get_root_parent_folder($folderid) {
    global $DB;
    if ($subfolder = $DB->get_record('email_subfolder', array('folderchildid' => $folderid))) {
        $rf = email_get_root_parent_folder($subfolder->folderparentid);
    } else {
        // Root folder
        $rf = $DB->get_record('email_folder', array('id' => $folderid));
    }
    return $rf;
}

/**
 * Execute email upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_email_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2011100601) {
        update_capabilities();
        upgrade_mod_savepoint(true, 2011100601, 'email');
    }

    if ($oldversion < 2011100602) {
        update_capabilities();
        upgrade_mod_savepoint(true, 2011100602, 'email');
    }

    if ($oldversion < 2014061700) {
        update_capabilities();
        upgrade_mod_savepoint(true, 2014061700, 'email');
    }

    if ($oldversion < 2015091600) {
        // Create the new tables.
        $table = new xmldb_table('email_message');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('emailid', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
            $table->add_field('subject', XMLDB_TYPE_CHAR, '255', null, null, null, '');
            $table->add_field('body', XMLDB_TYPE_TEXT, 'large', null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '5', null, null, null, '');
            $table->add_field('timesent', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $dbman->create_table($table);
        }
        $table = new xmldb_table('email_message_users');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('messageid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
            $table->add_field('type', XMLDB_TYPE_CHAR, '4', null, null, null, '');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
            $table->add_field('folderid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
            $table->add_field('viewed', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
            $table->add_field('timeviewed', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
            $table->add_field('deleted', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $dbman->create_table($table);
        }

        // Add columns email_folder.userid, email_folder.isdefault
        $table = new xmldb_table('email_folder');
        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('parentfolderid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'isparenttype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('emailid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Remove the useless columns.
        $table = new xmldb_table('email');
        $field = new xmldb_field('active');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $table = new xmldb_table('email_folder');
        $field = new xmldb_field('timecreated');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2015091600, 'email');
    }

    if($oldversion < 2015091700){
        // Migrate the mail data to the new data structure.

        // First remove the duplicate email_foldermail records.
        $sql = "SELECT MIN(FM.id) as id, FM.mailid, FM.folderid, COUNT(FM.id)
                FROM mdl_email_foldermail FM
                GROUP BY FM.mailid, FM.folderid
                HAVING COUNT(FM.mailid) > 1
                ORDER BY FM.mailid
                ;";
        $data = $DB->get_records_sql($sql);
        foreach($data as $d) {
            //Delete all but the first foldermail record
            $where = 'id != '.$d->id.' AND mailid = '.$d->mailid.' AND folderid = '.$d->folderid;
            $DB->delete_records_select('email_foldermail', $where);
        }


        //Get all the mail records (messages).
        $mailrecords = $DB->get_recordset('email_mail');
        foreach ($mailrecords as $mail) {
            $message = new stdClass();
            $message->id = $mail->id;
            
            // Get the mail message's emailid.
            $sql = "SELECT M.id, E.id as emailid
                    FROM {email_mail} M
                    JOIN {email_account} A
                      ON A.id = M.accountid
                    JOIN {email} E
                      ON E.id = A.emailid
                    WHERE M.id = ?";
            if($data = $DB->get_record_sql($sql, array($mail->id))) {
                $message->emailid   = $data->emailid;
            } else {
                // If the emailid was not found skip this mail message.
                continue;
            }
            $message->subject       = $mail->subject;
            $message->body          = $mail->body;
            $message->timecreated   = $mail->timecreated;
            
            // Get the sender's message folder id and name.
            $sql = "SELECT F.*
                    FROM {email_mail} M
                    JOIN {email_account} A
                      ON A.id = M.accountid
                    JOIN {email} E
                      ON E.id = A.emailid
                    JOIN {email_foldermail} FM
                      ON FM.mailid = M.id
                    JOIN {email_folder} F
                      ON F.id = FM.folderid
                      AND F.accountid = M.accountid
                    WHERE M.id = ?
                    ";
            $folder = new stdClass();
            $folders = $DB->get_records_sql($sql, array($mail->id));
            // A user may have sent a message to themselves. Get the sent folder or the subfolder of it.
            foreach($folders as $f){
                if (is_null($f->isparenttype)) {
                    // Not a parent type
                    // Get the root parent
                    $rf = email_get_root_parent_folder($f->id);
                    if ($rf->isparenttype == 'sendbox' || $f->isparenttype == 'draft' || $f->isparenttype == 'trash') {
                        $folder = $f;
                        break;
                    }
                } else {
                    // Parent Type
                    if ($f->isparenttype == 'sendbox' || $f->isparenttype == 'draft' || $f->isparenttype == 'trash') {
                        $folder = $f;
                        break;
                    }
                }
            }
            if (empty($folder) && count($folders) == 1) {
                // The sender moved the sent message to a subfolder of the INBOX or other root folder.
                $folder = array_shift($folders); 
            }


            if ($folder->name == 'Draft') {
                $message->status = 'draft';
                $message->timesent = 0;
            } else {
                $message->status = 'sent';
                $message->timesent = $mail->timecreated;
            }
            
            $m = $DB->get_record('email_message', array('id' => $message->id));
            if (empty($m)) {
                // Insert the new message.
                $DB->insert_record_raw('email_message', $message);
            }
            

            // Add the sender as a messageuser.
            if ($account = $DB->get_record('email_account', array("id" => $mail->accountid))) {
                $messageuser = new stdClass();
                $messageuser->messageid = $mail->id;
                $messageuser->type  = "from";
                $messageuser->userid = $account->userid;

                $messageuser->folderid = $folder->id;
                
                $messageuser->viewed = 1;
                $messageuser->timeviewed = $mail->timecreated;
                $messageuser->deleted = 0;
                
                //Insert the record if it doesn't exist already.
                $params = array('messageid' => $messageuser->messageid
                                , 'type' => $messageuser->type
                                , 'userid' => $messageuser->userid
                                , 'folderid' => $messageuser->folderid
                                );
                $mu = $DB->get_records('email_message_users', $params);
                if (empty($mu)) {
                    $DB->insert_record_raw('email_message_users', $messageuser);
                }
            } else {
                print_error("line: ".__LINE__." accountid: ".$mail->accountid." NOT FOUND.<br/>\n");
            }

            // Add any "to", "cc", or "bcc" messageusers
            if ($recipients = $DB->get_records('email_send', array("mailid" => $mail->id))) {
                foreach ($recipients as $recipient) {
                    if ($account = $DB->get_record('email_account', array("id" => $recipient->accountid))) {
                        $messageuser = new stdClass();
                        $messageuser->messageid = $mail->id;
                        $messageuser->type  = $recipient->type;
                        $messageuser->userid = $account->userid;

                        // Get the folderid of the recipients folder.
                        $sql = "SELECT M.id, F.accountid, F.id as folderid
                                FROM {email_mail} M
                                JOIN {email_foldermail} FM
                                  ON FM.mailid = M.id
                                JOIN {email_folder} F
                                  ON F.id = FM.folderid
                                  AND F.isparenttype != 'sendbox'
                                WHERE M.id = ?
                                  AND F.accountid = ?";

                        // A user may have sent a message to themselves. Get the inbox folder or the subfolder of it.

                        if ($message->timesent > 0 && $data = $DB->get_record_sql($sql, array($mail->id, $recipient->accountid))) {
                            $messageuser->folderid = $data->folderid;
                        } else if ($message->timesent == 0) {
                            $messageuser->folderid = 0;
                        } else {
                            $messageuser->folderid = -1;
                        }
                        $messageuser->viewed = $recipient->readed;
                        if ($messageuser->viewed == 1) {
                            $messageuser->timeviewed = $mail->timecreated;
                        } else {
                            $messageuser->timeviewed = 0;
                        }
                        $messageuser->deleted = 0;
                        
                        //Insert the record if it doesn't exist already.
                        $params = array('messageid' => $messageuser->messageid
                                        , 'type' => $messageuser->type
                                        , 'userid' => $messageuser->userid
                                        , 'folderid' => $messageuser->folderid
                                        );
                        $mu = $DB->get_records('email_message_users', $params);
                        if (empty($mu)) {
                            $DB->insert_record_raw('email_message_users', $messageuser);
                        }
                    } else {
                        print_error("line: ".__LINE__." recipient accountid:".$recipient->accountid." not found.<br/>\n");
                    }
                }
            }
        }

        // Migrate the folder and subfolder data.
        define('EMAIL_FOLDER', 0);
        define('EMAIL_INBOX', 1);
        define('EMAIL_SENT', 2);
        define('EMAIL_TRASH', 3);
        define('EMAIL_DRAFT', 4);

        define('EMAIL_DEFAULT_PAGE_SIZE', 10);
        
        $folders = $DB->get_recordset('email_folder');
        foreach ($folders as $folder) {
            if($account = $DB->get_record('email_account', array("id" => $folder->accountid))) {
                $folder->userid = $account->userid;
            } else {
                $folder->userid = -1;
            }

            if (is_null($folder->isparenttype)) {
                $folder->type = EMAIL_FOLDER;
                if ($subfolder = $DB->get_record('email_subfolder', array("folderchildid" => $folder->id))) {
                    $folder->parentfolderid = $subfolder->folderparentid;
                }else{
                    $folder->parentfolderid = 0;
                }
            } else {
                switch ($folder->isparenttype) {
                    case 'inbox':
                        $folder->type = EMAIL_INBOX;
                        break;
                    case 'sendbox':
                        $folder->type = EMAIL_SENT;
                        break;
                    case 'trash':
                        $folder->type = EMAIL_TRASH;
                        break;
                    case 'draft':
                        $folder->type = EMAIL_DRAFT;
                        break;
                }
                $folder->parentfolderid = 0;
            }

            $sql = "SELECT F.id, A.emailid
                    FROM {email_folder} F
                    JOIN {email_account} A
                      ON A.id = F.accountid
                    WHERE F.id = ?";
            if($data = $DB->get_record_sql($sql, array($folder->id))) {
                $folder->emailid = $data->emailid;
            } else {
                $folder->emailid = 0;
            }
            $DB->update_record('email_folder', $folder);
        }

        upgrade_mod_savepoint(true, 2015091700, 'email');
    }

    if ($oldversion < 2015091701) {
        // Remove the tables and columns that are no longer needed.
        $table = new xmldb_table('email_folder');
        $field = new xmldb_field('accountid');
        if ($dbman->field_exists($table, $field)) {
            $index = new xmldb_index('emaifold_acc_ix', XMLDB_INDEX_NOTUNIQUE, array('accountid'));
            $dbman->drop_index($table, $index);
            $dbman->drop_field($table, $field);
        }
        $field = new xmldb_field('isparenttype');
        if ($dbman->field_exists($table, $field)) {
            $index = new xmldb_index('emaifold_isp_ix', XMLDB_INDEX_NOTUNIQUE, array('isparenttype'));
            $dbman->drop_index($table, $index);
            $dbman->drop_field($table, $field);
        }

        // Remove old tables
        $table = new xmldb_table('email_account');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $table = new xmldb_table('email_filter');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $table = new xmldb_table('email_foldermail');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $table = new xmldb_table('email_mail');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $table = new xmldb_table('email_send');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $table = new xmldb_table('email_subfolder');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        upgrade_mod_savepoint(true, 2015091701, 'email');
    }

    if ($oldversion < 2015091800) {
        // Add and Drop indexes.
        $table = new xmldb_table('email');
        $index = new xmldb_index('emai_cou_uix', XMLDB_INDEX_UNIQUE, array('course'));
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        $index = new xmldb_index('email_course', XMLDB_INDEX_NOTUNIQUE, array('course'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('email_folder');
        $index = new xmldb_index('email_folder_userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('email_folder_emailid', XMLDB_INDEX_NOTUNIQUE, array('emailid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('email_message');
        $index = new xmldb_index('email_message_emailid', XMLDB_INDEX_NOTUNIQUE, array('emailid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('email_message_users');
        $index = new xmldb_index('email_message_users_messageid', XMLDB_INDEX_NOTUNIQUE, array('messageid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('email_message_users_userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('email_message_users_folderid', XMLDB_INDEX_NOTUNIQUE, array('folderid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_mod_savepoint(true, 2015091800, 'email');
    }
    
    if ($oldversion < 2016041300) {
        // Update the version to a more recent date for v3.1.
        upgrade_mod_savepoint(true, 2016041300, 'email');
    }


    if ($oldversion < 2016042800) {
        // Add deleted and parenttype columns to email_folder table.
        $table = new xmldb_table('email_folder');
        
        $field = new xmldb_field('parenttype', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('deleted', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2016042800, 'email');
    }

    function get_parenttype($folderid) {
        global $DB;
        $folder = $DB->get_record('email_folder', array('id' => $folderid));
        if ($folder->parentfolderid == 0) {
            return $folder->type;
        } else {
            return get_parenttype($folder->parentfolderid);
        }
    }

    if ($oldversion < 2016050600) {
        // Populate the parenttype column.
        $folderrecords = $DB->get_recordset('email_folder');
        foreach ($folderrecords as $folder) {
            if ($folder->parenttype == 0) {
                if ($folder->parentfolderid == 0) {
                    $folder->parenttype = $folder->type;
                } else {
                    $folder->parenttype = get_parenttype($folder->parentfolderid);
                }
                $DB->update_record('email_folder', $folder);
            }
        }
        upgrade_mod_savepoint(true, 2016050600, 'email');
    }

    if ($oldversion < 2016072700) {
        // Add bodyformat and bodytrust columns to email_message table.
        $table = new xmldb_table('email_message');

        $field = new xmldb_field('bodyformat', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('bodytrust', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2016072700, 'email');
    }

    if ($oldversion < 2016080400) {
        // Convert the message users type field to integers.
        define('EMAIL_USER_TYPE_FROM', 0);
        define('EMAIL_USER_TYPE_TO', 1);
        define('EMAIL_USER_TYPE_CC', 2);
        define('EMAIL_USER_TYPE_BCC', 3);

        $table = new xmldb_table('email_message_users');
        $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'messageid');

        if (!$dbman->field_exists($table, $field)) {
            // From users
            $users = $DB->get_recordset('email_message_users', array('type'=>'from'));
            foreach($users as $user) {
                $user->type = EMAIL_USER_TYPE_FROM;
                $DB->update_record('email_message_users', $user);
            }
            // To users
            $users = $DB->get_recordset('email_message_users', array('type'=>'to'));
            foreach($users as $user) {
                $user->type = EMAIL_USER_TYPE_TO;
                $DB->update_record('email_message_users', $user);
            }
            // CC users
            $users = $DB->get_recordset('email_message_users', array('type'=>'cc'));
            foreach($users as $user) {
                $user->type = EMAIL_USER_TYPE_CC;
                $DB->update_record('email_message_users', $user);
            }
            // BCC users
            $users = $DB->get_recordset('email_message_users', array('type'=>'bcc'));
            foreach($users as $user) {
                $user->type = EMAIL_USER_TYPE_BCC;
                $DB->update_record('email_message_users', $user);
            }

            $dbman->change_field_type($table, $field);
        }

        upgrade_mod_savepoint(true, 2016080400, 'email');
    }

    if ($oldversion < 2016080401) {
        // Add emailid field to email_message_users for easier handling of module instances.
        $table = new xmldb_table('email_message_users');
        $field = new xmldb_field('emailid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $emus = $DB->get_recordset('email_message_users', array('emailid' => 0));
        foreach ($emus as $emu) {
            $message = $DB->get_record('email_message', array('id' => $emu->messageid));
            $emu->emailid = $message->emailid;
            $DB->update_record('email_message_users', $emu);
        }

        upgrade_mod_savepoint(true, 2016080401, 'email');
    }

    if ($oldversion < 2016080402) {
        // Convert email_message.status to an integer and defined constants.
        define('EMAIL_MESSAGE_STATUS_DRAFT', 0);
        define('EMAIL_MESSAGE_STATUS_SENT', 1);

        $table = new xmldb_table('email_message');
        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);

        if (!$dbman->field_exists($table, $field)) {
            $messages = $DB->get_recordset('email_message', array('status' => 'sent'));
            foreach($messages as $message) {
                $message->status = EMAIL_MESSAGE_STATUS_SENT;
                $DB->update_record('email_message', $message);
            }
            $messages = $DB->get_recordset('email_message', array('status' => 'draft'));
            foreach($messages as $message) {
                $message->status = EMAIL_MESSAGE_STATUS_DRAFT;
                $DB->update_record('email_message', $message);
            }
            $dbman->change_field_type($table, $field);
        }
        
        upgrade_mod_savepoint(true, 2016080402, 'email');
    }

    return true;
}
