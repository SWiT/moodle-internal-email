<?php  // $Id: folder.php,v 1.1 2006/10/18 16:41:20 tmas Exp $
/**
 * This page recive an actions for folder's
 *
 * @uses $CFG
 * @author Toni Mas
 * @version $Id: folder.php,v 1.1 2006/10/18 16:41:20 tmas Exp $
 * @package email
 * @license The source code packaged with this file is Free Software, Copyright (C) 2006 by
 *          <toni.mas at uib dot es>.
 *          It's licensed under the AFFERO GENERAL PUBLIC LICENSE unless stated otherwise.
 *          You can get copies of the licenses here:
 * 		                   http://www.affero.org/oagpl.html
 *          AFFERO GENERAL PUBLIC LICENSE is also included in the file called "COPYING".
 **/

	global $CFG,$DB;

	require_once('../../config.php');
	require_once('lib.php');
	require_once($CFG->libdir.'/tablelib.php');

	$id 		= optional_param('id', 0, PARAM_INT); 				// Email ID instance/course
	//$name     	= optional_param('name', '', PARAM_ALPHANUM); 		// Name folder
	//$parent   	= optional_param('parent', '', PARAM_ALPHANUM); 	// Parent of this new folder

	if ($id) {
        if (! $cm = $DB->get_record("course_modules", array("id"=>$id))) {
            print_error("nocoursemodid", "email");
        }

        if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
            print_error("nocourseid", "email");
        }

        if (! $email = $DB->get_record("email", array("id"=>$cm->instance))) {
            print_error("noemailinstance", "email");
        }

    } else {
        if (! $email = $DB->get_record("email", array("id"=>$a))) {
            print_error("nocourseemail","email");
        }
        if (! $course = $DB->get_record("course", array("id"=>$email->course))) {
            print_error("nocourseid", "email");
        }
        if (! $cm = get_coursemodule_from_instance("email", $email->id, $course->id)) {
            print_error("nocoursemodid", "email");
        }
    }

	require_login($course->id);

	// Get form sended
    if ( $form = data_submitted($CFG->wwwroot.'/mod/email/view.php') ) {

        // Associted accountid
	if (! $account = $DB->get_record('email_account', array('emailid'=>$email->id, 'userid'=>$USER->id))) {
            print_error("noaccount","email");
        }

        // Generic URL for send mails errors
        if(!isset($form->parent)){
            $form->parent = '';
        }
        $baseurl =  $CFG->wwwroot.'/mod/email/view.php?id='.$cm->id.'&amp;folderid='.$form->id.'&amp;name=\''.$form->name.'\'&amp;parent='.$form->parent;

        // Check name
        if (empty($form->name)) {
                // Save in error variable this error
                $error->name = 1;
        } else {

                // Clean name
                $foldernew->name = strip_tags($form->name);
        }

        // Add user
        $foldernew->userid = $USER->id;
        $foldernew->accountid = $account->id;

        // Apply this information
        $stralert = get_string('createfolderok', 'email');

        // Use this field, for known if folder exist o none
        if (! $form->oldname ) {
                // Add new folder
                if ( ! email_newfolder($foldernew, $form->parentfolder) ) {
                    print_error("nocreatefolder", "email");
                }

        } else {
                // If exist folderid (sending in form), set field
                if ( ! email_rename_folder($form->folderid, $form->name) ) {
                    print_error("nomodifyfolder","email");
                }

                // Apply this information
                $stralert = get_string('modifyfolderok', 'email');
        }

        redirect($CFG->wwwroot.'/mod/email/view.php?id='.$cm->id, $stralert, '3');

    } else {
    	notify('Email data if empty');
    }

?>