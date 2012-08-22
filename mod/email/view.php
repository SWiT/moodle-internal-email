<?php  // $Id: view.php,v 1.4 2006/10/18 16:41:20 tmas Exp $
/**
 * This page prints a particular instance of email
 *
 * @author Toni Mas
 * @version $Id: view.php,v 1.4 2006/10/18 16:41:20 tmas Exp $
 * @package email
 * @license The source code packaged with this file is Free Software, Copyright (C) 2006 by
 *          <toni.mas at uib dot es>.
 *          It's licensed under the AFFERO GENERAL PUBLIC LICENSE unless stated otherwise.
 *          You can get copies of the licenses here:
 * 		                   http://www.affero.org/oagpl.html
 *          AFFERO GENERAL PUBLIC LICENSE is also included in the file called "COPYING".
 **/
    global $DB, $PAGE, $OUTPUT, $course, $CFG;
    
    require_once("../../config.php");
    require_once("lib.php");

    $id         = optional_param('id', 0, PARAM_INT);                   // Course Module ID, or
    $a          = optional_param('a', 0, PARAM_INT);                    // account ID
    $action 	= optional_param('action', '', PARAM_ALPHANUM);         // Action to execute
    $mailid 	= optional_param('mailid', 0, PARAM_INT);               // email ID
    $selectedmailids 	= optional_param_array('selectedmailids', array(), PARAM_INT);    // email ID
    $folderid	= optional_param('folderid', 0, PARAM_INT); 		// folder ID
    $filterid	= optional_param('filterid', 0, PARAM_INT);		// filter ID

    $message 	= optional_param('message', '', PARAM_TEXT); 	// Message to display

    $page       = optional_param('page', 0, PARAM_INT);          // which page to show
    $perpage    = optional_param('perpage', 10, PARAM_INT);  		// how many per page

    // Only contain value, when moving mails to other folder
    $folderoldid = optional_param('folderoldid', 0, PARAM_INT); 		// folder ID Old

    // Other params
    $error      = optional_param('error', 0, PARAM_ALPHANUM);
    
    $mails      = optional_param('mails', '', PARAM_ALPHANUM); 	// Next and previous mails
    $selectedusers = optional_param('selectedusers', '', PARAM_ALPHANUM); // User who send mail

    $subject = optional_param('subject', '', PARAM_TEXT); 	// Subject of mail
    $body = optional_param('body', '', PARAM_TEXT);		// Body of mail
    $bodyitemid = 0;
    $bodyformat = 1;
    
    // This is a band aid.  This whole module needs a rewrite so that form submission goes through the same file that did the form rendering
    if ( $error != 0 ) {
        $body       = isset($_COOKIE['moodle_email_bodytext']) ? $_COOKIE['moodle_email_bodytext'] : '';
        $bodyitemid = isset($_COOKIE['moodle_email_bodyitemid']) ? $_COOKIE['moodle_email_bodyitemid'] : '';
        $bodyformat = isset($_COOKIE['moodle_email_bodyformat']) ? $_COOKIE['moodle_email_bodyformat'] : '';
        $subject    = isset($_COOKIE['moodle_email_subject']) ? $_COOKIE['moodle_email_subject'] : '';
    }    

    if ($id) {
        if (! $cm = $DB->get_record("course_modules", array("id"=>$id))) {
            print_error('nocoursemodid','email');
        }

        if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
            print_error('nocourseid','email');
        }

        if (! $email = $DB->get_record("email", array("id"=>$cm->instance))) {
            print_error('noemailinstance','email');
        }

    } else {
        if (! $email = $DB->get_record("email", array("id"=>$a))) {
            print_error('nocourseemail','email');
        }
        if (! $course = $DB->get_record("course", array("id"=>$email->course))) {
            print_error('nocourseid','email');
        }
        if (! $cm = get_coursemodule_from_instance("email", $email->id, $course->id)) {
            print_error('noemailinstance','email');
        }
    }

    

	
    // Get Account course user
    if (! $a) {
            if (! $account = email_get_account( $course->id, $USER->id) ) {
                print_error("noaccount", "email");
            }
            $a = $account->id; // Only get Account ID
    }

    require_login($course->id);

    // Show if user have account for this course
    if (! email_have_account($course->id, $USER->id)) {
    	add_to_log($course->id, "email", "view error", "view.php?id=$cm->id", "User $USER->username can't view this account $course->shortname");
    	print_error('noaccount','email');
    }

    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
    if ($cm->visible == 0 && !has_capability('moodle/course:viewhiddenactivities', $modcontext, $USER->id)) { //hide if not visible unless user is teacher
        print_error('activityiscurrentlyhidden');
    }
    
    add_to_log($course->id, "email", "view account", "view.php?id=$cm->id", "$course->shortname");

