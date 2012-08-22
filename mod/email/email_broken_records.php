<?php
echo "START<br/><br/>";
require_once('../../config.php');

require_login();

//Get all courses with duplicate instances of Internal Email
$sql = "SELECT e.id";
$sql.= " FROM mdl_email e";
$data = $DB->get_records_sql($sql);
echo "(".count($data).") instances of Internal Email.<br/>".$sql."<br/><br/>";

function displayStatus($table, $sql, $message){
    global $DB;
    
    $data = $DB->get_records_sql($sql);
    $c = count($data);
    $message = "(".$c.") $message<br/>";
    if($c>0){
        $del_sql = "DELETE FROM :table WHERE id IN (:sql)";
        $message = "<b>".$message.str_replace(array(":table",":sql"), array($table, $sql), $del_sql)."</b>";
    }else{
        $message = $message.$sql;
    }
    $message.= "<br/><br/>";
    echo $message;
}


$table = "mdl_email_account";    
$sql = "SELECT a.id";
$sql.= " FROM $table a";
$sql.= " LEFT JOIN mdl_email e";
$sql.= "    ON e.id = a.emailid";  
$sql.= " WHERE e.id IS NULL";
$message = "Accounts - emailid not found";
displayStatus($table, $sql, $message);


$table = "mdl_email_account";    
$sql = "SELECT a.id";
$sql.= " FROM $table a";
$sql.= " LEFT JOIN mdl_user u";
$sql.= "    ON u.id = a.userid"; 
$sql.= " WHERE u.id IS NULL";
$message = "Accounts - userid not found";
displayStatus($table, $sql, $message);


$table = "mdl_email_folder";    
$sql = "SELECT f.id";
$sql.= " FROM $table f";
$sql.= " LEFT JOIN mdl_email_account a";
$sql.= "    ON a.id = f.accountid";
$sql.= " WHERE a.id IS NULL";
$message = "Folders - accountid not found";
displayStatus($table, $sql, $message);


$table = "mdl_email_foldermail";
$sql = "SELECT fm.id";
$sql.= " FROM $table fm";
$sql.= " LEFT JOIN mdl_email_mail m";
$sql.= "    ON m.id = fm.mailid";
$sql.= " WHERE m.id IS NULL";
$message = "FolderMails - mailid not found";
displayStatus($table, $sql, $message);


$table = "mdl_email_foldermail";
$sql = "SELECT fm.id";
$sql.= " FROM $table fm";
$sql.= " LEFT JOIN mdl_email_folder f";
$sql.= "    ON f.id = fm.folderid";
$sql.= " WHERE f.id IS NULL";
$message = "FolderMails - folderid not found";
displayStatus($table, $sql, $message);


$table = "mdl_email_mail";
$sql = "SELECT m.id";
$sql.= " FROM $table m";
$sql.= " LEFT JOIN mdl_email_account a";
$sql.= "    ON a.id = m.accountid";
$sql.= " WHERE a.id IS NULL";
$message = "Mails - accountid not found";
displayStatus($table, $sql, $message);


$table = "mdl_email_mail";
$sql = "SELECT m.id";
$sql.= " FROM $table m";
$sql.= " LEFT JOIN mdl_email_foldermail fm";
$sql.= "    ON fm.mailid = m.id";
$sql.= " WHERE fm.id IS NULL";
$message = "Mails - mailid has no foldermail record";
displayStatus($table, $sql, $message);


$table = "mdl_email_send";
$sql = "SELECT s.id";
$sql.= " FROM $table s";
$sql.= " LEFT JOIN mdl_email_account a";
$sql.= "    ON a.id = s.accountid";
$sql.= " WHERE a.id IS NULL";
$message = "Sends - accountid not found";
displayStatus($table, $sql, $message);


$table = "mdl_email_send";
$sql = "SELECT s.id";
$sql.= " FROM $table s";
$sql.= " LEFT JOIN mdl_email_mail m";
$sql.= "    ON m.id = s.mailid";
$sql.= " WHERE m.id IS NULL";
$message = "Sends - mailid not found";
displayStatus($table, $sql, $message);


$table = "mdl_email_subfolder";
$sql = "SELECT sf.id";
$sql.= " FROM $table sf";
$sql.= " LEFT JOIN mdl_email_folder f";
$sql.= "    ON f.id = sf.folderparentid";
$sql.= " WHERE f.id IS NULL";
$message = "Subfolders - folderparentid not found";
displayStatus($table, $sql, $message);


$table = "mdl_email_subfolder";
$sql = "SELECT sf.id";
$sql.= " FROM $table sf";
$sql.= " LEFT JOIN mdl_email_folder f";
$sql.= "    ON f.id = sf.folderchildid";
$sql.= " WHERE f.id IS NULL";
$message = "Subfolders - folderchildid not found";
displayStatus($table, $sql, $message);


echo "END<br/>";
?>
