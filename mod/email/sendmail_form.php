<?php // $Id: sendmail_form.php,v 1.1 2006/10/08 12:28:57 tmas Exp $
/**
 * This page defines the form to create new mail.
 *
 * @author Toni Mas
 * @version $Id: sendmail_form.php,v 1.3 2006/10/08 12:28:57 tmas Exp $
 * @package email
 * @license The source code packaged with this file is Free Software, Copyright (C) 2006 by
 *          <toni.mas at uib dot es>.
 *          It's licensed under the AFFERO GENERAL PUBLIC LICENSE unless stated otherwise.
 *          You can get copies of the licenses here:
 *                         http://www.affero.org/oagpl.html
 *          AFFERO GENERAL PUBLIC LICENSE is also included in the file called "COPYING".
 **/
require_once("$CFG->libdir/formslib.php");

class mod_email_sendmail_form extends moodleform {
    //Add elements to form
    function definition() {
        global $CFG, $OUTPUT, $PAGE;
 
        $mform =& $this->_form; // Don't forget the underscore! 
        $mform->updateAttributes(array('name'=>'sendmail'));
        
        $email = $this->_customdata['email'];
        $options = $this->_customdata['options'];
        $hiddeninputs = email_build_url($options, true);
        $selectedusers = $this->_customdata['selectedusers'];
        if(!is_array($selectedusers)){
            $selectedusers = array();
        }
        $bodyoptions = $this->_customdata['bodyoptions'];
        $attachmentoptions = $this->_customdata['attachmentoptions'];
        
        $label = get_string('participants', 'email');
        $url = (!empty($options)) ? email_build_url($options) : '';
        $link = new moodle_url('/mod/email/participants.php?'.$url);
        $tmp = "<div class=\"fitem\">";
        $actionlink = $OUTPUT->action_link($link, $label, new popup_action('click', $link, 'post', array("height"=>"570","width"=>"450")));
        $tmp.= "<div class=\"fitemtitle\"><b>".$actionlink."</b></div>";
        $tmp.= "<div class=\"felement\"></div>";
        $tmp.= "</div>";
        $mform->addElement('html', $tmp);
        
        $attributes = array('disabled'=>true, 'cols'=>'57', 'rows'=>'1');
        $pa = new popup_action('click', $link, 'post', array("height"=>"570","width"=>"450"));
        $label = get_string('for', 'email');
        $actionlink = $OUTPUT->action_link($link, $label, $pa);
        $mform->addElement('textarea', 'textareato', "<b>".$actionlink."</b>", $attributes);
        $mform->addElement('html', '<div id="to" style="display:none;">');
        foreach ($selectedusers as $userid ) {
            $mform->addElement('html', '<input type="hidden" name="to[]" value="'.$userid.'"/>');
            $mform->setDefault('textareato', fullname($userid));
    	}
        $mform->addElement('html', '</div>');
        
        $mform->addElement('html', '<div id="cc_fields"  style="display:none;">');
            $label = get_string('cc', 'email');
            $actionlink = $OUTPUT->action_link($link, $label, $pa);
            $mform->addElement('textarea', 'textareacc', "<b>".$actionlink."</b>", $attributes);
            $mform->addElement('html', '<div id="cc"  style="display:none;"></div>');
        $mform->addElement('html', '</div>');
        
        $mform->addElement('html', '<div id="bcc_fields"  style="display:none;">');
            $label = get_string('bcc', 'email');
            $actionlink = $OUTPUT->action_link($link, $label, $pa);
            $mform->addElement('textarea', 'textareabcc', "<b>".$actionlink."</b>", $attributes);
            $mform->addElement('html', '<div id="bcc"  style="display:none;"></div>');
        $mform->addElement('html', '</div>');
        
        
        
        $attributes = array('maxlength'=> 200, 'size'=>'60');
        $mform->addElement('text', 'subject', get_string('subject', 'email'), $attributes);
        $mform->setDefault('subject', '');

        $label = get_string('attachment', 'email') . "<br/>(".get_string('maxsize',null,display_size($email->maxbytes)).")";
        $mform->addElement('filemanager', 'attachments', $label, null, $attachmentoptions);
        
        
        $mform->addElement('editor', 'body', get_string("body", "email"), null, $bodyoptions);
        $mform->setType('body', PARAM_RAW);
        
        $mform->addElement('html', $hiddeninputs);
        
        $buttonarray=array();
        $buttonarray[] = &$mform->createElement('submit', 'send', get_string('send','email'));
        $buttonarray[] = &$mform->createElement('submit', 'draft', get_string('draft','email'));
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar'); 
        
        $PAGE->requires->js_init_call('M.mod_email.init_sendmail_form');
    }
    
    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }
}