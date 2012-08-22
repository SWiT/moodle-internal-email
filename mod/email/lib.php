<?php  // $Id: lib.php,v 1.4 2006/10/18 16:41:20 tmas Exp $
/**
 * Library of functions and constants for module email
 *
 * @author Toni Mas
 * @version $Id: lib.php,v 1.4 2006/10/18 16:41:20 tmas Exp $
 * @package email
 * @license The source code packaged with this file is Free Software, Copyright (C) 2006 by
 *          <toni.mas at uib dot es>.
 *          It's licensed under the AFFERO GENERAL PUBLIC LICENSE unless stated otherwise.
 *          You can get copies of the licenses here:
 * 		                   http://www.affero.org/oagpl.html
 *          AFFERO GENERAL PUBLIC LICENSE is also included in the file called "COPYING".
 **/

// Standard definitions
define('EMAIL_INBOX', 'inbox');
define('EMAIL_SENDBOX', 'sendbox');
define('EMAIL_TRASH', 'trash');
define('EMAIL_DRAFT', 'draft');


// Standard errors
define('EMAIL_NOSUBJECT', '1');
define('EMAIL_NOSENDERS', '2');

define('EMAIL_NO_DISPLAY_COURSE_PRINCIPAL', 0);
define('EMAIL_DISPLAY_COURSE_PRINCIPAL', 1);

// Display course principal
$EMAIL_MODE_DISPLAY_COURSE_PRINCIPAL = array (	EMAIL_DISPLAY_COURSE_PRINCIPAL => get_string('showprincipalcourse', 'email'),
                              					EMAIL_NO_DISPLAY_COURSE_PRINCIPAL => get_string('noshowprincipalcourse', 'email') );


 /// DEFAULT CONFIGS

if (!isset($CFG->email_display_course_principal)) {
    set_config('email_display_course_principal', EMAIL_DISPLAY_COURSE_PRINCIPAL);  // Default show principal course in blocks who containg list of courses
}

if (!isset($CFG->email_number_courses_display_in_blocks_course)) {
    set_config('email_number_courses_display_in_blocks_course', 99999);  // Default show all courses
}


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @uses $CFG, $USER
 * @param object $instance An object from the form in mod_form.php
 * @return int The id of the newly inserted email record
 **/
