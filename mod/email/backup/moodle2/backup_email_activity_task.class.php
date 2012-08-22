<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 onwards YOUR_NAME_GOES_HERE {@link YOUR_URL_GOES_HERE}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 // This activity has not particular settings but the inherited from the generic
 // backup_activity_task so here there isn't any class definition, like the ones
 // existing in /backup/moodle2/backup_settingslib.php (activities section)


require_once($CFG->dirroot . '/mod/email/backup/moodle2/backup_email_stepslib.php'); // Because it exists (must)
require_once($CFG->dirroot . '/mod/email/backup/moodle2/backup_email_settingslib.php'); // Because it exists (optional)

/**
 * choice backup task that provides all the settings and steps to perform one
 * complete backup of the activity
 */
class backup_email_activity_task extends backup_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        $this->add_step(new backup_email_activity_structure_step('email_structure', 'email.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        // Link to email view by moduleid
        $search="/(".$base."\/mod\/email\/view.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@EMAILVIEWBYID*$2@$', $content);
        return $content;
    }
}