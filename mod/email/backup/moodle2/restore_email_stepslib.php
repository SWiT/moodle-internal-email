<?php
/**
 * Structure step to restore one email activity
 */
class restore_email_activity_structure_step extends restore_activity_structure_step {
 
    protected function define_structure() {
 
        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');
 
        $paths[] = new restore_path_element('email', '/activity/email');
        if ($userinfo) {
            $paths[] = new restore_path_element('email_account',    '/activity/email/accounts/email_account');
            $paths[] = new restore_path_element('email_folder',     '/activity/email/folders/email_folder');
            $paths[] = new restore_path_element('email_filter',     '/activity/email/folders/email_folder/filters/email_filter');
            $paths[] = new restore_path_element('email_subfolder',  '/activity/email/subfolders/email_subfolder');
            $paths[] = new restore_path_element('email_mail',       '/activity/email/mail/email_mail');
            $paths[] = new restore_path_element('email_send',       '/activity/email/mail/email_mail/recipients/email_send');
            $paths[] = new restore_path_element('email_foldermail', '/activity/email/foldermail/email_foldermail');
        }
 
        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }
 
    protected function process_email($data) {
        global $DB;
 
        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
 
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
 
        // insert the email record
        $newitemid = $DB->insert_record('email', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }
 
    protected function process_email_account($data) {
        global $DB;
 
        $data = (object)$data;
        $oldid = $data->id;
 
        $data->emailid = $this->get_new_parentid('email');
        $data->userid = $this->get_mappingid('user', $data->userid);
        
        $newitemid = $DB->insert_record('email_account', $data);
        $this->set_mapping('email_account', $oldid, $newitemid);
    }
 
    protected function process_email_folder($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
 
        $data->accountid = $this->get_mappingid('email_account', $data->accountid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        
        $newitemid = $DB->insert_record('email_folder', $data);
        $this->set_mapping('email_folder', $oldid, $newitemid);
    }
    
    protected function process_email_filter($data){
        global $DB;
        $data = (object)$data;
        
        $data->folderid = $this->get_mappingid('email_folder', $data->folderid);
        
        $newitemid = $DB->insert_record('email_filter', $data);
    }


    protected function process_email_subfolder($data) {
        global $DB;
        $data = (object)$data;
 
        $data->folderchildid = $this->get_mappingid('email_folder', $data->folderchildid);
        $data->folderparentid = $this->get_mappingid('email_folder', $data->folderparentid);
        
        $newitemid = $DB->insert_record('email_subfolder', $data);
        
    }
    
    protected function process_email_mail($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        
        $data->accountid = $this->get_mappingid('email_account', $data->accountid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        
        $newitemid = $DB->insert_record('email_mail', $data);
        $this->set_mapping('email_mail', $oldid, $newitemid, true);
    }
    
    protected function process_email_send($data) {
        global $DB;
        $data = (object)$data;
        
        $data->accountid = $this->get_mappingid('email_account', $data->accountid);
        $data->mailid = $this->get_new_parentid('email_mail');
        
        $newitemid = $DB->insert_record('email_send', $data);
    }
    
    protected function process_email_foldermail($data) {
        global $DB;
        $data = (object)$data;
        
        $data->mailid = $this->get_mappingid('email_mail', $data->mailid);
        $data->folderid = $this->get_mappingid('email_folder', $data->folderid);
        
        $newitemid = $DB->insert_record('email_foldermail', $data);
    }
 
    protected function after_execute() {
        // Add email related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_email', 'attachments', 'email_mail');
        $this->add_related_files('mod_email', 'body', 'email_mail');
    }
}
?>
