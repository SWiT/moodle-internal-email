<?php  // $Id: block_email.php,v 1.4 2007/01/30 sergiosv Exp $

require_once($CFG->dirroot .'/mod/email/lib.php');

/**
 * This block shows information about user email accounts
 *
 * @author Sergio Sama
 * @version $Id: block_email.php,v 1.4 2007/01/30 sergiosv Exp $
 * @package email
 **/
class block_email extends block_list {
	function init() {
		$this->title = get_string('modulenameplural', 'email');
		$this->version = 2011100600;
	}

	function get_content() {
		global $USER, $CFG, $PAGE, $DB, $OUTPUT;

		if ($this->content !== NULL) {
			return $this->content;
		}

		$this->content = new stdClass;
		$this->content->items = array();
		$this->content->icons = array();
		$this->content->footer = '';

        

		// Only show all course in principal course, others, show it
		if ( $PAGE->course->id == 1 || $PAGE->pagetype == 'my-index') {
                    //Get the courses of the user
                    $my_courses = enrol_get_my_courses('*');
		} else {
                    // Get this course
                    $course = $DB->get_record('course',array('id'=>$PAGE->course->id));
                    $my_courses[] = $course;
		}

		// Count my courses
		$count_my_courses = count($my_courses);

		//Configure item and icon for this account
		$icon = '<img src="'.$OUTPUT->pix_url('i/course').'" height="16" width="16" alt="'.get_string("course").'" />';

		foreach( $my_courses as $my_course ) {
			// Check if the user has account in this course
			if( email_have_account($my_course->id, $USER->id) ) {
				//Get the account
				$account = email_get_account($my_course->id, $USER->id);

				//Get the number of unread mails
				$folder = email_get_root_folder($account->id, EMAIL_INBOX);
				$number_unread_mails = email_get_number_unreaded($account->id, $folder->id);

				if (! $cm = get_coursemodule_from_instance("email", $account->emailid, $my_course->id)) {
				    //error("Module EMAIL is not enabled. Please add it.");
                                    continue;
				}
                                
                                $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
                                if ($cm->visible == 0 && !has_capability('moodle/course:viewhiddenactivities', $modcontext, $USER->id)) { //hide if not visible unless user is teacher
                                    continue;
                                }
                                

				if ( $count_my_courses > $CFG->email_number_courses_display_in_blocks_course ) {
					// Only show if has unreaded mails
					if ( $number_unread_mails > 0 ) {
						$number_unread_mails = '<b>('. $number_unread_mails . ')</b>';

						// By configure show or not show principal course
						if ( $CFG->email_display_course_principal ) {
                                                        $contentitem = '<a href="'.$CFG->wwwroot.'/mod/email/view.php?id='.$cm->id.'"';
                                                        $contentitem.= ($cm->visible == 0)?' class="dimmed"':'';
                                                        $contentitem.= '>'.$my_course->fullname .' '. $number_unread_mails.'</a>';
							$this->content->items[] = $contentitem;
							$this->content->icons[] = $icon;
						} else {
							// Don't display principal course
							if ( $course->id != 1 ) {
                                                            $contentitem = '<a href="'.$CFG->wwwroot.'/mod/email/view.php?id='.$cm->id.'"';
                                                            $contentitem.= ($cm->visible == 0)?' class="dimmed"':'';
                                                            $contentitem.= '>'.$my_course->fullname .' '. $number_unread_mails.'</a>';
                                                            $this->content->items[] = $contentitem;
                                                            $this->content->icons[] = $icon;
							}
						}

					}

				} else {

					//If there are unread mails...
					if( $number_unread_mails > 0 ) {
						$number_unread_mails = '<b>('. $number_unread_mails . ')</b>';
					} else {
						$number_unread_mails = '';
					}

					// By configure show or not show principal course
                                        if ( $CFG->email_display_course_principal or !isset($CFG->email_display_course_principal) ) {
                                                $contentitem = '<a href="'.$CFG->wwwroot.'/mod/email/view.php?id='.$cm->id.'"';
                                                $contentitem.= ($cm->visible == 0)?' class="dimmed"':'';
                                                $contentitem.= '>'.$my_course->fullname .' '. $number_unread_mails.'</a>';
                                                $this->content->items[] = $contentitem;
                                                $this->content->icons[] = $icon;
                                        } else {
                                                // Don't display principal course
                                                if ( $course->id != 1 ) {
                                                    $contentitem = '<a href="'.$CFG->wwwroot.'/mod/email/view.php?id='.$cm->id.'"';
                                                    $contentitem.= ($cm->visible == 0)?' class="dimmed"':'';
                                                    $contentitem.= '>'.$my_course->fullname .' '. $number_unread_mails.'</a>';
                                                    $this->content->items[] = $contentitem;
                                                    $this->content->icons[] = $icon;
                                                }
                                        }
				}
			}
		}

		return $this->content;
	}

	/**
	 * The block can be used only from the site index
	 */
	/*function applicable_formats() {
		return array('site-index' => true);
	}*/
}
?>
