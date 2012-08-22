<?php  // $Id: participants.php,v 1.1 2006/10/18 16:41:20 tmas Exp $
/**
 * This page prints all participants or contacts who sents mail/s
 *
 * @uses $CFG
 * @author Toni Mas
 * @version $Id: participants.php,v 1.1 2006/10/18 16:41:20 tmas Exp $
 * @package email
 * @license The source code packaged with this file is Free Software, Copyright (C) 2006 by
 *          <toni.mas at uib dot es>.
 *          It's licensed under the AFFERO GENERAL PUBLIC LICENSE unless stated otherwise.
 *          You can get copies of the licenses here:
 * 		                   http://www.affero.org/oagpl.html
 *          AFFERO GENERAL PUBLIC LICENSE is also included in the file called "COPYING".
 **/

    global $CFG,$DB,$PAGE;

    require_once("../../config.php");
    require_once("lib.php");

    $id 	= optional_param('id', 0, PARAM_INT); 				// Course Module ID, or
    $a  	= optional_param('a', 0, PARAM_INT);  				// account ID
    $action 	= optional_param('action', '', PARAM_ALPHANUM); 	// Action to execute
    $mailid 	= optional_param('mailid', 0, PARAM_INT); 			// email ID
    $folderid	= optional_param('folderid', 0, PARAM_INT); 		// folder ID
    $filterid	= optional_param('filterid', 0, PARAM_INT);			// filter ID

    $page         = optional_param('page', 0, PARAM_INT);          // which page to show
    $perpage    = optional_param('perpage', 10, PARAM_INT);  		// how many per page

    // Only contain value, when moving mails to other folder
    $folderoldid	= optional_param('folderoldid', 0, PARAM_INT); 		// folder ID Old

    // Other params
    $error		= optional_param('error', 0, PARAM_ALPHANUM);

    $mails 		= optional_param('mails', '', PARAM_ALPHANUM); 	// Next and previous mails
    $chooseusers= optional_param('chooseusers', false, PARAM_BOOL); // When send mail, if choose users or not
    $selectedusers = optional_param('selectedusers', '', PARAM_ALPHANUM); // User who send mail


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
            print_error('noemailinstance','email');
        }
        if (! $course = $DB->get_record("course", array("id"=>$email->course))) {
            print_error('nocourseid','email');
        }
        if (! $cm = get_coursemodule_from_instance("email", $email->id, $course->id)) {
            print_error('nocoursemodid','email');
        }
    }

    require_login($course->id);

    // Options for new mail and new folder
    $options = new stdClass();
    $options->id = $id;
    $options->a	 = $a;
    $options->folderid = $folderid;
    $options->filterid = $filterid;
    $options->folderoldid = $folderoldid;

    $url = new moodle_url($CFG->wwwroot.$SCRIPT);
    $PAGE->set_url($url);
    $PAGE->set_pagelayout("popup");
    print_header(get_string('selectaction', 'email'));

    email_choose_users_to_send($email, $options);

    echo $OUTPUT->footer();
?>
