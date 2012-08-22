<?php // $Id: index.php,v 1.5 2006/10/18 16:41:20 tmas Exp $
/**
 * This page lists all the instances of email in a particular course
 *
 * @author Toni Mas
 * @version $Id: index.php,v 1.5 2006/10/18 16:41:20 tmas Exp $
 * @package email
 * @license The source code packaged with this file is Free Software, Copyright (C) 2006 by
 *          <toni.mas at uib dot es>.
 *          It's licensed under the AFFERO GENERAL PUBLIC LICENSE unless stated otherwise.
 *          You can get copies of the licenses here:
 * 		                   http://www.affero.org/oagpl.html
 *          AFFERO GENERAL PUBLIC LICENSE is also included in the file called "COPYING".
 **/
    global $DB;
    
    require_once("../../config.php");
    require_once("lib.php");

    $id = required_param('id', PARAM_INT);   // course

    if (! $course = $DB->get_record("course", array("id"=>$id))) {
        print_error("nocourseid","email");
    }

    require_login($course->id);

    add_to_log($course->id, "email", "view all", "index.php?id=$course->id", "");


/// Get all required strings

    $stremails = get_string("modulenameplural", "email");
    $stremail  = get_string("modulename", "email");


/// Print the header

    if ($course->category) {
        $navigation = "<a href=\"../../course/view.php?id=$course->id\">$course->shortname</a> ->";
    } else {
        $navigation = '';
    }

    $url = new moodle_url($CFG->wwwroot.$SCRIPT);
    $PAGE->set_url($url);
    print_header("$course->shortname: $stremails", "$course->fullname", "$navigation $stremails", "", "", true, "", navmenu($course));

/// Get all the appropriate data

    if (! $emails = get_all_instances_in_course("email", $course)) {
        notice("There are no emails", "../../course/view.php?id=$course->id");
        die;
    }

/// Print the list of instances (your module will probably extend this)

    $timenow = time();
    $strname  = get_string("name");
    $strweek  = get_string("week");
    $strtopic  = get_string("topic");

    if ($course->format == "weeks") {
        $table->head  = array ($strweek, $strname);
        $table->align = array ("center", "left");
    } else if ($course->format == "topics") {
        $table->head  = array ($strtopic, $strname);
        $table->align = array ("center", "left", "left", "left");
    } else {
        $table->head  = array ($strname);
        $table->align = array ("left", "left", "left");
    }

    foreach ($emails as $email) {
        if (!$email->visible) {
            //Show dimmed if the mod is hidden
            $link = "<a class=\"dimmed\" href=\"view.php?id=$email->coursemodule\">$email->name</a>";
        } else {
            //Show normal if the mod is visible
            $link = "<a href=\"view.php?id=$email->coursemodule\">$email->name</a>";
        }

        if ($course->format == "weeks" or $course->format == "topics") {
            $table->data[] = array ($email->section, $link);
        } else {
            $table->data[] = array ($link);
        }
    }

    echo "<br />";

    print_table($table);

/// Finish the page

    echo $OUTPUT->footer($course);

?>
