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
 * An interface for creating and sending internal email messages
 *
 * @package    mod_webexactvity
 * @author     Matt Switlik <switlik@oakland.edu>
 * @copyright  2016 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_email;

defined('MOODLE_INTERNAL') || die();

class folder {

    /**
     * Builds the folder object.
     *
     * @param stdClass|int    $folder Object of the folder record, or id of record to load.
     * @throws coding_exception when bad parameter received.
     */
    public function __construct($folder = null) {
        global $DB;

        if (is_null($folder)) {
            $this->folder = new \stdClass();
        } else if (is_object($folder)) {
            $this->folder = $folder;  #QUESTION: should this copy the object or reference it?
        } else if (is_numeric($folder)) {
            $this->folder = $DB->get_record('email_folder', array('id' => $folder));
        }

        if ($this->folder) {
            return;
        }

        throw new \coding_exception('Unexpected parameter type passed to folder constructor.');
    }

    public function add() {}
    public function edit() {}
    public function delete() {}

    public function get_user_inbox($user = null) {}
    public function get_folder_list($user = null) {}

    public function get_messages() {}

    public function get_subfolders() {}
    
}