function email_add_instance($email) {

    global $CFG, $USER, $DB;

    // Define time created and modified
    $now = time();
    $email->timecreated  = $now;
    $email->timemodified = $now;


    //Only hability one instance for course
    $count = $DB->count_records('email');

    // Principal course.
    if ($email->course == 1)	{
        if ($count == 0) {
            if(! $emailid = $DB->insert_record('email', $email)) {
                print_error("noenable","email");
            }

            //Create users accounts for this instance.
            if (! email_add_all_accounts($emailid) ) {
                print_error("failcreatingnewaccounts", "email");
            }

        } else {
            //email was already enabled.
            print_error("alreadyenabled","email");
        }
    } else { //Others courses
         $count = $DB->count_records('email', array('course'=>$email->course));   //WTF why is this returning "1" for course id 3???
        if($DB->count_records('email', array('course'=>$email->course)) > 0) {
            print_error("alreadyenabled","email");
        }
        else{
            $emailid = $DB->insert_record('email', $email);

            if($DB->count_records('email', array('course'=>$email->course)) != 1) {
                print_error("noenable","email");
            }else if (! email_add_all_accounts($emailid) ) {
                print_error("failcreatingnewaccounts", "email");
            }
            
            return $emailid;
        }
    }

    return false;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 *
 * @param object $instance An object from the form in mod.html
 * @return boolean Success/Fail
 **/
function email_update_instance($email) {
    global $DB;
    
    $email->timemodified = time();
    $email->id = $email->instance;

    # May have to add extra stuff in here #

    return $DB->update_record('email', $email);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function email_delete_instance($id) {
    global $DB;

    if (! $email = $DB->get_record('email', array('id'=>$id))) {
        return false;
    }

    $cm = get_coursemodule_from_instance("email", $email->id);
    
    $result = true;

    # Delete any dependent records here #

	// Delete instance.
    if (! $DB->delete_records('email', array('id'=>$email->id))) {
        $result = false;
    }

    notify ('Removing this course instance');

    // Get account associated at instance
    if (! $accounts =  $DB->get_records('email_account', array('emailid'=>$email->id))) {
    	return false;
    }

    // Delete all accounts
    foreach ( $accounts as $account ) {

		// Get folders associated at this account
		if ($folders =  $DB->get_records('email_folder', array('accountid'=>$account->id))) {

		    // For all folders . . .
		    foreach($folders as $folder) {

				// Delete all subfolders of this
				if (! $DB->delete_records('email_subfolder', array('folderparentid'=>$folder->id))) {
				    	$result = false;
				}

				// Delete all subfolders of this
				if (! $DB->delete_records('email_subfolder', array('folderchildid'=>$folder->id))) {
				    	$result = false;
				}

				// Delete all filters of this
				if (! $DB->delete_records('email_filter', array('folderid'=>$folder->id))) {
				    	$result = false;
				}
		    }

		    // Delete all folder associated a this account
		    if (! $DB->delete_records('email_folder', array('accountid'=>$account->id))) {
			    	$result = false;
			}
		}

		notify ('Removing course folders created by users');


	    // Get all written mails, but delete reply for anyone.
	    if ($mails = $DB->get_records('email_mail', array('accountid'=> $account->id))) {

		    // Delete all replys for mail written
		    foreach($mails as $mail) {

				// Delete all attachments
				if (email_has_attachments($mail, $cm)) {
                                    if (! email_delete_attachments($mail->id, $cm)) {
                                        $result = false;
                                    }
				}

			    // Delete all foldermail (relation folder and mail)
			    if (! $DB->delete_records('email_foldermail', array('mailid'=>$mail->id))) {
			    	$result = false;
			    }
		    }
	    }

	    notify ('Removing mails');

	    // Delete all emails written for this account
	    if (! $DB->delete_records('email_mail', array('accountid'=>$account->id))) {
	    	$result = false;
	    }

	    // Delete all emails send to this account
	    if (! $DB->delete_records('email_send', array('accountid'=>$account->id))) {
	    	$result = false;
	    }
    }

    // Delete all accounts of this instance
    if (! $DB->delete_records('email_account', array('emailid'=>$email->id))) {
    	$result = false;
    }

	notify ('Removing accounts course');

	notify ('Satisfactory erasure');

    return $result;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param int $account Account ID
 * @return object Number of mails written in this account, accountid and userid
 * @todo Finish documenting this function
 **/
function email_user_outline($account) {
    global $DB;
    $account = $DB->get_record('account', array('id'=>$account));

    // Small object contains account ID, user Id, and number of writed mails.
    $return->accountid = $account;
    $return->userid = $account->userid;
    $return->numberwritedmails = $DB->count_records('mail',array('accountid'=>$account));

    return $return;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function email_user_complete($course, $user, $mod, $email) {

	//Not supported

    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in email activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @uses $CFG
 * @return boolean
 * @todo Finish documenting this function
 **/
function email_print_recent_activity($course, $isteacher, $timestart) {
    global $CFG;

	//Not supported

    return false;  //  True if anything was printed, otherwise false
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @uses $CFG
 * @return boolean
 * @todo Finish documenting this function
 **/
function email_cron () {
    // Cron create missing accounts for courses with Internal Email.
    global $CFG,$DB;

    @set_time_limit(0);
    @raise_memory_limit("128M");

    $starttime = time();

    // Get all courses with Internal Email
    $emails  = $DB->get_records('email', array('active'=>1), 'id, course');
   
    $msg = "\n\t".count($emails) . " courses with Internal Email active.\n";

    $nCreated = 0;
    
    foreach($emails as $email) {
        //compare Users versus Accounts
        $context = get_context_instance(CONTEXT_COURSE, $email->course);
        $users = get_enrolled_users($context, '', 0 , "u.id");
        
        $accounts = $DB->get_records('email_account', array('emailid'=>$email->id));
        
        // compare
        foreach($users as $user) {
            $found = false;
            foreach($accounts as $account){
                if($user->id == $account->userid){
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                email_add_account($email->id, $user->id);
                $nCreated++;
            }
        }
    }
    $msg .= "\t".$nCreated." email accounts created.\n";
    echo $msg;

    return true;
}

/**
 * Must return an array of grades for a given instance of this module,
 * indexed by user.  It also returns a maximum allowed grade.
 *
 * Example:
 *    $return->grades = array of grades;
 *    $return->maxgrade = maximum allowed grade;
 *
 *    return $return;
 *
 * @param int $emailid ID of an instance of this module
 * @return mixed Null or object with an array of grades and with the maximum grade
 **/
function email_grades($emailid) {
   return NULL;
}

/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of email. Must include every user involved
 * in the instance, independient of his role (student, teacher, admin...)
 * See other modules as example.
 *
 * @param int $emailid ID of an instance of this module
 * @return mixed boolean/array of students
 **/
function email_get_participants($emailid) {
    return false;
}

/**
 * This function returns if a scale is being used by one email
 * it it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $emailid ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 **/
function email_scale_used ($emailid,$scaleid) {
    $return = false;

    return $return;
}

function email_scale_used_anywhere($scaleid) {
    return false;
}

/**
 * This function delete account.
 *
 * @param int $accountid account ID to add accounts
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_delete_account($accountid) {
    global $DB;
    $status = true;

    // Get folders associated at this account
    if ($folders =  $DB->get_records('email_folder', array('accountid'=>$accountid))) {

        // For all folders . . .
        foreach($folders as $folder) {

            // Delete all subfolders of this
            if (! $DB->delete_records('email_subfolder', array('folderparentid'=>$folder->id))) {
                $status = false;
            }

            // Delete all subfolders of this
            if (! $DB->delete_records('email_subfolder', array('folderchildid'=>$folder->id))) {
                $status = false;
            }

            // Delete all filters of this
            if (! $DB->delete_records('email_filter', array('folderid'=>$folder->id))) {
                $status = false;
            }

                // Delete all foldermail (relation folder and mail)
            if (! $DB->delete_records('email_foldermail', array('folderid'=>$folder->id))) {
                $status = false;
            }
        }

        // Delete all folder associated a this account
        if (! $DB->delete_records('email_folder', array('accountid'=>$accountid))) {
            $status = false;
        }
    }

    // Delete all emails send to this account
    if (! $DB->delete_records('email_send', array('accountid'=>$accountid))) {
    	$result = false;
    }

    // Delete this account
    if (! $DB->delete_records('email_account', array('id'=>$accountid))) {
    	$result = false;
    }

	return $status;
}

/**
<<<<<<< HEAD
 * This function deleting accounts to one instance of email
 * Add teachers and students of this course.
 *
 * @param int $emailid email ID to add accounts
 * @param int $courseid Course ID.
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_deleting_accounts($emailid, $courseid) {

	$status = true;

	// Get accounts
	if ( $accounts = email_get_accounts($courseid) ) {
            // compare
            foreach ( $accounts as $account ) {

                $currentcontext = get_context_instance(CONTEXT_COURSE, $courseid);
                if(has_capability('moodle/course:viewparticipants', $currentcontext, $account->userid)          //isstudent
                    || has_capability('moodle/course:viewhiddenactivities', $currentcontext, $account->userid)  //isteacher
                    || has_capability('moodle/course:manageactivities', $currentcontext, $account->userid)      //isteacheredit
                    || is_siteadmin($account->userid)              //isadmin
                  ){
                    continue;
                }

                $status = email_delete_account($account->id);
                
            }
	} else {
            $status = false;
	}

	return $status;
}

/**
 * This function compare accounts to one instance of email
 * Add or delete teachers and students of this course.
 *
 * @param int $courseid Course ID.
 * @param int $emailid Email ID.
 * @param object $users User who containg course.
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_compare_accounts_participants($courseid, $emailid, $users) {

	$status = true;

	if ( $users ) {

		// compare
		foreach ( $users as $user ) {
			if (! email_have_account($courseid, $user->id, $emailid) ) {

				email_add_all_accounts($emailid);

				// Add accounts
				//if ( isstudent($courseid, $user->id) ) {
				//	$status = email_add_account_students($emailid);
				//} else {
				//	$status = email_add_account_teachers($emailid);
				//}

				// Now, deleting accounts not corresponding of this course
				$status = email_deleting_accounts($emailid, $courseid);
			}
		}
	} else {
		$status = false;
	}

	return $status;
}

/**
=======
>>>>>>> 42e42f8... OUMoodle internal_email : optimized email_cron().  removed useless functions that dealt with account creation and removal that were not being called anywhere.
 * This function insert new account for any new users
 * of one course.
 *
 * @param int $emailid Id of the courses Email activity
 * @param int $userid Id of the User to add.
 * @return boolean Success of account creation.
 **/
function email_add_account($emailid, $userid) {
    global $DB;
    $status = false;

    $account = new stdClass();
    $account->userid = $userid;
    $account->emailid = $emailid;
    $account->id = $DB->insert_record('email_account', $account);
    if($account->id > 0){
        $status = true;
        email_create_parents_folders($account->id);
    }
    return $status;
}

/**
 * This function count accounts to one instance of email
 * (one course).
 *
 * @param int $course Course ID
 * @return int Number of accounts
 * @todo Finish documenting this function
 **/
function email_count_accounts($course) {
    global $DB;
    // Get email instance
    $email = $DB->get_record('email', array('course'=>$course));

    return $DB->count_records('email_account', array('emailid'=>$email->id));

}

/**
 * This function get accounts to one instance of email
 * (one course).
 *
 * @param int $course Course ID
 * @return object Accounts
 * @todo Finish documenting this function
 **/
function email_get_accounts($course) {
    global $DB;
    // Get email instance
    $email = $DB->get_record('email', array('course'=>$course));

    return $DB->get_records('email_account', array('emailid'=>$email->id));

}

/**
 * This function get account, having course ID and user ID.
 *
 * @param int $course Course ID
 * @param int $user	   User ID
 * @return object Account
 * @todo Finish documenting this function
 **/
function email_get_account($course, $user) {
    global $DB;
    // Get email instance
    $email = $DB->get_record('email', array('course'=>$course));

    return $DB->get_record('email_account', array('emailid'=>$email->id, 'userid'=>$user));

}

/**
 * This function get account by Account ID
 *
 * @param int $accountid Account ID
 * @return object Account
 * @todo Finish documenting this function
 **/
function email_get_account_by_id($accountid) {
    global $DB;
	return $DB->get_record('email_account', array('id'=>$accountid));

}

/**
 * This function adds all accounts for a given emailid
 *
 * @param int $emailid Email ID
 * @return boolean Success/Fail
 **/
function email_add_all_accounts($emailid) {
    global $CFG,$DB;

    $email = $DB->get_record('email', array('id'=>$emailid));

    if (!$currentcontext = get_context_instance(CONTEXT_COURSE, $email->course)) {
            print_error('cannotfindcontext');
    }
	
    $sitecontext = get_context_instance(CONTEXT_SYSTEM);

	
    if ($roles = get_roles_used_in_context($currentcontext)) {
		
        $canviewroles    = get_roles_with_capability('moodle/course:viewparticipants', CAP_ALLOW, $currentcontext);
        $doanythingroles = get_roles_with_capability('moodle/site:doanything', CAP_ALLOW, $sitecontext);

        //Remove extra roles we dont want (site admin and course recreator)
        foreach ($roles as $role) {
            if (!isset($canviewroles[$role->id])) {   // Avoid this role (eg course creator)
                $avoidroles[] = $role->id;
                unset($roles[$role->id]);
                continue;
            }
            if (isset($doanythingroles[$role->id])) {   // Avoid this role (ie admin)
                $avoidroles[] = $role->id;
                unset($roles[$role->id]);
                continue;
            }
        }
	
        foreach ($roles as $role) {
            $users = get_role_users($role->id, $currentcontext);

            $account->emailid = $emailid;
            foreach($users as $user) {
                $account->userid = $user->id;

                //If record don't exist
                if (! $DB->record_exists('email_account', array('userid'=>$account->userid, 'emailid'=>$account->emailid)) ) {

                    // Add account
                    if (! $account->id = $DB->insert_record('email_account', $account)) {
                        //Failed insert account
                        return false;
                    }

                    // Add parents folders
                    if (! email_create_parents_folders($account->id)) {
                        return false;
                    }
                }
            }
        }
    }

    return true;
}

/**
 * This functions return string language of root folder (default en)
 *
 * @param string $type Type
 * @return string Name
 * @todo Finish documenting this function
 */
function email_get_root_folder_name($type) {

	if ($type == EMAIL_INBOX) {
		$name = get_string('inbox', 'email');
	} else if ($type == EMAIL_SENDBOX) {
		$name = get_string('sendbox', 'email');
	} else if ($type == EMAIL_TRASH) {
		$name = get_string('trash', 'email');
	} else if ($type == EMAIL_DRAFT) {
		$name = get_string('draft', 'email');
	} else {
		// Type is not defined
		$name = '';
	}

	return $name;
}

/**
 * This function prints blocks.
 *
 * @uses $CGF
 * @param int $userid User ID
 * @param object $options Options to do
 * @return NULL
 * @todo Finish documenting this function
 **/
function email_printblocks($userid, $options) {

	global $CFG,$DB,$OUTPUT;

	$accountid = $options->a;

	$strcourse  = get_string('course');
	$strcourses = get_string('courses');
	$strsearch  = get_string('search');
	$strmail    = get_string('modulename', 'email');
	$strfolders = get_string('folders', 'email');
	$stredit 	= get_string('editfolders', 'email');

	// For title blocks
	$startdivtitle	= '<div class="title">';
	$enddivtitle    = '</div>';

	// Print search block
	$form = email_get_search_form($accountid, $options);
	print_side_block($startdivtitle.$strsearch.$enddivtitle, $form);

	// Define default path of icon for folders
	$icon = '<img src="'.$OUTPUT->pix_url('i/files').'" height="16" width="16" alt="'.$strcourse.'" />';

	// Define folder parent icon
	$parenticon = '<img src="'.$OUTPUT->pix_url('f/parent').'" height="16" width="16" alt="'.$strcourse.'" />';

	// Get my folders account
	if ( $folders = email_get_root_folders($accountid) ) {

            if (! $account = email_get_account_by_id($accountid) ) {
                print_error('noaccount', 'email');
            }

            // Get necessary records
            $email   = $DB->get_record('email', array('id'=>$account->emailid));
            $course  = $DB->get_record('course', array('id'=>$email->course));
            if (! $cm = get_coursemodule_from_instance('email', $email->id, $course->id)) {
                print_error('nocourseemail', 'email');
            }

            // Get courses associated at this account
            foreach ($folders as $folder) {

                    unset($numbermails);
                    unset($unreaded);
                    // Get number of unreaded mails
                    if ( $numbermails = email_get_number_unreaded($account->id, $folder->id) ) {
                            $unreaded = '<b>('.$numbermails.')</b>';
                    } else {
                            $unreaded = '';
                    }

                    // Clean trash
                    $clean = '';
                    if ( email_isfolder_type($folder, EMAIL_TRASH) ) {
                            $clean .= '&#160;&#160;<a href="'.$CFG->wwwroot.'/mod/email/view.php?id='.$cm->id.'&amp;folderid='.$folder->id.'&amp;action=cleantrash">('.get_string('cleantrash', 'email').')</a>';
                    }

                    $list[]  = '<a href="'.$CFG->wwwroot.'/mod/email/view.php?id='.$cm->id.'&amp;folderid='.$folder->id.'"><b>'.$folder->name.'</b></a>'.$unreaded.$clean;
                    $icons[] = $parenticon;

                    // Now, print all subfolders it
                    $subfolders = email_get_subfolders($folder->id);

                    // If subfolders
                    if ( $subfolders ) {
                            foreach ( $subfolders as $subfolder ) {

                                    unset($numbermails);
                                    unset($unreaded);
                                    // Get number of unreaded mails
                                    $numbermails = email_get_number_unreaded($account->id, $subfolder->id);
                                    if ( $numbermails > 0 ) {
                                            $unreaded = '<b>('.$numbermails.')</b>';
                                    } else {
                                            $unreaded = '';
                                    }

                                    $list[]  = '<a href="'.$CFG->wwwroot.'/mod/email/view.php?id='.$cm->id.'&amp;folderid='.$subfolder->id.'">'.$subfolder->name.'</a>'.$unreaded;
                                    $icons[] = $icon;
                            }
                    }
            }

            // For admin folders
            $streditfolders = '<a href="'.$CFG->wwwroot.'/mod/email/view.php?id='.$cm->id.'&amp;action=\'editfolderform\'"><b>'.$stredit.'</b></a>';

            // Print block of course select folders
            print_side_block($startdivtitle.$strfolders.$enddivtitle, '', $list, $icons, $streditfolders);

	}

	// Remove old fields
	unset($list);
	unset($icons);

	// Define default path of icon for course
	$icon = '<img src="'.$OUTPUT->pix_url('/i/course').'" height="16" width="16" alt="'.$strcourse.'" />';

	// Get list accounts
	if (! $accounts = $DB->get_records('email_account', array('userid'=>$userid)) ) {
		$list = array();
	}



	// Get courses associated at this account
	foreach ($accounts as $account) {
		$email   = $DB->get_record('email', array('id'=>$account->emailid));
		if($email === false){
                    continue;
                }
                $course  = $DB->get_record('course', array('id'=>$email->course));

		// Check if show principal course
		if ( $CFG->email_display_course_principal ) {
                    if ($cm = get_coursemodule_from_instance('email', $email->id, $course->id)) {
                        //added
                        $list[]  = '<a href="'.$CFG->wwwroot.'/mod/email/view.php?id='.$cm->id.'">'.$course->fullname.'</a>';
                        $icons[] = $icon;
                    }
		} else {
			// Don't show principal course.
			if ( $course->id != 1 ) {
				if ($cm = get_coursemodule_from_instance('email', $email->id, $course->id)) {
                                	$list[]  = '<a href="'.$CFG->wwwroot.'/mod/email/view.php?id='.$cm->id.'">'.$course->fullname.'</a>';
					$icons[] = $icon;
				}
			}
		}
	}

	// Print block of my account courses
	print_side_block($startdivtitle.$strcourses.$enddivtitle, '', $list, $icons);

}

/**
 * This function print form to edit folders.
 * Note: Only edit or remove subfolders of 3 parents
 * (inbox, sendbox and trash) hasn't edit or remove
 *
 * @uses $CFG
 * @param object $options Options
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 */
function email_edit_folders($options) {

	global $CFG,$DB,$OUTPUT;

	// String for alt of img
	$strremove = get_string('removefolder', 'email');
        $strrename = get_string('renamefolder', 'email');


	echo '<form method="post" name="folderform" action="'.$CFG->wwwroot.'/mod/email/view.php?action=\'none\'">
					<table align="center">';

	$form = '';

	$OUTPUT->heading(get_string('editfolder', 'email'));

	if ( $folders = email_get_root_folders($options->a, false) ) {

            // Get account
            $account = email_get_account_by_id($options->a);

            $emailid = $account->emailid;

            $email   = $DB->get_record('email', array('id'=>$emailid));
            $course  = $DB->get_record('course', array('id'=>$email->course));
            if (! $cm = get_coursemodule_from_instance('email', $email->id, $course->id)) {
                print_error('nocoursemodid','email');
            }

            // Has subfolders
            $hassubfolders = false;

            // Get courses associated at this account
            foreach ($folders as $folder) {

                // Now, print all subfolders it
                $subfolders = email_get_subfolders($folder->id);

                // If subfolders
                if ( $subfolders ) {
                    foreach ( $subfolders as $subfolder ) {
                        $form  .= '<tr><td>';
                        $form  .= '<a href="'.$CFG->wwwroot.'/mod/email/view.php?id='.$cm->id.'&amp;folderid='.$subfolder->id.'&amp;action=renamefolderform">'.$subfolder->name.'</a>';
                        $form  .= '&#160;&#160;';

                        $form  .= '<a href="'.$CFG->wwwroot.'/mod/email/view.php?id='.$cm->id.'&amp;folderid='.$subfolder->id.'&amp;action=renamefolderform">';
                            $form  .= '<img src="'.$OUTPUT->pix_url('t/edit').'" alt="'.$strrename.'" />';
                        $form  .= '</a>';
                        $form  .= '&#160;&#160;';
                        $form  .= '<a href="'.$CFG->wwwroot.'/mod/email/view.php?id='.$cm->id.'&amp;folderid='.$subfolder->id.'&amp;action=removefolder">';
                            $form  .= '<img src="'.$OUTPUT->pix_url('t/delete').'" alt="'.$strremove.'" />';
                        $form  .= '</a>';
                        $form  .= '</td></tr>';

                        // Has subfolders..
                        $hassubfolders =  true;
                    }
                }
            }
	}

	$form .= 	'</table>
			</form>';


	echo $form;


	if ( ! $hassubfolders ) {
		// Print form to new folder
		notify( get_string ('nosubfolders', 'email') );
		email_newfolderform($options);
	}

	return true;
}


/**
 * This fuctions return all subfolders with one folder, if it've
 *
 * @param int $folderid Folder parent
 * @return array Contain all subfolders
 * @todo Finish documenting this function
 **/
function email_get_subfolders($folderid) {
    global $DB;

    // Get childs for this parent
    $childs = $DB->get_records('email_subfolder', array('folderparentid'=>$folderid));

    // If have childs
    if ( $childs ) {

            // Save child folder in array
            foreach ( $childs as $child ) {
                    $subfolders[] = $DB->get_record('email_folder', array('id'=>$child->folderchildid));
            }
    } else {
            // If no childs, return false
            return false;
    }

    // Return subfolders
    return $subfolders;
}

/**
 * This fuctions return the root parent folder, of that folderchild
 *
 * @param int $folderid Folder ID
 * @return Object Contain root parent folder
 * @todo Finish documenting this function
 **/
function email_get_parentfolder($folderid) {
    global $DB;

    // Get parent for this child
    $parent = $DB->get_record('email_subfolder', array('folderchildid'=>$folderid));

    // If has parent
    if ( $parent ) {

            $folder = email_get_folder($parent->folderparentid);

            // While not find parent root, searching...
            while ( is_null($folder->isparenttype) ) {
                    // Searching ...
                    $parent = $DB->get_record('email_subfolder', array('folderchildid'=>$folder->id));
                    $folder = email_get_folder($parent->folderparentid);
            }

            return $folder;

    } else {
            // If no parent, return false => FATAL ERROR!
            return false;
    }

    // Return Fail
    return false;
}

/**
 * This function return form for searching emails.
 *
 * @uses $CGF
 * @param int $accountid Account ID
 * @param object $options Options
 * @return string HTML search form
 * @todo Finish documenting this function
 **/
function email_get_search_form($accountid, $options){

    global $CFG;

    // Get my folders account
    if ( $folders = email_get_root_folders($accountid, false) ) {

        // Get courses associated at this account
        foreach ($folders as $folder) {
            $menu[$folder->id] = $folder->name;

            // Now, get all subfolders it
            $subfolders = email_get_subfolders($folder->id);

            // If subfolders
            if ( $subfolders ) {
                foreach ( $subfolders as $subfolder ) {
                    $menu[$subfolder->id] = $subfolder->name;
                }
            }
        }
    }

    $inputhidden = '';
    if ($options) {
        foreach ($options as $name => $value) {
            $inputhidden .= '<input type="hidden" name="'.$name.'" value="'.$value.'" />';
        }
    }

    $choosetofrom[1] = get_string('from','email');
    $choosetofrom[2] = get_string('to','email');

    // By default, select inbox .. get this
    $inbox = email_get_root_folder($accountid, EMAIL_INBOX);

    $prevform = data_submitted();
    $form = '<form method="post" name="searchform" action="'.$CFG->wwwroot.'/mod/email/view.php?action=\'search\'">';
    $form.= '<table>';
        $form.= '<tr>';
            $form.= '<td colspan="4">';        
            $words = isset($prevform->words)? htmlspecialchars($prevform->words) : '';
            $form.= '<input type="text" name="words" value="'.$words.'"/>';
            $form.= '</td>';
        $form.= '</tr>';
        $form.= '<tr valign="top">';
            $form.= '<td align="center" colspan="4">';
            $form.= $inputhidden;
            $form.= '<input type="submit" name="send" value="'.get_string('search').'" />&#160;<br/>';
            $selectedfolder = isset($prevform->folder)? $prevform->folder : $inbox->id;
            $form.= html_writer::select($menu, 'folder', $selectedfolder, false);
            $form.= '</td>';
        $form.= '</tr>';
    $form.= '</table>';
    $form.= '</form>';
    unset($prevform, $words, $selectedfolder);
    return $form;
}

/**
 * This function show if this user have an
 * account in this course.
 *
 * @param int $courseid Course ID
 * @param int $userid  User ID
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_have_account($courseid, $userid, $emailid=null) {
    global $DB;
    if(is_null($emailid)){
        // Get email instance
        if ( $email = $DB->get_record('email', array('course'=>$courseid))) {
            // Get account for this course
            if ($account = $DB->get_record('email_account', array('userid'=>$userid, 'emailid'=>$email->id))) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }else{
        $thecount = $DB->count_records('email_account', array('userid'=>$userid, 'emailid'=>$emailid));
        return ($thecount>0);
    }
}

function email_get_form_options($email, $mail, $options, $selectedusers, $context){
    $bodyoptions = array('subdirs'=>0, 'maxfiles'=>50, 'maxbytes'=>$email->maxbytes, 'trusttext'=>true, 'context'=>$context);
    $attachmentoptions = array('subdirs'=>0, 'maxfiles'=>50, 'maxbytes'=>$email->maxbytes, 'context'=>$context);
    
    $itemid = null;  //being set to NULL creates a new entry
    $mail = file_prepare_standard_filemanager($mail, 'attachments', $attachmentoptions, $context, 'mod_email', 'attachments', $itemid);
    
    $itemid = isset($mail->bodyitemid)?$mail->bodyitemid:null;
    $mail = file_prepare_standard_editor($mail, 'body', $bodyoptions, $context, 'mod_email', 'body', $itemid);
    $formoptions = array('email'=>$email
                        , 'options'=>$options
                        , 'selectedusers'=>$selectedusers
                        , 'attachmentoptions'=>$attachmentoptions
                        , 'bodyoptions'=>$bodyoptions
                        );
    return $formoptions;
}

/**
 * This functions print form, who it's necessary for sent mail
 *
 * @uses $CFG
 * @param object $email Email instance
 * @param object $mail  Mail fields when it's insert error
 * @param object $options Options
 * @param Array $selectedusers Users who sent mail.
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_newmailform($email, $formdefaults, $options, $selectedusers, $context) {

	global $CFG;
        
	// Errors
        $errmsg = '';
	if ( $formdefaults->error!=0 ) {
            if ($formdefaults->error == EMAIL_NOSUBJECT) {
                $errmsg = get_string('nosubject', 'email');
            }

            if ($formdefaults->error == EMAIL_NOSENDERS) {
                $errmsg = get_string('nosenders', 'email');
            }
	}

        include_once('sendmail_form.php');
        $formoptions = email_get_form_options($email, $formdefaults, $options, $selectedusers, $context);
        $mform = new mod_email_sendmail_form('sendmail.php', $formoptions);
        
        $draftid_file = file_get_submitted_draft_itemid('attachments');
        file_prepare_draft_area($draftid_file, $context->id, 'mod_email', 'attachments', empty($formdefaults->id)?null:$formdefaults->id, $formoptions["attachmentoptions"]);
        
        $draftid_editor = file_get_submitted_draft_itemid('body');
        if ($draftid_editor==0) {
            $draftid_editor = $formdefaults->bodyitemid;
        }
        $formdefaults->body_editor['text'] = file_prepare_draft_area($draftid_editor, $context->id, 'mod_email', 'body', null, $formoptions["bodyoptions"], $formdefaults->body);
        $formdefaults->body_editor['itemid'] = $formdefaults->bodyitemid;
        $formdefaults->body_editor['format'] = $formdefaults->bodyformat;
        $formdefaults->body = $formdefaults->body_editor;
        $mform->set_data($formdefaults);
        
        notify($errmsg);
        $mform->display();

	return true;
}

/**
 * This function print formated users to send mail ( This had choosed before )
 *
 * @uses $CFG
 * @param Array $users Users to print.
 * @param boolean $nosenders No users choose (error log)
 * @todo Finish documenting this function
 */
function email_print_users_to_send( $users, $nosenders=false, $options=NULL ) {

    global $CFG,$DB,$OUTPUT;

    if ( $options ) {
        $url = email_build_url($options);
    }

    // Add links to add cc and bcc textarea.
    $urltoaddcc = '<a id="urlcc" href="javascript:addC(\'cc\')">'.get_string('addcc', 'email').'</a>';
    $urltoaddbcc = '<a id="urlbcc" href="javascript:addC(\'bcc\');">'.get_string('addbcc', 'email').'</a>';

    $javascript = '
<script type="text/javascript" language="JavaScript">
<!--
    function addC(tipo) {
        var d = document.getElementById("fortextarea"+tipo);

        var d1 = document.getElementById("button"+tipo);

        var textarea = document.createElement("textarea");
        textarea.setAttribute("rows", "3");
        textarea.setAttribute("cols", "65");
        textarea.setAttribute("name", tipo);
        textarea.setAttribute("id", "textarea" + tipo);
        textarea.setAttribute("disabled","true");
        textarea.setAttribute("class","textareacontacts");

        d.appendChild(textarea);

        var b = document.createElement("b");
        var txt;
        if ( tipo == "cc") {
                txt = "'. get_string('cc', 'email').'" ;
        } else {
                txt = "' .get_string('bcc', 'email') . '";
        }

        var node=document.createTextNode(txt+": ");
        b.appendChild(node);
        document.getElementById("td"+tipo).appendChild(b);

        var rm = document.getElementById("url"+tipo);
        document.getElementById("url").removeChild(rm);

        var rm1 = document.getElementById("urltxt");
        if ( rm1 ) {
            document.getElementById("url").removeChild(rm1);
        }
    }
-->
</script>';


    echo '<tr valign="middle">';
    echo '  <td class="legendmail">';
    echo '      <b>'.get_string('for', 'email'). ' : </b>';
    echo '  </td>';
    echo '  <td class="inputmail">';

    if ( ! empty ( $users ) ) {

    	echo '<div id="to">';

    	foreach ( $users as $userid ) {
    		echo '<input type="hidden" value="'.$userid.'" name="to[]" />';
    	}

    	echo '</div>';

    	echo '<textarea id="textareato" class="textareacontacts" name="to" cols="65" rows="3" disabled="true" multiple="multiple">';

    	foreach ( $users as $userid ) {
            echo fullname($DB->get_record('user', array('id'=>$userid))).', ';
    	}

    	echo '</textarea>';
    } else {
    	echo '<div id="to"></div>';
    	echo '<textarea id="textareato" class="textareacontacts" name="to" cols="65" rows="3" disabled="true"></textarea>';
    }

    echo '  </td>';
    echo '  <td class="extrabutton" style="text-align:left;">';

    $label = get_string('participants', 'email');
    /*link_to_popup_window(   '/mod/email/participants.php?'.$url
                            , 'participants'
                            , $label.' ...'
                            , 470
                            , 520
                            , $label
                        );
     */
    $link = new moodle_url ('/mod/email/participants.php?'.$url);
    echo $OUTPUT->action_link($link, $label, new popup_action('click', $link, 'post', array("height"=>"570")));

    // Display errors
    if ($nosenders) {
        echo "<br/>".$OUTPUT->error_text($nosenders); //formerr($nosenders);
        echo '<script type="text/javascript" language="JavaScript"> window.document.sendmail.to.focus(); </script>';
    }
    echo '  </td>';
    echo '</tr>';
    echo '<tr valign="middle">';
    echo '  <td class="legendmail">';
    echo '      <div id="tdcc"></div>';
    echo '  </td>';
    echo '  <td>'.$javascript.'<div id="fortextareacc"></div>';
    echo '      <div id="cc"></div>';
    echo '      <div id="url">'.$urltoaddcc.'<span id="urltxt">&#160;|&#160;</span>'.$urltoaddbcc.'</div>';
    echo '  </td>';
    echo '  <td><div id="buttoncc"></div></td>';
    echo '</tr>';
    echo '<tr valign="middle">';
    echo '  <td class="legendmail">';
    echo '      <div id="tdbcc"></div>';
    echo '  </td>';
    echo '  <td>';
    echo '      <div id="fortextareabcc"></div>';
    echo '      <div id="bcc"></div>';
    echo '  </td>';
    echo '  <td><div id="buttonbcc"></div></td>';
    echo '</tr>';

}

/**
 * This function show all participants of this course. Choose user/s to sent mail.
 *
 * @uses $CFG
 * @param Object $email Email instance
 * @param Object $options Options
 * @return Array Users to sending mail.
 * @todo Finish documenting this function
 */
function email_choose_users_to_send($email, $options) {

    global $CFG,$DB;

    $selectedusers = array();

    // Get contacts
    $context = get_context_instance(CONTEXT_COURSE, $email->course);
    $teachers = get_enrolled_users($context, "moodle/course:viewhiddenactivities");
    $teacherids = array_keys($teachers);
    $enrolled_users = get_enrolled_users($context);
    if ($enrolled_users) {
        foreach($enrolled_users as $userid=>$user){
            if(in_array($userid, $teacherids)){
                unset($enrolled_users[$userid]);
                $unselectedusers[$user->id] = "# ".fullname($user, true);
            }
        }
        
        foreach ($enrolled_users as $user) {
            if ( ! email_contains(fullname($user, true), $selectedusers) ) {
            	$unselectedusers[$user->id] = fullname($user, true);
            }
        }
        unset($enrolled_users);
    }

    // Prepare tags
    $straddusersto  = get_string('addusersto', 'email');
    $stradduserscc = get_string('cc', 'email');
    $straddusersbcc = get_string('bcc', 'email');
    $stradd = get_string('ok');
    $strto = get_string('to', 'email');
    $strcc = get_string('cc', 'email');
    $strbcc = get_string('bcc', 'email');
    $strselectedusersremove = get_string('selectedusersremove', 'email');
    $straction = get_string('selectaction', 'email');
    $strcancel = get_string('cancel');

    // Prepare url
    $toform = email_build_url($options, true);-

    $url = $CFG->wwwroot.'/mod/email/view.php';

    if ( $options ) {
        $urlhtml = email_build_url($options);
    }

    include_once('participants.html');

}

/**
 * This function return true or false if barn contains needle.
 *
 * @param string Needle
 * @param Array Barn
 * @return boolean True or false if barn contains needle
 * @todo Finish documenting this function
 */
function email_contains( $needle, $barn) {

	// If not empty ...
	if ( ! empty ( $barn ) ) {
		// search string
		foreach ( $barn as $straw ) {
			if ( $straw == $needle ) {
				return true;
			}
		}
	}

	return false;

}

/**
 * This funcion assign default line on reply or forward mail
 *
 * @param object $user User
 * @param int $date Date on write mail
 * @return string Default line
 * @todo Finish documenting this function
 */
function email_make_default_line_replyforward($user, $date) {

	$line = get_string('on', 'email').' '. userdate($date). ', '.fullname($user).' '. get_string('wrote', 'email') . ': <br />';

	return $line;
}

/**
 * This function return form for include original attachments
 *
 * @param object $mail Mail source of attachments
 * @param boolean $checked Check all inputs
 * @return string Form to include this attachments
 * @todo Finish documenting this function
 */
function email_prepare_add_old_attachments($mail, $checked=false) {

	$form = '';

	// Get mail attachments
	$attachments = email_get_attachments($mail);

	if ( $attachments ) {

		// Checked inputs
		$check = '';
		if ( $checked ) {
			$check = 'checked';
		}

		$i = 0;
		foreach ($attachments as $attachment) {

			if ( $i > 0 ) {
				$form .= '<div>';
			}
			$form .= '<input type="checkbox" name="oldattachment'.$i.'" value="'.$attachment->path.'/'.$attachment->name.'" onchange="document.getElementById(\'moreUploadsLink\').style.display = \'block\';" '.$check.' />
						'. $attachment->name;

			if ( $i > 0 ) {
				$form .= '</div>';
			}
			$i++;
		}
	}

	return $form;
}

/**
 * 
 */
function email_modify_body_for_reply($mail, $user){
    $maxCharInLine = 100;
    $l = strlen($mail->body);
    $i = 0;
    $c = 0;
    while($i < $l){
        if(substr_compare($mail->body, "&gt;", $i, 4)===0){
            $i += 4;
            //move to end of consecutive >'s
            while(substr_compare($mail->body, "&gt;", $i, 4)===0){
                $i += 4;
            }
            
            //insert another >
            $body = substr($mail->body, 0 , $i);
            $body.= "&gt;";
            $body.= substr($mail->body, $i);
            $mail->body = $body;
            $i += 4;
            $c = 0;
            $l = strlen($mail->body);
            
        }elseif(substr_compare($mail->body, "<p>", $i, 3)===0){
            $i += 3;
            //move to end of consecutive >'s
            while(substr_compare($mail->body, "&gt;", $i, 4)===0){
                $i += 4;
            }
            
            //insert another >
            $body = substr($mail->body, 0 , $i);
            $body.= "&gt;";
            $body.= substr($mail->body, $i);
            $mail->body = $body;
            $i += 4;
            $c = 0;
            $l = strlen($mail->body);
            
        }elseif(substr_compare($mail->body, "<br />", $i, 6)===0){
            $i += 6;
            $c = 0;
            
        }else{
            $c++;
            if($c > $maxCharInLine){
                //If char is not a whitespace back up until you find one.
                while($mail->body[$i]!=' ' && $i>0){
                    $i--;
                }
                $body = substr($mail->body, 0 , $i);
                $body.= "<br />&gt;";
                $body.= substr($mail->body, $i);
                $mail->body = $body;
                $i += 10;
                $c = 0;
                $l = strlen($mail->body);
            }else{
                $i++;
            }
        }
    }
    $mail->body = "<br/>\n<br/>\n".email_make_default_line_replyforward($user, $mail->timecreated)."\n".$mail->body;
    return $mail->body;
}

/**
 * This function prepare new mail for reply to another mail
 *
 * @param int $mailid Mail ID to reply
 * @param object $options General params
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 */
function email_reply($mailid, $options, $context) {
    global $DB, $CFG;
    
    // Get mail
    if ( ! $mail = $DB->get_record('email_mail', array('id'=>$mailid))) {
        print_error('nomailid','email');
    }

    // Get account
    if (! $account = email_get_account_by_id($options->a) ) {
        print_error('noaccount', 'email');
    }

    // Is required in sendmail_form.php
    if (! $email = $DB->get_record('email', array('id'=>$account->emailid))) {
        print_error("nocourseemail", "email");
    }

    // Predefinity user send
    $userwriter = email_get_user($mailid);
    $selectedusers[] = $userwriter->id;
    $mail->textareato = fullname($userwriter);

    // Modify subject
    $mail->subject = get_string('re', 'email').' '.$mail->subject;

    //Modify Body
    $mail->body = email_modify_body_for_reply($mail, $userwriter);

    include_once('sendmail_form.php');
    $formoptions = email_get_form_options($email, $mail, $options, $selectedusers, $context);
    $mform = new mod_email_sendmail_form('sendmail.php', $formoptions);
    
    $mail->body = $mail->body_editor;    
    $mform->set_data($mail);
    $mform->display();
    
    return true;
}

/**
 * This function prepare new mail for reply all people recived and sender it
 *
 * @param int $mailid Mail ID to reply
 * @param object $options General params
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 */
function email_replyall($mailid, $options, $context) {

    global $DB, $CFG, $USER;

    // Get mail
    if ( ! $mail = $DB->get_record('email_mail', array('id'=>$mailid))) {
            print_error('nomailid', 'email');
    }

    // Get account
    if (! $account = email_get_account_by_id($options->a) ) {
            print_error('noaccount', 'email');
    }

    // Is required in sendmail_form.php
    if (! $email = $DB->get_record('email', array('id'=>$account->emailid))) {
            print_error('nocourseemail','email');
    }

    // Predefinity user send
    $userwriter = email_get_user($mailid);

    // First, prepare writer
    $selectedusers[] = $userwriter->id;

    // Get users sent mail, with option for reply all
    $arrTo = email_get_userids_sent($mailid, "to", array($USER->id));
    $arrCc = email_get_userids_sent($mailid, "cc", array($USER->id));
    $selectedusers = array_merge( $selectedusers, $arrTo, $arrCc);
    $mail->textareato = "";
    foreach($selectedusers as $k=>$userid){
        $user = $DB->get_record('user',array('id'=>$userid));
        $mail->textareato .= fullname($user).", ";
    }
    $mail->textareato = substr($mail->textareato, 0, -2);

    // Modify subject
    $mail->subject = get_string('re', 'email').' '.$mail->subject;

    //Modify Body
    $mail->body = email_modify_body_for_reply($mail, $userwriter);

    include_once('sendmail_form.php');
    $formoptions = email_get_form_options($email, $mail, $options, $selectedusers, $context);
    $mform = new mod_email_sendmail_form('sendmail.php', $formoptions);
    
    $mail->body = $mail->body_editor;
    $mform->set_data($mail);
    $mform->display();
    
    return true;
}

/**
 * This function prepare new mail for forward
 *
 * @param int $mailid Mail ID to forward
 * @param object $options General params
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 */
function email_forward($mailid, $options, $context) {
    global $DB, $CFG;
    
    // Get mail
    if ( ! $mail = $DB->get_record('email_mail', array('id'=>$mailid))) {
            print_error('nomailid', 'email');
    }

    // Get account
    if (! $account = email_get_account_by_id($options->a) ) {
            print_error('noaccount', 'email');
    }

    // Is required in sendmail_form.php
    if (! $email = $DB->get_record('email', array('id'=>$account->emailid))) {
            print_error('nocourseemail','email');
    }

    // Predefinity user send
    $user = email_get_user($mailid);

    // Modify subject
    $mail->subject = get_string('fw', 'email').' '.$mail->subject;

    // Modify Body
    $mail->body = email_modify_body_for_reply($mail, $user);

    // Add old attachments
//    if ( email_has_attachments($mail) ) {
//            $formoldattachments = email_prepare_add_old_attachments($mail);
//    }

    $options->action = 'forward';
    $selectedusers = array();
    include_once('sendmail_form.php');
    $formoptions = email_get_form_options($email, $mail, $options, $selectedusers, $context);
    $mform = new mod_email_sendmail_form('sendmail.php', $formoptions);
    
    //Form processing and displaying is done here
    $mail->body = $mail->body_editor;
    $mform->set_data($mail);
    $mform->display();
    
    return true;
}

/**
 * This function prepare draft mail for sending
 *
 * @param int $mailid Mail ID to forward
 * @param object $options General params
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 */
function email_draftmailform($mailid, $options) {
    global $DB, $CFG;
    
	// Get mail
	if ( ! $mail = $DB->get_record('email_mail', array('id'=>$mailid))) {
		print_error('nomailid', 'email');
	}

	// Get account
	if (! $account = email_get_account_by_id($options->a) ) {
                print_error('noaccount', 'email');
	}

	// Is required in sendmail_form.php
	if (! $email = $DB->get_record('email', array('id'=>$account->emailid))) {
		print_error('nocourseemail','email');
	}

	include_once('sendmail_form.php');
        $selectedusers = array();
        $formoptions = email_get_form_options($email, $mail, $options, $selectedusers);
        $mform = new mod_email_sendmail_form('sendmail.php', $formoptions);

        //Form processing and displaying is done here
        $mail->body = $mail->body_editor;
        $mform->set_data($mail);
        $mform->display();
    
	return true;
}

/**
 * This function remove mails of account
 *
 * @uses $CFG
 * @param object $mailids All mails who it's removed
 * @param int $account Account to remove mails
 * @param object $options Options for url
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_removemail($mailids, $accountid, $options) {

	global $CFG,$DB;

	// First, show if folder remove or not

	$deletemails = false;
	$success = true;

	if ($options) {
		// Filter by folder?
		if ( $options->folderoldid != 0 ) {

			// Get folder
			$folder = email_get_folder($options->folderoldid);

			if ( email_isfolder_type($folder, EMAIL_TRASH) ) {
				$deletemails = true;
			}
		}

		// If no folder's id's, it is inbox
		if ( $options->folderid != 0 ) {
			$folder = email_get_folder($options->folderid);
		} else {
			$folder = email_get_root_folder($accountid, EMAIL_INBOX);
		}
	}

	if ($mailids) {

		// Get account
		if (! $account = email_get_account_by_id($accountid) ) {
			print_error('noaccount', 'email');
		}

		foreach ( $mailids as $mailid ) {

			// If delete definity mails ...
			if ( $deletemails ) {
				// Delete reference mail for this account
				$folder = email_get_folder($options->folderoldid);
				
				if ($folder) {
					if (! $DB->delete_records('email_foldermail', array('folderid'=>$folder->id, 'mailid'=>$mailid))) {
					   	$success = false;
					}
				}
				/*if (! $DB->delete_records('email_send', array('mailid'=>$mailid, 'accountid'=>$account->id))) {
				    	return false;
				}*/
			} else {
				// Get remove folder account
				$removefolder = email_get_root_folder($account->id, EMAIL_TRASH);

				if ( $options->folderoldid != 0 ) {
					// Get actual folder
					$actualfolder = email_get_reference2foldermail($mailid, $options->folderoldid);
				} else {
					// Inbox
					$actualfolder = email_get_reference2foldermail($mailid, $folder->id);
				}

				if ($actualfolder) {
					// Move mails to trash
					if (! email_move2folder($mailid, $actualfolder->id, $removefolder->id) ) {
						$success = false;
					}
				} else {
					$success = false;
				}
			}

		}
	}

	$url = email_build_url($options);

	// Notify
	if ( $success ) {
    	notify( get_string('removeok', 'email') );
	} else {
		notify( get_string('removefail', 'email') );
	}

	return true;
}

/**
 * This function clean trash of account
 *
 * @param int $accountid Account to remove mails
 * @param object $options Options for url
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_cleantrash($accountid, $options) {
    global $DB;
    
	$trash = email_get_root_folder($accountid, EMAIL_TRASH);

	$success = true;

	// Delete reference mail for this account
	if (! $DB->delete_records('email_foldermail', array('folderid'=>$trash->id))) {
	   	$success = false;
	}

	$url = email_build_url($options);

	// Notify
	if ( $success ) {
    	notify( get_string('cleantrashok', 'email') );
	} else {
		notify( get_string('cleantrashfail', 'email') );
	}

	return true;
}

/**
 * This function read folder's to one mail account
 *
 * @uses $CFG;
 * @param object $mail Mail who has get folder
 * @param int $account Account User
 * @return array Folders contains mail
 * @todo Finish documenting this function
 **/
function email_get_foldermail($mailid, $account) {

	global $CFG,$DB;

	// Prepare select
	$sql = "SELECT f.id, f.name, fm.id as foldermail
                   FROM {email_folder} f
                   LEFT JOIN {email_foldermail} fm ON f.id = fm.folderid
                   WHERE fm.mailid = $mailid
                   AND f.accountid = $account
                   ORDER BY f.timecreated";

	// Return value of select
	return $DB->get_records_sql($sql);
}

/**
 * This function read Id to reference mail and folder
 *
 * @param int $mailid Mail ID
 * @param int $folderid Folder ID
 * @return object Contain reference
 * @todo Finish documenting this function
 **/
function email_get_reference2foldermail($mailid, $folderid) {
    global $DB;
    return $DB->get_record('email_foldermail', array('mailid'=>$mailid, 'folderid'=>$folderid));

}

/**
 * This function mark mails to read
 *
 * @uses $CFG
 * @param object $mailids All mails who it's removed
 * @param int $accountid Account to mark read mails
 * @param object $options Options for url
 * @param boolean $printsuccess Print information (per default, yes)
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_mail2read($mailids, $accountid, $options, $printsuccess=true) {

    global $CFG,$DB;

    $success = true;

    // Get account
    if (! $account = email_get_account_by_id($accountid) ) {
        print_error('noaccount', 'email');
    }

    if ($mailids) {
        foreach ( $mailids as $mailid ) {
            // Delete reference mail for this account
            if (! $mail = $DB->set_field('email_send', 'readed', 1, array('mailid'=>$mailid, 'accountid'=>$account->id))) {
                return false;
            }
        }
    } else {
        $success = false;
    }

    // Build url part options
    $url = email_build_url($options);

    if ( $printsuccess ) {
        // Notify
        print('<div class="notifyproblem">'.get_string('toreadok', 'email').'</div>' );
    }

    return $success;
}

/**
 * This function mark mails to unread
 *
 * @uses $CFG
 * @param object $mailids All mails who it's removed
 * @param int $accountid Account to mark unread mails
 * @param object $options Options for url
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_mail2unread($mailids, $accountid, $options) {

    global $CFG,$DB;

    // Get account
    if (! $account = email_get_account_by_id($accountid) ) {
            print_error('noaccount', 'email');
    }

    if ($mailids) {
            foreach ( $mailids as $mailid ) {
                    // Delete reference mail for this account
                    if (! $DB->set_field('email_send', 'readed', 0, array('mailid'=>$mailid, 'accountid'=>$account->id))) {
                            return false;
                    }
            }
    }

    // Build url part options
    $url = email_build_url($options);

    // Notify
    print('<div class="notifyproblem">'.get_string('tounreadok', 'email').'</div>' );

    return true;
}

/**
 * This function move mail to folder indicated.
 *
 * @param int $mailid Mail ID
 * @param int $foldermailid Folder Mail ID reference
 * @param int $folderidnew Folder ID New
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_move2folder($mailid, $foldermailid, $folderidnew) {
    global $DB;

    if (!$folderidnew) {
        return false;
    }
    // Change folder reference to mail
    if (! $DB->set_field('email_foldermail', 'folderid', $folderidnew, array('id'=>$foldermailid, 'mailid'=>$mailid))) {
        return false;
    }

    return true;
}

/**
 * This functions print form to create a new folder
 *
 * @uses $CFG
 * @param object $options Options send form
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_newfolderform($options) {

	global $CFG;

	// Print form
	echo '<form name="newfolder" method="post" action="folder.php" enctype="multipart/form-data">';

	echo '<table class="sitetopic" border="0" cellpadding="5" cellspacing="0" width="100%">
	        <tr valign="top">
	            <td align="right">
	                <b>'.
	                        get_string('namenewfolder', 'email')
	                    .':
	                </b>
	            </td>
	            <td align="left">
	                <input type="text" name="name" size="30" maxlength="60" value="" />
	            </td>
	        </tr>
	        <tr valign="top">
	            <td align="right">
	                <b>'.
	                        get_string('linkto', 'email')
	                    .':
	                </b>
	            </td>
	            <td align="left">';

	// Get account folders
	$folders = email_get_root_folders($options->a, false);

	// Get inbox, there default option on menu
	$inbox = email_get_root_folder($options->a, EMAIL_INBOX);

	// Insert into menu, only name folder
	foreach ($folders as $folder) {
		$menu[$folder->id] = $folder->name;
	}

	// Print choose
	echo html_writer::select($menu, 'parentfolder', $inbox->id, '');

	echo        '</td>
	        </tr>
	        <tr valign="top">
	         	<td align="center" colspan="2">';

	// Add action
	$options->action = 'newfolder';

    // Print submit
    $form = email_build_url($options, true);
    echo $form;

    echo '<input type="submit" value="'. get_string('create') .'" />';

	// Close table
	echo '		</td>
			</tr>
		</table>';

	// End form
	echo '</form>';

	return true;
}

/**
 * This functions created news folders
 *
 * @param object $folder Fields of new folder
 * @param int $parentfolder Parent folder
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_newfolder($folder, $parentfolder) {
    global $DB;

	// Add actual time
	$folder->timecreated = time();

	// Insert record
	if (! $folder->id = $DB->insert_record('email_folder', $folder)) {
		return false;
	}

	// Prepare subfolder
	$subfolder->folderparentid = $parentfolder;
	$subfolder->folderchildid  = $folder->id;

	// Insert record reference
	if (! $DB->insert_record('email_subfolder', $subfolder)) {
		return false;
	}

	return true;
}

/**
 * This function get folder to accountid.
 *
 * @param int $accountid
 * @return object Object contain all folders
 * @todo Finish documenting this function
 **/
function email_get_folders($accountid) {
    global $DB;
    return $DB->get_records('email_folder', array('accountid'=>$accountid));
}

/**
 * This function get folder.
 *
 * @param int $folderid
 * @return object Object contain folder
 * @todo Finish documenting this function
 **/
function email_get_folder($folderid) {
    global $DB;

    $folder = $DB->get_record('email_folder', array('id'=>$folderid));

    // Only change in parent folders
    if (isset($folder->isparenttype) && ! is_null($folder->isparenttype) ) {
            // If is parent ... return language name
            if ( ( email_isfolder_type($folder, EMAIL_INBOX) ) ) {
                    $folder->name = get_string('inbox', 'email');
            }

            if ( ( email_isfolder_type($folder, EMAIL_SENDBOX) ) ) {
                    $folder->name = get_string('sendbox', 'email');
            }

            if ( ( email_isfolder_type($folder, EMAIL_TRASH) ) ) {
                    $folder->name = get_string('trash', 'email');
            }

            if ( ( email_isfolder_type($folder, EMAIL_DRAFT) ) ) {
                    $folder->name = get_string('draft', 'email');
            }
    }

    return $folder;
}

/**
 * This function created for one account the initial folders
 * who are Inbox, Sendbox and Trash
 *
 * @param int $accountid Account ID
 * @return boolean Success/Fail If Success return object which id's
 * @todo Finish documenting this function
 **/
function email_create_parents_folders($accountid) {
    global $DB;
    
	$folder->timecreated = time();
	$folder->accountid	 = $accountid;
	$folder->name		 = get_string('inbox', 'email');
	$folder->isparenttype = EMAIL_INBOX; // Be careful if you change this field

	/// $folders is an object who contain id's of created folders

	// Insert inbox
	if (! $folders->inboxid = $DB->insert_record('email_folder', $folder)) {
		return false;
	}

	// Insert draft
	$folder->name		 = get_string('draft', 'email');
	$folder->isparenttype = EMAIL_DRAFT; // Be careful if you change this field

	if (! $folders->trashid = $DB->insert_record('email_folder', $folder)) {
		return false;
	}

	// Insert sendbox
	$folder->name		 = get_string('sendbox', 'email');
	$folder->isparenttype = EMAIL_SENDBOX; // Be careful if you change this field

	if (! $folders->sendboxid = $DB->insert_record('email_folder', $folder)) {
		return false;
	}

	// Insert trash
	$folder->name		 = get_string('trash', 'email');
	$folder->isparenttype = EMAIL_TRASH; // Be careful if you change this field

	if (! $folders->trashid = $DB->insert_record('email_folder', $folder)) {
		return false;
	}

	return $folders;
}

/**
 * This function remove one folder
 *
 * @uses $CFG
 * @param int $folderid Folder ID
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_removefolder($folderid) {

	global $CFG,$DB;

	// Check if this folder have subfolders
	if ( $DB->get_record('email_subfolder', array('folderparentid'=>$folderid))) {
            // This folder is parent of other/s folders. Don't remove this
            // Notify
            redirect( $CFG->wwwroot.'/mod/email/view.php?action=\'viewmails\'', '<div class="notifyproblem">'.get_string('havesubfolders', 'email').'</div>' );
	}

	// Get folder
	if ($folders =  $DB->get_records('email_folder', array('id'=>$folderid))) {

	    // For all folders . . .
	    foreach($folders as $folder) {

			// Before removing references to foldermail, move this mails to root folder parent.
			if ($foldermails = $DB->get_records('email_foldermail', array('folderid'=>$folder->id)) ) {

				// Move mails
				foreach ( $foldermails as $foldermail ) {
					// Get folder
					if ( $folder = email_get_folder($foldermail->folderid) ) {

						// Get root folder parent
						if ( $parent = email_get_parentfolder($foldermail->folderid) ) {

							// Assign mails it
							email_move2folder($foldermail->mailid, $foldermail->id, $parent->id);
						} else {
							print_error('norootfolder'.'email');
						}
					} else {
                                            print_error('nofolder','email');
					}
				}

			}

			// Delete all subfolders of this
			if (! $DB->delete_records('email_subfolder', array('folderparentid'=>$folder->id))) {
			    	return false;
			}

			// Delete all subfolders of this
			if (! $DB->delete_records('email_subfolder', array('folderchildid'=>$folder->id))) {
			    	return false;
			}

			// Delete all filters of this
			if (! $DB->delete_records('email_filter', array('folderid'=>$folder->id))) {
			    	return false;
			}

			// Delete all foldermail references
			if (! $DB->delete_records('email_foldermail', array('folderid'=>$folder->id))) {
			    	return false;
			}
	    }

	    // Delete all folder associated a this account
	    if (! $DB->delete_records('email_folder', array('id'=>$folderid))) {
		    	return false;
		}
	}

	notify(get_string('removefolderok', 'email'));

	return true;
}

/**
 * This functions prepare form for edit this folder
 *
 * @param int $folderid Folder ID
 * @param object $options Options
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_rename_folder_form($folderid, $options) {

    global $DB;
    
	if (! $folder = $DB->get_record('email_folder', array('id'=>$folderid))) {
		print_error('nofolder', 'email');
	}

	// Print form
	echo '<form name="editfolder" method="post" action="folder.php" enctype="multipart/form-data">';

	echo '<table class="sitetopic" border="0" cellpadding="5" cellspacing="0" width="100%">
	        <tr valign="top">
	            <td align="right">
	                <b>'.
	                        get_string('namenewfolder', 'email')
	                    .':
	                </b>
	            </td>
	            <td align="left">
	                <input type="text" name="name" size="30" maxlength="60" value="'.$folder->name.'" />
	            </td>
	        </tr>';


	echo   '<tr valign="top">
	         	<td align="center" colspan="2">';

	// Add action
	$options->action = 'newfolder';

    // Print submit
    $form = email_build_url($options, true);
    echo $form;

    // Only used in this case
    echo '<input type="hidden" name="oldname" value="'. $folder->name .'" />';

    echo '<input type="submit" value="'. get_string('changeme', 'email') .'" />';

	// Close table
	echo '		</td>
			</tr>
		</table>';

	// End form
	echo '</form>';

	return true;

}

/**
 * This functions change name of one folder
 *
 * @param int $folderid Folder ID
 * @param string $namenew New name of folder
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_rename_folder($folderid, $namenew) {
    global $DB;
	// Change name of folder
	if (! $DB->set_field('email_folder', 'name', $namenew, array('id'=>$folderid)) ) {
	    	return false;
	}

	return true;
}

function email_createfilter($folderid) {

	notice();

	return true;
}

function email_modityfilter($filterid) {
	return true;
}

function email_removefilter($filterid) {
	return true;
}

/**
 * This function prints all mails from account
 *
 * @uses $CFG
 * @param int $accountid Account ID
 * @param string $order Order by ...
 * @param object $options Options for url
 * @param boolean $search When show mails on search
 * @param array $mailssearch Mails who has search
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_showaccountmails($accountid, $order = '', $page=0, $perpage=10, $options=NULL, $search=false, $mailssearch=NULL) {

    global $CFG;
   
    require_once('tablelib.php');

    // Build url part options
    if ($options) {
        $url = email_build_url($options);
    }

    /// Print all mails in this HTML file

    // Should use this variable so that we don't break stuff every time a variable is added or changed.
    $baseurl = $CFG->wwwroot.'/mod/email/view.php?'.$url. '&amp;page='.$page.'&amp;perpage='.$perpage;

    // Print init form from send data
    echo '<form id="sendmail" action="'.$baseurl. '" method="post" name="sendmail"'.(isset($CFG->framename)?' target="'.$CFG->framename.'"':'').'>';
 
    $tablecolumns = array('', 'subject', 'writer', 'timecreated');

    if ( $options->folderid != 0 ) {
            // Get folder
            $folder = email_get_folder($options->folderid);
    } else {
            // solve problem with select an x mails per page for maintein in this folder
            if ( $options->folderoldid != 0 ) {
                    $options->folderid = $options->folderoldid;
                    $folder = email_get_folder($options->folderid);
            }
    }

    // If actual folder is inbox type, ... change tag showing.
    if ( isset($folder) && $folder ) {
            if ( ( email_isfolder_type($folder, EMAIL_INBOX) ) ) {
                    $strto = get_string('from', 'email');
            } else {
                    $strto = get_string('to', 'email');
            }
    } else {
            $strto = get_string('from', 'email');
    }

    $tableheaders = array('', get_string('subject', 'email'), $strto, get_string('date', 'email'));


    $table = new email_flexible_table('list-mails-'.$accountid);

    $table->define_columns($tablecolumns);
    $table->define_headers($tableheaders);
    $table->define_baseurl($baseurl);

    $table->set_attribute('align', 'center');
    $table->set_attribute('width', '100%');

    $table->set_control_variables(array(
            TABLE_VAR_SORT    => 'ssort',
            TABLE_VAR_HIDE    => 'shide',
            TABLE_VAR_SHOW    => 'sshow',
            TABLE_VAR_IFIRST  => 'sifirst',
            TABLE_VAR_ILAST   => 'silast',
            TABLE_VAR_PAGE    => 'spage'
            ));

    $table->sortable(true, 'timecreated', SORT_DESC);

    $table->setup();

    // When no search
    if (! $search) {
        // Get mails
        $mails = email_get_mails($accountid, $table->get_sql_sort(), '', $options, '');
    } else {
        $mails = $mailssearch;
    }

    // Define long page.
    $totalcount = count($mails);
    $table->pagesize($perpage, $totalcount);

    $table->inputs(true);

    if($table->get_page_start() !== '' && $table->get_page_size() !== '') {
        $limitfrom = $table->get_page_start();
        $limitnum = $table->get_page_size();
    }
    else {
        $limitfrom = '';
        $limitnum = '';
    }

    // Now, re-getting emails, apply pagesize (limit)
    if (! $search) {
        // Get mails
        $mails = email_get_mails($accountid, $table->get_sql_sort(), $limitfrom, $options, $limitnum);
    }

    if (! $mails ) {
        $mails = array();
    }


    $mailsids = email_get_ids($mails);
    $cm = get_coursemodule_from_id('email', $options->id);
    
    // Print all rows
    foreach ($mails as $mail) {

        $attribute = array();

        if ( isset($folder) && $folder ) {
            if ( email_isfolder_type($folder, EMAIL_SENDBOX) ) {
                $struser = email_get_user_for_sendbox($mail->id);
            } else if ( email_isfolder_type($folder, EMAIL_INBOX) ) {

                // Get writer
                if ( ! $mail->writer ) {
                        $mail->writer = $mail->accountid;	// Thanks Sergio
                }

                $struser = email_get_user_for_inbox($mail->writer);

                if (! email_readed($mail->id, $accountid) ) {
                    $attribute = array( 'bgcolor' => '#CCCCCC', 'style' => 'font-weight:bold');
                }

            } else if ( email_isfolder_type($folder, EMAIL_TRASH) ){

                $struser = email_get_user_for_inbox($mail->writer);

                if (! email_readed($mail->id, $accountid) ) {
                    $attribute = array( 'bgcolor' => '#CCCCCC', 'style' => 'font-weight:bold');
                }
                
            } else if ( email_isfolder_type($folder, EMAIL_DRAFT) ) {

                $struser = '';

                if (! email_readed($mail->id, $accountid) ) {
                    $attribute = array( 'bgcolor' => '#CCCCCC', 'style' => 'font-weight:bold');
                }

            } else {

                    $struser = email_get_user_for_inbox($mail->writer);

                    if (! email_readed($mail->id, $accountid) ) {
                        $attribute = array( 'bgcolor' => '#CCCCCC', 'style' => 'font-weight:bold');
                    }
            }
            
        } else {
            // Format user's
            $struser = email_get_user_for_inbox($mail->writer);

            if (! email_readed($mail->id, $accountid) ) {
                $attribute = array( 'bgcolor' => '#CCCCCC', 'style' => 'font-weight:bold');
            }
        }

        if (isset($folder) && email_isfolder_type($folder, EMAIL_DRAFT) ) {
                $urltosent = '<a href="'.$CFG->wwwroot.'/mod/email/view.php?id='.$options->id.'&amp;a='.$options->a.'&amp;action=\'draftmail\'&amp;mailid='.$mail->id.'">'.$mail->subject.'</a>';
        } else {
                $urltosent = '<a href="'.$CFG->wwwroot.'/mod/email/view.php?id='.$options->id.'&amp;a='.$options->a.'&amp;action=\'viewmail\'&amp;mailid='.$mail->id.'&amp;folderid='.$options->folderid.'&amp;mails='.$mailsids.'">'.$mail->subject.'</a>';
        }

        $attachment_icon = '';
        if (email_has_attachments($mail, $cm) ) {
            $attachment_icon = '<img src="'.$CFG->wwwroot.'/mod/email/pix/clip.gif" alt="attachment" /> ';
        }
        
        $table->add_data(array('<input id="mail" type="checkbox" name="selectedmailids[]" value="'.$mail->id.'" />'
                                , $attachment_icon.$urltosent
                                , $struser
                                , userdate($mail->timecreated)
                            )
                            , $attribute
                        );

        // Save previous mail
       	$previousmail = $mail->id;
    }

    //$folder = email_get_root_folder($accountid, $folder)

    $foldername = (isset($folder)) ? $folder->name : "Inbox";
    echo "<div style='text-align: center;font-weight:bold;font-size: 1.3em;'>".$foldername."</div>";
    $table->print_html();



    // Print select action, if have mails
    if ( $mails ) {
        email_print_select_options($options, $perpage);
    }

    // End form
    echo '</form>';

    return true;
}

/**
 * This functions return if mail has attachments
 *
 * @param object $mail Mail
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_has_attachments($mail, $cm) {
    global $CFG; 
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_email', 'attachments', $mail->id);
    if(count($files)>1){
        return true;
    }
    
    $files = $fs->get_area_files($context->id, 'mod_email', 'body', $mail->id);
    return (count($files)>1);
}

function email_delete_attachments($id, $cm) {
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $fs->delete_area_files($context, 'mod_email', 'attachment', $id);
    $fs->delete_area_files($context, 'mod_email', 'body', $id);
}

/**
 * This functions prints tabs options
 *
 * @uses $CFG
 * @param object $options Hidden options to send in form
 * @param string $action  Actual action
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 */
function email_print_tabs_options($options, $action) {

 	global $CFG;

 	// Build url part options
 	$url = email_build_url($options);

 	// Declare tab array
 	$tabrow = array();

 	// Tab for writting new email
	$tabrow[] = new tabobject('newmail',   $CFG->wwwroot.'/mod/email/view.php?'.$url .'&amp;action=newmail',   get_string('newmail', 'email') );
	$tabrow[] = new tabobject('newfolderform', $CFG->wwwroot.'/mod/email/view.php?'.$url .'&amp;action=\'newfolderform\'', get_string('newfolderform', 'email') );

	/// FUTURE: Implement filters
	//$tabrow[] = new tabobject('newfilter', $CFG->wwwroot.'/mod/email/view.php?'.$url .'&amp;action=\'newfilter\'', get_string('newfilter', 'email') );

	$tabrows = array($tabrow);

	// Print tabs, and if it's in case, selected this
	switch($action)
	{
		case 'newmail':
			  print_tabs($tabrows, 'newmail');
			break;
	    case 'newfolderform':
			  print_tabs($tabrows, 'newfolderform');
			break;
		case 'newfilter':
			  print_tabs($tabrows, 'filter');
			break;
	    default:
			  print_tabs($tabrows);
	}

	return true;
 }


/// SQL funcions



/**
 * This function get write mails from account
 *
 * @param int $accountid Account ID
 * @param string $order Order by ...
 * @return object Contain all write mails
 * @todo Finish documenting this function
 **/
function email_get_my_writemails($accountid, $order = NULL) {
    global $DB;
    
	// Get my write mails
	if ($order) {
		$mails = $DB->get_records('email_mail', array('accountid'=>$accountid), $order);
	} else {
		$mails = $DB->get_records('email_mail', array('accountid'=>$accountid));
	}

	return $mails;
}

/**
 * This function get mails from account.
 *
 * @uses $CFG
 * @param int $accountid Account ID
 * @param string $sort Order by ...
 * @param string $limitfrom row to start the returned set from
 * @param object $options Options from get
 * @param string $limitnum the number of rows to return
 * @return object Contain all send mails
 * @todo Finish documenting this function
 **/
function email_get_mails($accountid, $sort = NULL, $limitfrom = '', $options = NULL, $limitnum = '') {

    global $CFG, $DB;

    // For apply order, I've writting an sql clause
    $sql = "SELECT m.id, m.accountid as writer, m.subject, m.timecreated, m.body";
    $sql.= " FROM {email_mail} m";
    
    if ( $options ) {
        // Filter by folder?
        if ( $options->folderid != 0 ) {
            // Get folder
            $folder = email_get_folder($options->folderid);
        } else {
            /// If folder == 0, get the inbox
            $folder = email_get_root_folder($accountid, EMAIL_INBOX);    
        }
    } else {
        /// If no options, get the inbox
        $folder = email_get_root_folder($accountid, EMAIL_INBOX);
    }

    $sql .= " JOIN {email_foldermail} fm";
        $sql .= " ON m.id = fm.mailid ";
        $sql .= " AND fm.folderid = $folder->id ";
    $sql .= " JOIN {email_folder} f";
        $sql .= " ON f.id = fm.folderid ";
        $sql .= " AND f.accountid = $accountid ";
    
    if ($sort) {
        $sql .=  ' ORDER BY '.$sort;
    } else {
        $sql .=  ' ORDER BY m.timecreated';
    }

    $ret = $DB->get_records_sql($sql, array(), $limitfrom, $limitnum);
    return $ret;
}

/**
 * This function return success/fail if folder corresponding with this type.
 *
 * @param object $folder Folder Object
 * @param string $type Type folder
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_isfolder_type($folder, $type) {

	if ( $folder->isparenttype ) {
		return ($type == $folder->isparenttype);
	} else {

		// Get first parent
		$parentfolder = email_get_parent_folder($folder);

		// Return value
		return ( $parentfolder->isparenttype == $type );
	}

	return false;

}

/**
 * This function return folder parent.
 *
 * @param object $folder Folder
 * @return object Contain parent folder
 * @todo Finish documenting this function
 **/
function email_get_parent_folder($folder) {
    global $DB;

    if (!$subfolder = $DB->get_record('email_subfolder', array('folderchildid'=>$folder->id))) {
        return false;
    }

    return $DB->get_record('email_folder', array('id'=>$subfolder->folderparentid));

}

/**
 * This functions add mail, and active the corresponding flag to user sent.
 * Add new mail in table.
 * Add all references in table send.
 *
 * @param object $mail Mail fields
 * @param array $usersto  Users Id to sent mail, type to
 * @param array $userscc  Users Id to sent mail, type cc
 * @param array $usersbcc  Users Id to sent mail, type bcc
 * @param object $attachments Attachments of mail
 * @param int $mailid If mail exists in bbdd.
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_add_new_mail($mail, $usersto, $userscc, $usersbcc, $mailid, $context, $attachmentoptions, $bodyoptions) {
    global $DB;
    
    $draftid_editor = $mail->body["itemid"];
    $mail->body = $mail->body["text"];
    
    if (! $mailid ) {
        $mail->timecreated = time();

        if (! $mail->id = $DB->insert_record('email_mail', $mail)) {
            return false;
        }
    } else {

        // First, update record
        $mail->id = $mailid;
        if (! $DB->update_record('email_mail', $mail)) {
            notify(' Fail updating mail.');
        }

        if (! $mail = $DB->get_record('email_mail', array('id'=>$mailid))) {
            print_error('nomailid', 'email');
        }
    }

    if (! email_reference_mail_folder($mail, EMAIL_SENDBOX) ) {
    	return false;
    }

    // If mail has saved in draft, delete this reference.
    if ( $folderdraft = email_get_root_folder($mail->accountid, EMAIL_DRAFT) ) {
	    if ($foldermail = email_get_reference2foldermail($mailid, $folderdraft->id) ) {
	    	if (! $DB->delete_records('email_foldermail', array('id'=>$foldermail->id))) {
	    		print_error('removedraftfail', 'email');
	    	}
	    }
    }

    // Get an account object
    if (! $account = email_get_account_by_id($mail->accountid) ) {
        print_error('noaccount', 'email');
    }

    // Get an email object
    if (! $email = $DB->get_record('email', array('id'=>$account->emailid))) {
        print_error( 'nocourseemail','email');
    }

    // Add attachments
    file_save_draft_area_files($mail->attachments, $context->id, 'mod_email', 'attachments', $mail->id, $attachmentoptions);
    if(!empty($draftid_editor)){
        $mail->body = file_save_draft_area_files($draftid_editor, $context->id, 'mod_email', 'body', $mail->id, $bodyoptions, $mail->body);
        $DB->set_field('email_mail', 'body', $mail->body, array('id'=>$mail->id));
    }
    
    // Prepare send mail
    $send = new stdClass();
    $send->accountid = $mail->accountid;
    $send->mailid	 = $mail->id;
    $send->readed	 = 0;

    if (! empty($usersto) ) {

            // Insert mail into send table, for all senders users.
            foreach ( $usersto as $userid ) {

                    // If have account user
                    if ( email_have_account($email->course,$userid) ) {
                            // Get an account user
                            if (! $account = $DB->get_record('email_account', array('userid'=>$userid, 'emailid'=>$email->id))) {
                                    return false;
                            }

                            $send->accountid = $account->id;

                            $send->type		 = 'to';

                            if (! $DB->insert_record('email_send', $send)) {
                                    print_error('sendfail', 'email');
                                    return false;
                    }

                    // Modify mail, to have reference to new accountid user
                    $mail->accountid = $account->id;

                    if (! email_reference_mail_folder($mail, EMAIL_INBOX) ) {
                            return false;
                        }
                    } else {
                            // This user no has account in this course
                            notify(get_string('noaccount', 'email', fullname($DB->get_record('user', array('id'=>$userid)))));
                            return false;
                    }
            }
    }

    if (! empty($userscc) ) {

            // Insert mail into send table, for all senders users.
            foreach ( $userscc as $userid ) {

                    // If have account user
                    if ( email_have_account($email->course,$userid) ) {
                            // Get an account user
                            if (! $account = $DB->get_record('email_account', array('userid'=>$userid, 'emailid'=>$email->id))) {
                                    return false;
                            }

                            $send->accountid = $account->id;

                            $send->type		 = 'cc';

                            if (! $DB->insert_record('email_send', $send)) {
                                    print_error('sendfail', 'email');
                                    return false;
                    }

                    // Modify mail, to have reference to new accountid user
                    $mail->accountid = $account->id;

                    if (! email_reference_mail_folder($mail, EMAIL_INBOX) ) {
                            return false;
                        }
                    } else {
                            // This user no has account in this course
                            notify(get_string('noaccount', 'email', fullname($DB->get_record('user', array('id'=>$userid)))));
                            return false;
                    }
            }
    }

    if (! empty($usersbcc) ) {

            // Insert mail into send table, for all senders users.
            foreach ( $usersbcc as $userid ) {
                    // If have account user
                    if ( email_have_account($email->course,$userid) ) {
                            // Get an account user
                            if (! $account = $DB->get_record('email_account', array('userid'=>$userid, 'emailid'=>$email->id))) {
                                    return false;
                            }

                            $send->accountid = $account->id;

                            $send->type		 = 'bcc';

                            if (! $DB->insert_record('email_send', $send)) {
                                    print_error('sendfail', 'email');
                                    return false;
                    }

                    // Modify mail, to have reference to new accountid user
                    $mail->accountid = $account->id;

                    if (! email_reference_mail_folder($mail, EMAIL_INBOX) ) {
                            return false;
                        }
                    } else {
                            // This user no has account in this course
                            notify(get_string('noaccount', 'email', fullname($DB->get_record('user', array('id'=>$userid)))));
                            return false;
                    }
            }
    }

    return $mail->id;
}

/**
 * This functions add mail in user draft folder.
 * Add new mail in table.
 * Add all references in table send.
 *
 * @param object $mail Mail fields
 * @param object $attachments Attachments of mail
 * @param int $mailid Old mail ID
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_add_new_mail_in_draft($mail, $attachments, $mailid=NULL) {
    global $DB;
    
	$mail->timecreated = time();

	if (! $mailid ) {
		if (! $mail->id = $DB->insert_record('email_mail', $mail)) {
			return false;
    	}

    	if (! email_reference_mail_folder($mail, EMAIL_DRAFT) ) {
    		print_error('draftfail','email');
    	}

	} else {
		$mail->id = $mailid;
		if (! $DB->update_record('email_mail', $mail)) {
			notify(' Fail updating draft mail ');
		}
	}

	// Get an account object
	if (! $account = email_get_account_by_id($mail->accountid) ) {
            print_error('noaccount','email');
    }

	// Get an email object
	if (! $email = $DB->get_record('email', array('id'=>$account->emailid))) {
            print_error( 'nocourseemail','email');
    }

    // Add attachments
    if ($attachments) {
		if (! email_add_attachments($mail->id, $attachments, $email) ) {
			notify('Fail uploading attachments');
		}
    }

	return $mail->id;
}

/**
 * This function insert reference mail <-> folder. There apply filters.
 *
 * @param object $mail Mail
 * @param string $foldername Folder name
 * @return object Contain all users object send mails
 * @todo Finish documenting this function
 **/
function email_reference_mail_folder($mail, $foldername) {
    global $DB;
    
	$foldermail->mailid = $mail->id;

	$folder = email_get_root_folder($mail->accountid, $foldername);

	$foldermail->folderid = $folder->id;

	// Insert into inbox user account
	if (! $DB->insert_record('email_foldermail', $foldermail)) {
		return false;
	}

	return true;

}

/**
 * This function return folder parent with it.
 *
 * @param int $accountid Account ID
 * @param string $folder Folder
 * @return object Contain parent folder
 * @todo Finish documenting this function
 **/
function email_get_root_folder($accountid, $folder) {
    global $DB;
    
	if ( $folder == EMAIL_INBOX ) {
		$rootfolder = $DB->get_record('email_folder', array('accountid'=>$accountid, 'isparenttype'=>EMAIL_INBOX));
		$rootfolder->name = get_string('inbox', 'email');
		return $rootfolder;
	}

	if ( $folder == EMAIL_SENDBOX ) {
		$rootfolder = $DB->get_record('email_folder', array('accountid'=>$accountid, 'isparenttype'=>EMAIL_SENDBOX));
		$rootfolder->name = get_string('sendbox', 'email');
		return $rootfolder;
	}

	if ( $folder == EMAIL_TRASH ) {
		$rootfolder = $DB->get_record('email_folder', array('accountid'=>$accountid, 'isparenttype'=>EMAIL_TRASH));
		$rootfolder->name = get_string('trash', 'email');
		return $rootfolder;
	}

	if ( $folder == EMAIL_DRAFT ) {
		$rootfolder = $DB->get_record('email_folder', array('accountid'=>$accountid, 'isparenttype'=>EMAIL_DRAFT));
		$rootfolder->name = get_string('draft', 'email');
		return $rootfolder;
	}

	return false;

}

/**
 * This function return root folders parent with it.
 *
 * @param int $accountid Account ID
 * @param boolean $draft Add draft folder
 * @return array Contain all parents folders
 * @todo Finish documenting this function
 **/
function email_get_root_folders($accountid, $draft=true) {

	$folders[] = email_get_root_folder( $accountid, EMAIL_INBOX);

	// Include return draft folder
	if ( $draft ) {
		$folders[] = email_get_root_folder( $accountid, EMAIL_DRAFT);
	}

	$folders[] = email_get_root_folder( $accountid, EMAIL_SENDBOX);
	$folders[] = email_get_root_folder( $accountid, EMAIL_TRASH);

	return $folders;

}

/**
 * This function get users to sent an mail.
 *
 * @param int $mailid Mail ID
 * @param string send type of mail to user
 * @param array $excludedids Contains userids to ignore
 * @return object Contain all users object send mails
 * @todo Finish documenting this function
 **/
function email_get_users_sent($mailid, $type='', $excludedids=array()) {
    global $DB;
    
    // Get mails with send to my
    if (! $sends = $DB->get_records('email_send', array('mailid'=>$mailid)) ) {
        return false;
    }

    $users = array();

    // Get username
    foreach ( $sends as $send ) {
        // Get account
        if (! $account = $DB->get_record('email_account', array('id'=>$send->accountid))) {
                return false;
        }

        // Get user
        if (! $user = $DB->get_record('user', array('id'=>$account->userid))) {
                return false;
        }

        // Exclude user
        if (in_array($user->id, $excludedids)) {
            continue;
        } 
            
        // filter by send type
        if ( $type == 'to') {
            if ($send->type == 'to' ) {
                $users[] = fullname($user);
            }
        } else if ( $type == 'cc' ) {
            if ($send->type == 'cc' ) {
                $users[] = fullname($user);
            }
        } else if ( $type == 'bcc' ) {
            if ($send->type == 'bcc' ) {
                $users[] = fullname($user);
            }
        } else {
            $users[] = fullname($user);
        }
    }

    return $users;
}



/**
 * This function get users to sent an mail.
 *
 * @param int $mailid Mail ID
 * @param string send type of mail to user
 * @param array $excludedids Contains userids to ignore
 * @return object Contain all users object send mails
 * @todo Finish documenting this function
 **/
function email_get_userids_sent($mailid, $type='', $excludedids=array()) {
    global $DB;
    
    // Get mails with send to my
    if (! $sends = $DB->get_records('email_send', array('mailid'=>$mailid)) ) {
        return false;
    }

    $userids = array();

    // Get username
    foreach ( $sends as $send ) {
        // Get account
        if (! $account = $DB->get_record('email_account', array('id'=>$send->accountid))) {
            return false;
        }

        // Get user
        if (! $user = $DB->get_record('user', array('id'=>$account->userid))) {
            return false;
        }

        // Exclude user
        if (in_array($user->id, $excludedids)) {
            continue;
        } 
            
        // filter by send type
        if ( $type == 'to') {
            if ($send->type == 'to' ) {
                $userids[] = $user->id;
            }
        } else if ( $type == 'cc' ) {
            if ($send->type == 'cc' ) {
                $userids[] = $user->id;
            }
        } else if ( $type == 'bcc' ) {
            if ($send->type == 'bcc' ) {
                $userids[] = $user->id;
            }
        } else {
            $userids[] = $user->id;
        }
    }

    return $userids;
}


/**
 * This function get users to send mail. Before you get an mail, and passed
 * accountid at this function, return user
 *
 * @param int $mailid Account ID of one mail.
 * @return string Contain user who writed mails
 * @todo Finish documenting this function
 **/
function email_get_user_for_sendbox($mailid) {
    global $DB;
    
	// Get send's
	if (! $sendbox = $DB->get_records('email_send', array('mailid'=>$mailid)) ) {
		return false;
	}
        
        $users = '';
	foreach ( $sendbox as $sendmail ) {
		// Get account
		if ( $account = $DB->get_record('email_account', array('id'=>$sendmail->accountid))) {
			// Get user
			if ( $user = $DB->get_record('user', array('id'=>$account->userid))) {
				$users .= fullname($user) .', ';
			}
		}
	}

	// Delete 2 last characters
	$count = strlen($users);
	$users = substr ( $users, 0, $count-2 );

	return $users;
}

/**
 * This function get users for inbox. Before you get an mail, and passed
 * accountid at this function, return user
 *
 * @param int $accountid Account ID of one mail.
 * @return string Contain user who writed mails
 * @todo Finish documenting this function
 **/
function email_get_user_for_inbox($accountid) {
    global $DB;

	// Get account
	if (! $account = $DB->get_record('email_account', array('id'=>$accountid))) {
		return false;
	}

	// Get user
	if (! $user = $DB->get_record('user', array('id'=>$account->userid))) {
		return false;
	}

	return fullname($user);
}

/**
 * This function return format fullname users.
 *
 * @param array $users Fullname of user's
 * @param boolean $forreplyall If it's true, no return string error (default false)
 * @return string format fullname user's.
 * @todo Finish documenting this function
 **/
function email_format_users($users, $forreplyall=false) {
    $usersend = '';
    if ($users) {
        // Index of record
        $i = 0;
        foreach ( $users as $user ) {

            // If no first record, add semicolon
            if ( $i != 0 ) {
                $usersend .= ', '.$user;
            } else {
                    // If first add name only
                $usersend .= $user;
            }

            // Increment index record
            $i++;
        }
    } else {

        if (! $forreplyall) {
            // If no users sent's, inform this act.
            $usersend = get_string('neverusers', 'email');
        }
    }

    // Return string format name's
    return $usersend;
}

/**
 * This functions print select form, who it's options to have mails
 *
 * @uses $CFG
 * @param object $options Options for redirect this form
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 * */
function email_print_select_options($options, $perpage) {

	global $CFG,$DB;

	$baseurl = $CFG->wwwroot . '/mod/email/view.php?' . email_build_url($options);

	echo '<br />';

	echo '<div class="content">' . get_string('whatdo', 'email') .' ';

	// Define default separator
	$spaces = '&#160;&#160;&#160;';

	echo '<select name="action">
                	<option value="" selected="selected">' . get_string('choose') . ' ...</option>
                	<option value="removemail">'. get_string('removemail','email') .' </option>
        			<option value="toread">' . get_string('toread','email') . '</option>
               	<option value="tounread">' . get_string('tounread','email') . '</option>
    		</select>';


	// Add 2 space
	echo '&#160;&#160;';

	// Print sent button
	echo '<input type="submit" value="' .get_string('ok', 'email'). '" />';

	echo '</div>';


	// Choose number mails perpage

	echo '<div id="sizepage" class="content" align="right">' . get_string('mailsperpage', 'email') .': ';

	// Define default separator
	$spaces = '&#160;&#160;&#160;';

	echo '<select name="perpage" onchange="javascript:this.form.submit();">';

	for($i = 5; $i < 80; $i=$i+5) {

    	if ( $perpage == $i ) {
    		echo '<option value="'.$i.'" selected="selected">' . $i . '</option>';
    	} else {
    		echo '<option value="'.$i.'">' . $i . '</option>';
    	}
	}

    echo '</select>';

	echo '</div>';

	/// Move folder...

	echo '<br />';

	echo '<div id="move2folder" class="content">' . get_string('movetofolder', 'email') .': ';

	// Define default separator
	$spaces = '&#160;&#160;&#160;';

	// Get my folders account
        $choose = '';
	if ( $folders = email_get_root_folders($options->a, false) ) {

		// Get account
		if (! $account = email_get_account_by_id($options->a) ) {
			print_error( 'noaccount','email');
		}

		// Get courses associated at this account
		foreach ($folders as $folder) {
                    $email   = $DB->get_record('email', array('id'=>$account->emailid));
                    $course  = $DB->get_record('course', array('id'=>$email->course));
                    if (! $cm = get_coursemodule_from_instance('email', $email->id, $course->id)) {
                        print_error('nocourseemail','email');
                    }


                    // Important. Add in text, id for this folder, and in javascript function, add this value in corresponding field.
                    $translatefolder = email_get_folder($folder->id);
                    $choose .= '<option value="'.$folder->id.'">'.$spaces.$translatefolder->name  .'</option>';

                    // Now, print all subfolders it
                    $subfolders = email_get_subfolders($folder->id);

                    // If subfolders
                    if ( $subfolders ) {
                        foreach ( $subfolders as $subfolder ) {
                            $choose .= '<option value="'.$subfolder->id.'">'.$spaces.$spaces.$subfolder->name  .'</option>';
                        }
                    }
		}
	}

	echo '<select name="folderid">
					<option value="" selected="selected">' . get_string('choose') . ' ...</option>' .
               			$choose . '
    		</select>';


	// Add 2 space
	echo '&#160;&#160;';

	// Change, now folderoldid is actual folderid
	if (! $options->folderid ) {
		if ( $inbox = email_get_root_folder($options->a, EMAIL_INBOX) ) {
			echo '<input type="hidden" name="folderoldid" value="'.$inbox->id.'" />';
		}
	} else {
		echo '<input type="hidden" name="folderoldid" value="'.$options->folderid.'" />';
	}

	// Define action
	//echo '<input type="hidden" name="action" value="move2folder" />';
	// Add javascript for insert person/s who I've send mail

	$javascript = '<script type="text/javascript" language="JavaScript">
                <!--
                		function addAction(form) {

                			var d = document.createElement("div");
                        d.setAttribute("id", "action");
                        var act = document.createElement("input");
                        act.setAttribute("type", "hidden");
                        act.setAttribute("name", "action");
                        act.setAttribute("id", "action");
                        act.setAttribute("value", "move2folder");
                        d.appendChild(act);
                        document.getElementById("move2folder").appendChild(d);

                			form.submit;
                		}
                	-->
                 </script>';

	echo $javascript;

	// Print sent button
	echo '<input type="submit" value="' .get_string('move'). '" onclick="javascript:addAction(this);" />';

	echo '</div>';

    return true;
}

/**
 * This functions return if mail is readed or not readed
 *
 * @param int $mailid Mail ID
 * @param int $accountid Account ID
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_readed($mailid, $accountid) {
    global $DB;
    
	// Get account
	if (! $send = $DB->get_record('email_send', array('mailid'=>$mailid, 'accountid'=>$accountid))) {
		return false;
	}

	// Return value
	if ( $send->readed == 0 ) {
		return false;
	} else {
		return true;
	}
}

/**
 * This functions return number of mails unreaded
 *
 * @uses $CFG
 * @param int $accountid Account ID
 * @param int $folderid Folder ID (Optional) When fault this param, return total number of unreaded mails for account
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_get_number_unreaded($accountid, $folderid=NULL) {

	global $CFG,$DB;

	if (! $folderid ) {
		// return mails unreaded
		return $DB->count_records('email_send', array('accountid'=>$accountid, 'readed'=>0));
	} else {

		// Get folder
		if (!$folder = email_get_folder($folderid) ) {
			return false;
		}

		if ( email_isfolder_type($folder, EMAIL_INBOX) ) {
			// For apply order, I've writting an sql clause
			$sql = "SELECT count(*)
		                            FROM {email_mail} m
		                   LEFT JOIN {email_send} s ON m.id = s.mailid
		                   LEFT JOIN {email_foldermail} fm ON m.id = fm.mailid ";


			// WHERE principal clause for filter account
			$wheresql = " WHERE s.accountid = $accountid
						  AND fm.folderid = $folder->id
						  AND s.readed = 0";

			return $DB->count_records_sql( $sql.$wheresql );

		} else if ( email_isfolder_type($folder, EMAIL_DRAFT) ) {
			// For apply order, I've writting an sql clause
			$sql = "SELECT count(*)
		                   	FROM {email_mail} m
		                   	LEFT JOIN {email_foldermail} fm ON m.id = fm.mailid ";


			// WHERE principal clause for filter account
			$wheresql = " WHERE m.accountid = $accountid
						  AND fm.folderid = $folder->id";

			return $DB->count_records_sql( $sql.$wheresql );

		} else {
			return false;
		}
	}
}

/**
 * This funcions build an URL for an options
 *
 * @param object $options
 * @param boolean $form If return form
 * @param boolean $arrayinput If name of hidden input is array.
 * @param string $nameinput If is arrayinput, pass name of this.
 * @return string URL or Hidden input's
 * @todo Finish documenting this function
 **/
function email_build_url($options, $form=false, $arrayinput=false, $nameinput=NULL) {

	$url = '';
//print_r($options);
	// Build url part options
 	if ($options) {
 		// Index of part url
 		$i = 0;

        foreach ($options as $name => $value) {
        	// If not first param
        	if (! $form ) {
        		if ($i != 0) {
           			$url .= '&' .$name .'='. $value;
        		} else {
        			// If first param
        			$url .= $name .'='. $value;
        		}
        		// Increment index
        		$i++;
        	} else {

        		if ( $arrayinput ) {
        			$url .= '<input type="hidden" name="'.$nameinput.'[]" value="'.$value.'" /> ';
        		} else {
        			$url .= '<input type="hidden" name="'.$name.'" value="'.$value.'" /> ';
        		}
        	}
        }
    }

    return $url;
}

/**
 * This function show content mail.
 *
 * @uses $USER
 * @param int $mailid Mail ID
 * @param object $options Options sending
 * @param string $mails Mails ID's
 * @return boolean Success/Fail
 * @todo Finish documenting this function
 **/
function email_viewmail($mailid, $options, $mails, $cm) {

	global $USER,$DB,$OUTPUT,$COURSE, $CFG;

	// Get mail
	if (! $mail = $DB->get_record('email_mail', array('id'=>$mailid))) {
		notify('Fail reading mail');
	}

	// Get user account
	if (! $account = email_get_account_by_id($options->a)) {		// Fix bug. Thanks Sergio Sama :-)
		notify('Fail reading account');
	}

	// Get user, for show this fields
	if (! $user = $DB->get_record('user',array('id'=>$account->userid))) {
		notify('Fail reading user');
	}

	// Get user account
	if (! $accountwritter = email_get_account_by_id($mail->accountid)) {
		notify('Fail reading account');
	}

	// Get user writter
	if (! $writer = $DB->get_record('user',array('id'=>$accountwritter->userid))) {
		notify('Fail reading user');
	}

	// Now, mark mail as readed
	$mailids[] = $mail->id;
	if (! email_mail2read($mailids, $account->id, $options, false) ) {
		print_error('markreadfail','email');
	}

	// Prepare next and previous mail
	if ( $mails ) {
		$urlnextmail  = '';
		$next = email_get_nextprevmail($mailid, $mails, true);
		if ( $next ) {
			$action = clone $options;
			$action->mailid = $next;
			$urlnextmail  = email_build_url($action);
			$urlnextmail .= '&amp;mails='. $mails;
			$urlnextmail .=  '&amp;action=\'viewmail\'';
		}

		$urlpreviousmail  = '';
		$prev = email_get_nextprevmail($mailid, $mails, false);
		if ( $prev ) {
			$action = clone $options;
			$action->mailid = $prev;
			$urlpreviousmail  = email_build_url($action);
			$urlpreviousmail .= '&amp;mails='. $mails;
			$urlpreviousmail .= '&amp;action=\'viewmail\'';
		}
	}

	// Here, get users sended mail
	$userssendto = email_get_users_sent($mail->id, 'to');
	$userstosendto = email_format_users($userssendto);

	$userssendcc = email_get_users_sent($mail->id, 'cc');
	$userstosendcc = email_format_users($userssendcc, true);

	$userssendbcc = email_get_users_sent($mail->id, 'bcc');
	$userstosendbcc = email_format_users($userssendbcc, true);

	// Drop users sending by bcc if user isn't writer
	if ( $USER->id != $accountwritter->userid ) {
		$userstosendbcc	= ''; // Drop users sending by bcc if user isn't writer
	}



	// Get user who write mail
	if (! $writeraccount = email_get_account_by_id($mail->accountid) ) {
		notify('Fail reading writer mail account');
	}

	if (! $writer = $DB->get_record('user', array('id'=>$writeraccount->userid))) {
		notify('Fail reading writer');
	}

	/// Prepare url's to sending
//	print_r($options);
	$baseurl = email_build_url($options);

	$urltoreply 	= $baseurl .'&amp;action=\'reply\'';
	$urltoreplyall 	= $baseurl .'&amp;action=\'replyall\'';
	$urltoforward 	= $baseurl .'&amp;action=\'forward\'';

	$urltoremove 	= $baseurl .'&amp;action=\'removemail\'';

	include_once('viewmail.php');

	return true;
}

/**
 * This functions return id's of object
 *
 * @param object $ids Mail
 * @return string String of ids
 * @todo Finish documenting this function
 **/
function email_get_ids($ids) {
	$identities = array();

	if ( $ids ) {
		foreach ($ids as $id) {
			$identities[] = $id->id;
		}
	}

	// Character alfanumeric, becase optional_param clean anothers tags.
	$strids = implode('a', $identities);

	return $strids;
}

/**
 * This functions return next or previous mail
 *
 * @param int $mailid Mail
 * @param string $mails Id's of mails
 * @param boolean True when next, false when previous
 * @return int Next or Previous mail
 * @todo Finish documenting this function
 **/
function email_get_nextprevmail($mailid, $mails, $nextorprevious) {

	// To array
	// Character alfanumeric, becase optional_param clean anothers tags.
	$mailsids = explode('a', $mails);

	if ( $mailsids ) {
		$prev = 0;
		$next = false;
		foreach ($mailsids as $mail) {
			if ( $next ) {
				return $mail; // Return next "record"
			}
			if ( $mail == $mailid ) {
				if ($nextorprevious) {
					$next = true;
				} else {
					return $prev; // Return previous "record"
				}
			}
			$prev = $mail;
		}
	}

	return false;
}

/**
 * This function return user who writting an mail
 *
 * @param int $mailid Mail ID
 * @return object User record
 * @todo Finish documenting this function
 */
function email_get_user($mailid) {
    global $DB;

    // Get mail record
    if (! $mail = $DB->get_record('email_mail', array('id'=>$mailid))) {
            print_error('nomailid','email');
    }

    // Get account for writer
    if (! $account = $DB->get_record('email_account', array('id'=>$mail->accountid))) {
            print_error('noaccount','email');
    }

    // Return user record
    return $DB->get_record('user', array('id'=>$account->userid));

}

/// Search Functions

/**
 * This functions searches through the email's subject,body, senders and recipients
 *
 * @uses $CFG, $DB
 * @param object $form Form data sended to search
 * @param object $options Options
 * @return array Contain all mails
 * @todo Finish documenting this function
 */
function email_search($form, $options) {

    global $CFG, $DB;
    include_once('searchlib.php');

    //$searchstring = optional_param('words', '', PARAM_TEXT); 	

    $searchstring = str_replace("\\\"","\"",$form->words);
    $parser = new search_parser();
    $lexer = new search_lexer($parser);

    if ($lexer->parse($searchstring)) {
        $parsearray = $parser->get_parsed_array();

        $sql = "SELECT m.id";
            $sql.= ", m.accountid";
            $sql.= ", m.subject";
            $sql.= ", m.timecreated";
            $sql.= ", m.body";
            $sql.= ", u.firstname";
            $sql.= ", u.lastname";
            $sql.= ", u.email";
            $sql.= ", u.picture";
            $sql.= ", m.accountid as writer";
        $sql.= " FROM {email_mail} m";
        $sql.= " JOIN {email_account} a";   //sender acount
            $sql.= " ON a.id = m.accountid";
        $sql.= " JOIN {user} u";            //sender user
            $sql.= " ON u.id = a.userid";
        $sql.= " JOIN {email_send} s";
            $sql.= " ON s.mailid = m.id";
        $sql.= " JOIN {email_account} ra";  //recipient account
            $sql.= " ON ra.id = s.accountid";
        $sql.= " JOIN {user} ru";           //recipient user
            $sql.= " ON ru.id = ra.userid";
        $sql.= " JOIN {email_foldermail} fm";
            $sql.= " ON fm.mailid = m.id";
            $sql.= " AND fm.folderid = :folderid";

        if ( $form->folder ) {
            // Get folder
            $folder = email_get_folder($form->folder);
        }

        
        $whereclause = '';
        

        $params = array("accountid"=>$options->a
                    , "folderid"=>$form->folder
                    );
        $ntokens = count($parsearray);
        for($i=0; $i<$ntokens; $i++){
            $whereclause.= " AND (";
                $whereclause.= "(m.subject  ILIKE  :word".$i."subject)";
                $whereclause.= " OR (m.body  ILIKE  :word".$i."body)";
                $whereclause.= " OR (u.firstname  ILIKE  :word".$i."sfn)"; //sender firstname
                $whereclause.= " OR (u.lastname  ILIKE  :word".$i."sln)"; //sender lastname
                $whereclause.= " OR (ru.firstname  ILIKE  :word".$i."rfn)"; //recipient firstname
                $whereclause.= " OR (ru.lastname  ILIKE  :word".$i."rln)"; //recipient lastname
            $whereclause.= ")";
            $params["word".$i."subject"] = "%".$parsearray[$i]->value."%";
            $params["word".$i."body"] = "%".$parsearray[$i]->value."%";
            $params["word".$i."sfn"] = "%".$parsearray[$i]->value."%";
            $params["word".$i."sln"] = "%".$parsearray[$i]->value."%";
            $params["word".$i."rfn"] = "%".$parsearray[$i]->value."%";
            $params["word".$i."rln"] = "%".$parsearray[$i]->value."%";
        }

        if($whereclause!=''){
            $sql.= " WHERE ".substr($whereclause,4);
        }
        
        $sql.= " GROUP BY m.id, m.accountid, m.subject, m.timecreated, m.body, u.firstname, u.lastname, u.email, u.picture";
        $sql.= " ORDER BY m.timecreated DESC ";

        return $DB->get_records_sql($sql, $params);
    }

}


/**
 * This function get users of one course, formated with send all (any record contain username)
 *
 * @param int $courseid Course ID
 * @return array Contain all username's
 * @todo Finish documenting this function
 **/
function email_get_students_for_sendall($courseid) {
    global $DB;
    
	// NOTE: We could use get_students of the basic libraries (datalib.php), but the case could occur that lacked or exceeded some student.

	// Get mails with send to my
	if (! $email = $DB->get_record('email', array('course'=>$courseid))) {
		return false;
	}

	// Get accounts course
	if (! $accounts = $DB->get_records('email_account', array('emailid'=>$email->id))) {
		return false;
	}

	// Get users records
	foreach ( $accounts as $account ) {

		// Get user
		if (! $user = $DB->get_record('user', array('id'=>$account->userid))) {
			return false;
		}

		$users[] = $user->username;

	}

	return $users;
}


/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function email_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO:               return false;

        case FEATURE_BACKUP_MOODLE2:          return true;
            
        default: return null;
    }
}


/**
 * Serves the email attachments. Implements needed access control ;-)
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 */
function email_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $fileareas = array('attachments', 'body');
    if (!in_array($filearea, $fileareas)) {
        return false;
    }

    $mailid = (int)array_shift($args);

    if (!$mail = $DB->get_record('email_mail', array('id'=>$mailid))) {
        return false;
    }

    if (!$forum = $DB->get_record('email', array('id'=>$cm->instance))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_email/$filearea/$mailid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, true); // download MUST be forced - security!
}
?>
