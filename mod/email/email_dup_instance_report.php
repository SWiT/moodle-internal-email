<?php
echo "START<br/>";
require_once('../../config.php');

require_login();

//Get all courses with duplicate instances of Internal Email
$sql = "SELECT course";
$sql.= " FROM mdl_email";
$sql.= " GROUP BY course";
$sql.= " HAVING count(id) > 1";
$sql.= ";";
$courses = $DB->get_records_sql($sql);
echo "(".count($courses).") courses with more than 1 instance of Internal Email.<br/><br/>";
foreach($courses as $course){
    
    $sql = "SELECT e.id, cm.id as cm";
    $sql.= " FROM mdl_email e";
    $sql.= " JOIN mdl_course_modules cm";
    $sql.= "    ON cm.module = 16";  //module id of 'email'
    $sql.= "    AND cm.course = e.course";
    $sql.= "    AND cm.instance = e.id";
    $sql.= " WHERE e.course = :courseid";
    $sql.= ";";
    $emails = $DB->get_records_sql($sql, array("courseid"=>$course->course));
    
    echo "Course (".$course->course.") has (".count($emails).") instances.<br/>";
    foreach($emails as $email){
        $sql = "SELECT m.id";
        $sql.= " FROM mdl_email_account a";
        $sql.= " JOIN mdl_email_mail m";
        $sql.= "    ON m.accountid = a.id";
        $sql.= " WHERE a.emailid = :emailid";
        $sql.= ";";
        $mails = $DB->get_records_sql($sql, array("emailid"=>$email->id));
        echo "Emailid: ".$email->id." cmid: ".$email->cm." has ";
        echo (count($mails)>0)?"(".count($mails).") mail":"NO MAIL";
        echo "<br/>";
        
        
    }
    echo "<br/>";
}
echo "END<br/>";
?>
