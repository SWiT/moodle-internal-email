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
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 onwards YOUR_NAME_GOES_HERE {@link YOUR_URL_GOES_HERE}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 // This activity has not particular settings but the inherited from the generic
 // backup_activity_task so here there isn't any class definition, like the ones
 // existing in /backup/moodle2/backup_settingslib.php (activities section)


/**
 * Define all the backup steps that will be used by the backup_choice_activity_task
 */


/**
 * Define the complete choice structure for backup, with file and id annotations
 */
class backup_email_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $email = new backup_nested_element('email'
                , array('id')
                , array('name', 'course', 'active', 'maxbytes', 'timecreated', 'timemodified')
                );

        $accounts = new backup_nested_element('accounts');
        $email_account = new backup_nested_element('email_account'
            , array('id')
            , array('userid', 'emailid')
            );
        
        $folders = new backup_nested_element('folders');    
        $email_folder = new backup_nested_element('email_folder'
            , array('id')
            , array('accountid', 'name', 'timecreated', 'isparenttype')
            );

        $subfolders = new backup_nested_element('subfolders');
        $email_subfolder = new backup_nested_element('email_subfolder'
                , array('id')
                , array('folderparentid', 'folderchildid')
                );
        
        $filters = new backup_nested_element('filters');
        $email_filter = new backup_nested_element('email_filter'
                , array('id')
                , array('folderid', 'rules')
                );
        
        $mail = new backup_nested_element('mail');
        $email_mail = new backup_nested_element('email_mail'
                , array('id')
                , array('accountid', 'subject', 'timecreated', 'body')
                );
        
        $recipients = new backup_nested_element('recipients');
        $email_send = new backup_nested_element('email_send'
                , array('id')
                , array('accountid', 'mailid', 'type', 'readed')
                );
        
        $foldermail = new backup_nested_element('foldermail');
        $email_foldermail = new backup_nested_element('email_foldermail'
                , array('id')
                , array('mailid', 'folderid')
                );
        
        // Build the tree
        $email->add_child($accounts);
        $accounts->add_child($email_account);

        $email->add_child($folders); 
        $folders->add_child($email_folder);
        $email_folder->add_child($filters);
        $filters->add_child($email_filter);
        
        $email->add_child($subfolders);
        $subfolders->add_child($email_subfolder);
        
        $email->add_child($mail);
        $mail->add_child($email_mail);
        $email_mail->add_child($recipients);
        $recipients->add_child($email_send);
        
        $email->add_child($foldermail);
        $foldermail->add_child($email_foldermail);
        
        
        // Define sources
        $email->set_source_table('email', array('id' => backup::VAR_ACTIVITYID));
        if($userinfo){
            $email_account->set_source_table('email_account', array('emailid' => backup::VAR_ACTIVITYID));
            
            $sql = "SELECT f.*";
            $sql.= " FROM {email_folder} f";
            $sql.= " JOIN {email_account} a";
                $sql.= " ON a.emailid = ?";
            $sql.= " WHERE f.accountid = a.id";
            $sql.= ";";
            $email_folder->set_source_sql($sql, array(backup::VAR_ACTIVITYID));
            
            $email_filter->set_source_table('email_filter', array('folderid' => '../../id'));
            
            $sql = "SELECT sf.*";
            $sql.= " FROM {email_subfolder} sf";
            $sql.= " JOIN {email_account} a";
                $sql.= " ON a.emailid = ?";
            $sql.= " JOIN {email_folder} f";
                $sql.= " ON f.accountid = a.id";
            $sql.= " WHERE sf.folderchildid = f.id";
            $sql.= ";";
            $email_subfolder->set_source_sql($sql, array(backup::VAR_ACTIVITYID));
            
            
            $sql = "SELECT m.*";
            $sql.= " FROM {email_mail} m";
            $sql.= " JOIN {email_account} a";
                $sql.= " ON a.emailid = ?";
            $sql.= " WHERE m.accountid = a.id";
            $sql.= ";";
            $email_mail->set_source_sql($sql, array(backup::VAR_ACTIVITYID));
            
            $email_send->set_source_table('email_send', array('mailid' => '../../id'));
            
            $sql = "SELECT fm.*";
            $sql.= " FROM {email_foldermail} fm";
            $sql.= " JOIN {email_account} a";
                $sql.= " ON a.emailid = ?";
            $sql.= " JOIN {email_mail} m";
                $sql.= " ON m.accountid = a.id";
            $sql.= " WHERE fm.mailid = m.id";
            $sql.= ";";
            $email_foldermail->set_source_sql($sql, array(backup::VAR_ACTIVITYID));
            
                        
        }

        // Define id annotations
        $email_account->annotate_ids('user', 'userid');
        
        // Define file annotations
        $email_mail->annotate_files('mod_email', 'attachments', 'id');
        $email_mail->annotate_files('mod_email', 'body', 'id');

        // Return the root element (choice), wrapped into standard activity structure
        return $this->prepare_activity_structure($email);
    }
}
