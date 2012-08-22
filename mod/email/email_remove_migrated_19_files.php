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
 * Script to remove file attachments of Internal Email from the 
 * Moodle v1.9 file structure that have been migrated to v2.2+
 *
 * @package    email
 * @copyright  2012 Matthew G. Switlik, Oakland Unversity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

set_time_limit(0);

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/clilib.php');      // CLI only functions.

echo "START ".date("Y-m-d H:i:s")."\n";


$fs = get_file_storage();


//Get all courses with Internal Email
$sql = "SELECT";
    $sql.= " id,";
    $sql.= " course";
$sql.= " FROM mdl_email";
$sql.= ";";
$emails = $DB->get_records_sql($sql);
foreach($emails as $email){
    echo "------\n";
    
    if (! $cm = get_coursemodule_from_instance("email", $email->id, $email->course)) {
        exit;
    }
    if (! $context = context_module::instance($cm->id)){
        exit;
    }
                                            
    
    $dir_email = $CFG->dataroot."/".$email->course."/moddata/email";
    if(file_exists($dir_email) && is_dir($dir_email))
    {
        //Accounts
        if($h_account = opendir($dir_email))
        {
            while(($dir_account = readdir($h_account)) !== false)
            {
                if($dir_account != "." && $dir_account != ".." && is_dir($dir_email."/".$dir_account))
                {
                    //Mails
                    if($h_mail = opendir($dir_email."/".$dir_account))
                    {
                        while(($dir_mail = readdir($h_mail)) !== false)
                        {
                            if($dir_mail != "." && $dir_mail != ".." && is_dir($dir_email."/".$dir_account."/".$dir_mail))
                            {
                                //File Attachments
                                if($h_file = opendir($dir_email."/".$dir_account."/".$dir_mail))
                                {
                                    while(($filename = readdir($h_file)) !== false){
                                        $fullpath = $dir_email."/".$dir_account."/".$dir_mail."/".$filename;
                                        if($filename != "." && $filename != ".." && !is_dir($fullpath))
                                        {
                                            $file = $fs->get_file($context->id, 'mod_email', 'attachments', $dir_mail, "/", $filename);
                                            if($file===false){
                                                echo "[Error File Not Migrated] ".$fullpath."\n";
                                                
                                            }else{
                                                //Code to remove file after migration
                                                $del_ok = unlink($fullpath);
                                                if($del_ok){
                                                    echo "[Removed] ".$fullpath."\n"; //Migrated and Removed
                                                }else{
                                                    echo "[ERROR Removing] ".$fullpath."\n";
                                                }
                                            }
                                        }      
                                    }
                                    closedir($h_file);
                                    rmdir($dir_email."/".$dir_account."/".$dir_mail);                
                                }
                            }
                        }
                        closedir($h_mail);
                        rmdir($dir_email."/".$dir_account);
                    }
                }
            }
            closedir($h_account);
            rmdir($dir_email);
        }
    }
}
echo "END ".date("Y-m-d H:i:s")."\n";
?>