/// Print the page header

    if ($course->category) {
        $navigation = "<a href=\"../../course/view.php?id=$course->id\">$course->shortname</a> ->";
    } else {
        $navigation = '';
    }

    $stremails = get_string('modulenameplural', 'email');
    $stremail  = get_string('modulename', 'email');

    $url = new moodle_url($CFG->wwwroot.$SCRIPT);
    $PAGE->set_url($url);
    //$PAGE->set_pagelayout('standard');
    print_header("$course->shortname: $email->name"
                , "$course->fullname"
                , "$navigation <a href=index.php?id=$course->id>$stremails</a> -> $email->name"
                , ""
                , ""
                , true
                , update_module_button($cm->id, $course->id, $stremail)
                , navmenu($course, $cm)
                );

    // Options for new mail and new folder
    $options = new stdClass();
    $options->id = $id;
    $options->a	 = $a;
    $options->folderid = $folderid;
    $options->filterid = $filterid;
    $options->folderoldid = $folderoldid;
    $options->mailid = $mailid;

    //formdefaults is used to re-populate a new message form with 
    $formdefaults = new stdClass();
    $formdefaults->error = $error;
    $formdefaults->to = array();
    $formdefaults->cc = array();
    $formdefaults->bcc = array();
    $formdefaults->subject = $subject;
    $formdefaults->attachments = array();
    $formdefaults->body = $body;
    $formdefaults->bodyitemid = $bodyitemid;
    $formdefaults->bodyformat = $bodyformat;
    
    // Print principal table. This have 2 columns . . .  and possibility to add right column.
    echo '<table id="layout-table"><tr>';


    // Print "blocks" of this account
    echo '<td id="left-column" style="width: 180px;vertical-align:top;">';
    email_printblocks($USER->id, $options);

    // Close left column
    echo '</td>';

    // Print principal column
    echo '<td id="middle-column" style="width:99%;vertical-align:top;">';

    // Get actual folder, for show
    if (! $folder = email_get_folder($folderid)) {
        // Default, is inbox
        $folder->name = get_string('inbox', 'email');
    }

    // Print tabs options
    email_print_tabs_options($options, $action);

    // Print header of central colunm
    //print_simple_box_start('center', '100%', 'white', 5, 'sitetopic');

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    
    // Print action in case . . .
    switch( $action ) {
        case 'viewmail':
                email_viewmail($mailid, $options, $mails, $cm);
            break;
        case 'newmail':
                // If is new mail, mailid = null or zero.
                email_newmailform($email, $formdefaults, $options, $selectedusers, $context);
            break;

        case 'draftmail':
                email_draftmailform($mailid, $options);
            break;

        case 'reply':
                email_reply($mailid, $options, $context);
            break;

        case 'replyall':
                email_replyall($mailid, $options, $context);
            break;

        case 'forward':
                email_forward($mailid, $options, $context);
            break;

        case 'search':
                if ( $form = data_submitted($CFG->wwwroot.'/mod/email/view.php') ) {

                    notify(get_string('searchword', 'email').': '.$form->words);
                    $options->folderid = $form->folder;
                    // Show mails searched
                    email_showaccountmails($a, '', $page, $perpage, $options, true, email_search($form, $options) );
                } else {
                    notify('Fail when getting submitted data');
                }
            break;

        case 'removemail':
                // When remove an mail, this functions only accept array in param, overthere converting this param ...
                if (! is_array($selectedmailids)) {
                        $arrmailid = array($selectedmailids);
                } else {
                        $arrmailid = $selectedmailids;
                }
                
                if (empty($arrmailid)) {
                    $arrmailid[] = $mailid;
                }

                // Apply this functions
                email_removemail($arrmailid, $a, $options);

                // Now, show mails
                email_showaccountmails($a, '', $page, $perpage, $options);
            break;

        case 'toread':
                email_mail2read($selectedmailids, $a, $options);
                email_showaccountmails($a, '', $page, $perpage, $options);
            break;

        case 'tounread':
                email_mail2unread($selectedmailids, $a, $options);
                email_showaccountmails($a, '', $page, $perpage, $options);
            break;

        case 'move2folder':
                // In variable folderid
                $success = true;
                // Move mails -- This variable is an array of ID's
                foreach ( $selectedmailids as $mailid ) {
                        // Get foldermail reference
                        $foldermail = email_get_reference2foldermail($mailid, $folderoldid);

                        // Move this mail into folder
                        if (! email_move2folder($mailid, $foldermail->id, $folderid) ) {
                                $success = false;
                        }
                }
                // Show
                if (! $success ) {
                        notify( get_string('movefail', 'email') );
                } else {
                        notify( get_string('moveok', 'email') );
                        // No redirect ... also show this mails new folder
                        $options->folderid = $folderoldid;
                        email_showaccountmails($a, '', $page, $perpage, $options);
                }
            break;

        case 'newfolderform':
                email_newfolderform($options);
            break;

        case 'editfolderform':
                email_edit_folders($options);
            break;

        case 'cleantrash':
                email_cleantrash($a, $options);
            break;

        case 'newfolder':
                email_newfolder($options);
            break;

        case 'removefolder':
                email_removefolder($folderid);
            break;

        case 'renamefolderform':
                email_rename_folder_form($folderid, $options);
            break;

        case 'createfilter':
                email_createfilter($folderid);
            break;

        case 'modifyfilter':
                email_modityfilter($filterid);
            break;

        case 'removefilter':
                email_removefilter($filterid);
            break;

        case 'orderbysubjectasc':
                email_showaccountmails($a, 'subjectasc', $perpage);
            break;

        case 'orderbysubjectdsc':
                email_showaccountmails($a, 'subjectdsc', $page, $perpage);
            break;

        case 'orderbytoasc':
                email_showaccountmails($a, 'toasc', $page, $perpage);
            break;

        case 'orderbytodsc':
                email_showaccountmails($a, 'todsc', $page, $perpage);
            break;

        case 'orderbydataasc':
                email_showaccountmails($a, 'dataasc', $perpage);
            break;

        case 'orderbydatadsc':
                email_showaccountmails($a, 'datadsc', $page, $perpage);
            break;

        case 'displaymessage':
                echo "<div class='generalbox bold centerpara'>".$message."</div>";
                email_showaccountmails($a, '', $page, $perpage, $options);
            break;

        default: //Show list all mails of this account
                email_showaccountmails($a, '', $page, $perpage, $options);
    }

    // End print middle table
    //print_simple_box_end();

    // Close principal column
    echo '</td>';

    // Close table
    echo '</tr></table>';

    // Finish the page
    echo $OUTPUT->footer();

?>
