<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of newPHPClass
 *
 * @author switlik
 */
namespace mod_email;

class folder_messages_table extends \table_sql implements \renderable {

    /** @var email $email */
    private $email = null;
    /** @var int $perpage */
    private $perpage = 25;
    /** @var int $rownum (global index of current row in table) */
    private $rownum = -1;
    /** @var renderer_base for getting output */
    private $output = null;
    /** @var stdClass gradinginfo */
    private $gradinginfo = null;
    /** @var int $tablemaxrows */
    private $tablemaxrows = 10000;
    /** @var boolean $quickgrading */
    private $quickgrading = false;
    /** @var boolean $hasgrantextension - Only do the capability check once for the entire table */
    private $hasgrantextension = false;
    /** @var boolean $hasgrade - Only do the capability check once for the entire table */
    private $hasgrade = false;
    /** @var array $groupsubmissions - A static cache of group submissions */
    private $groupsubmissions = array();
    /** @var array $submissiongroups - A static cache of submission groups */
    private $submissiongroups = array();
    /** @var string $plugingradingbatchoperations - List of plugin supported batch operations */
    public $plugingradingbatchoperations = array();
    /** @var array $plugincache - A cache of plugin lookups to match a column name to a plugin efficiently */
    private $plugincache = array();
    /** @var array $scale - A list of the keys and descriptions for the custom scale */
    private $scale = null;

    private $folder = null;

    function __construct($folder, $perpage) {
        parent::__construct('email-viewfolder-'.$folder->id);
        global $PAGE, $DB, $USER;;

        $baseurl = new \moodle_url('/mod/email/view.php', array('f' => $folder->id));

        $this->output = $PAGE->get_renderer('mod_email');
        $this->perpage = $perpage;
        $this->folder = $folder;
        
        $tablecolumns = array();
        $tableheaders = array();

        $tablecolumns[] = 'select';
        $tableheaders[] = get_string('select');

        $tablecolumns[] = 'lastname';
        if ($folder->type == EMAIL_SENT) {
            $tableheaders[] = get_string('to', 'email');
        } else {
            $tableheaders[] = get_string('from', 'email');
        }

        $tablecolumns[] = 'subject';
        $tableheaders[] = get_string('subject','email');

        $tablecolumns[] = 'timesent';
        $tableheaders[] = get_string('date','email');

        $this->define_columns($tablecolumns);
        $this->define_headers($tableheaders);
        $this->define_baseurl($baseurl->out());

        $this->no_sorting('select');
        if ($folder->parenttype == EMAIL_SENT) {
            $this->no_sorting('lastname');
        } else {
            $this->sortable(true, 'lastname', SORT_ASC);
        }
        $this->sortable(true, 'subject', SORT_ASC);
        $this->sortable(true, 'timesent', SORT_DESC);

        $this->set_attribute('cellspacing', '0');
        $this->set_attribute('class', 'generaltable generalbox');

        $this->initialbars(true);

        // Get the messages to display.
        if ($folder->type == EMAIL_SENT) {
            // SQL for folders where the user is a the sender.
            $fields =   "EM.id"
                        . ", EM.emailid"
                        . ", EM.timecreated"
                        . ", EM.subject"
                        . ", EM.body"
                        . ", EM.status"
                        . ", EM.timesent"
                        . ", EMU1.id as emuid"
                        . ", EMU1.viewed"
                        . ", EMU1.userid as from_userid"
                        ;
            $from = "{email_message} EM"
                        . " JOIN {email_message_users} EMU1"
                        . "     ON EMU1.messageid = EM.id"
                        . "     AND EMU1.type = 'from'"
                        . "     AND EMU1.folderid = ?"
                        . "     AND EMU1.userid = ?"
                        ;
            $where = "EMU1.deleted = 0";
        } else {
            // SQL for folders where the user is a the recipient.
            $fields =   "EM.id"
                        . ", EM.emailid"
                        . ", EM.timecreated"
                        . ", EM.subject"
                        . ", EM.body"
                        . ", EM.status"
                        . ", EM.timesent"
                        . ", EMU1.id as emuid"
                        . ", EMU1.userid as to_userid"
                        . ", EMU1.viewed"
                        . ", EMU2.userid as from_userid"
                        . ", U.firstname as from_firstname"
                        . ", U.lastname as from_lastname"
                        ;
            $from = "{email_message} EM"
                        . " JOIN {email_message_users} EMU1"
                        . "     ON EMU1.messageid = EM.id"
                        . "     AND EMU1.type = 'to'"
                        . "     AND EMU1.folderid = ?"
                        . "     AND EMU1.userid = ?"
                        . " JOIN {email_message_users} EMU2"
                        . "     ON EMU2.messageid = EM.id"
                        . "     AND EMU2.type = 'from'"
                        . " JOIN {user} U"
                        . "     ON U.id = EMU2.userid"
                        ;
            $where = "EMU1.deleted = 0";
        }

        $params = array($folder->id, $USER->id);

        $this->set_sql($fields, $from, $where, $params);

    }

    /**
     * Return the number of rows to display on a single page.
     *
     * @return int The number of rows per page
     */
    public function get_rows_per_page() {
        return $this->perpage;
    }

    /**
     * Before adding each row to the table make sure rownum is incremented.
     *
     * @param array $row row of data from db used to make one row of the table.
     * @return array one row for the table
     */
    public function format_row($row) {
        if ($this->rownum < 0) {
            $this->rownum = $this->currpage * $this->pagesize;
        } else {
            $this->rownum += 1;
        }

        return parent::format_row($row);
    }

    /**
     * Insert a checkbox for selecting the current row for batch operations.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_select(\stdClass $message) {
       return '<input type="checkbox" class="usercheckbox" name="emuid[]" value="'.$message->emuid.'" />';
    }

    public function col_lastname(\stdClass $message) {
        $url = new \moodle_url("message.php", array('f' => $this->folder->id, 'p' => $this->currpage, 'm' => $message->id));
        if ($this->folder->type == EMAIL_SENT) {
            $userfullname = email_get_recipients($message->id);
        } else {
            $userfullname = $message->from_firstname . " " . $message->from_lastname;
        }
        return email_makebold($this->output->action_link($url, $userfullname), $message->viewed);
    }

    public function col_subject(\stdClass $message) {
        $url = new \moodle_url("message.php", array('f' => $this->folder->id, 'p' => $this->currpage, 'm' => $message->id));
        return email_makebold($this->output->action_link($url, $message->subject), $message->viewed);
    }

    public function col_timesent(\stdClass $message) {
        return email_makebold(userdate($message->timesent), $message->viewed);
    }

}
