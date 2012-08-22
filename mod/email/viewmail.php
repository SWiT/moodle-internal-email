<?php // $Id: viewmail.html,v 1.3 2006/10/08 12:28:57 tmas Exp $
/**
 * This page show mails at user.
 *
 * @author Toni Mas
 * @version $Id: viewmail.html,v 1.3 2006/10/08 12:28:57 tmas Exp $
 * @package email
 * @license The source code packaged with this file is Free Software, Copyright (C) 2006 by
 *          <toni.mas at uib dot es>.
 *          It's licensed under the AFFERO GENERAL PUBLIC LICENSE unless stated otherwise.
 *          You can get copies of the licenses here:
 *                         http://www.affero.org/oagpl.html
 *          AFFERO GENERAL PUBLIC LICENSE is also included in the file called "COPYING".
 **/

echo <<<EOD
<table class="sitetopic" border="0" cellpadding="5" cellspacing="0" width="100%">
    <tr class="headermail">
        <td style="border-left: 1px solid black; border-top:1px solid black" width="7%" align="center">
            {$OUTPUT->user_picture($writer, array('courseid'=>$COURSE->id))}
        </td>
        <td style="border-right: 1px solid black; border-top:1px solid black" align="left" colspan="2">
            {$mail->subject}
        </td>
    </tr>
    <tr>
        <td  style="border-left: 1px solid black; border-right: 1px solid black; border-top:1px solid black" align="left" colspan="3">
            &nbsp;&nbsp;&nbsp;
EOD;
            
echo "<b>".get_string('from','email').": </b>";
echo fullname($writer);

echo <<<EOD
        </td>
    </tr>
    <tr>
        <td style="border-left: 1px solid black;" width="80%" align="left" colspan="2">
            &nbsp;&nbsp;&nbsp;
EOD;

echo "<b>".get_string('for','email').": </b>";

echo <<<EOD
        {$userstosendto}    
        </td>
        <td style="border-right: 1px solid black;" width="20%">
EOD;

if ( $urlnextmail or $urlpreviousmail ) {
    echo "&nbsp;&nbsp;&nbsp;||&nbsp;&nbsp;&nbsp;";
}
if ( $urlpreviousmail ) {
    echo '<a href="view.php?'. $urlpreviousmail .'">' . get_string('previous','email') . '</a>';
}
if ( $urlnextmail ) {
    if ( $urlpreviousmail ) {
        echo ' | ';
    }
    echo '<a href="view.php?'. $urlnextmail .'">' . get_string("next","email").'</a>';
}

echo <<<EOD
        &nbsp;&nbsp;
        </td>
    </tr>
EOD;

if ( $userstosendcc != '' ) {
    echo '<tr>
            <td  style="border-left: 1px solid black; border-right: 1px solid black;" align="left" colspan="3">
                &nbsp;&nbsp;&nbsp;
                <b> ' . get_string('cc','email') . ':</b> ' . $userstosendcc . '
            </td>
        </tr>';
}

if ( $userstosendbcc != '' ) {
    echo '<tr>
            <td  style="border-left: 1px solid black; border-right: 1px solid black;" align="left" colspan="3">
                &nbsp;&nbsp;&nbsp;
                <b> ' . get_string('bcc','email') . ':</b> ' . $userstosendbcc . '
            </td>
        </tr>';
}

echo <<<EOD
    <tr>
        <td style="border-left: thin solid black; border-right: 1px solid black" width="60%" align="left" colspan="3">
            &nbsp;&nbsp;&nbsp;
EOD;

echo "<b>".get_string('date','email').": </b>";
echo userdate($mail->timecreated);

 echo <<<EOD
        </td>
    </tr>
    
    
    <tr>
        <td style="border-left: thin solid black; border-right: 1px solid black" width="60%" align="left" colspan="3">
            &nbsp;&nbsp;&nbsp;
EOD;

echo "<b>".get_string('attachment','email').": </b>";


$context = context_module::instance($cm->id);
$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_email', 'attachments', $mail->id);
$tmp = '';
if(count($files)>1){
    $tmp.= "<ul>";
    foreach ($files as $f) {
        // $f is an instance of stored_file
        $filename = $f->get_filename();
        if($filename != '.'){
            $url = "{$CFG->wwwroot}/pluginfile.php/{$f->get_contextid()}/mod_email/attachments";
            $fileurl = $url.$f->get_filepath().$f->get_itemid().'/'.rawurlencode($filename);
            $tmp .= "<li>".html_writer::link($fileurl, $filename)." &nbsp;".display_size($f->get_filesize())."</li>";
        }
    }
    $tmp.= "</ul>";
}
echo $tmp;

echo <<<EOD
        </td>
    </tr>


    <tr>
        <td style="border: 1px solid black" colspan="3" align="left">
            <br />
EOD;

$mail->body = file_rewrite_pluginfile_urls($mail->body, 'pluginfile.php', $context->id, 'mod_email', 'body', $mail->id);
echo text_to_html($mail->body, true, false);

echo <<<EOD
            <br />
            <br />
        </td>
    </tr>
    <tr>
        <td align="right" colspan="3">
EOD;

echo '<a href="view.php?'.$urltoreply.'"><b>'.get_string('reply','email').'</b></a>';
echo ' | <a href="view.php?'.$urltoreplyall.'"><b>'.get_string('replyall','email').'</b></a>';
echo ' | <a href="view.php?'.$urltoforward.'"><b>'.get_string('forward','email').'</b></a>';
echo ' | <a href="view.php?'.$urltoremove.'"><b>'.get_string('removemail','email').'</b></a>';
            
echo <<<EOD
        </td>
    </tr>
</table>
EOD;
            
?>