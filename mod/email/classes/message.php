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
 * @package    mod_email
 * @author     Matt Switlik <switlik@oakland.edu>
 * @copyright  2016 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_email;

defined('MOODLE_INTERNAL') || die();

class message {

    /**
     * Builds the message object.
     *
     * @param stdClass|int    $message Object of message and message users records, or id of record to load.
     * @throws coding_exception when bad parameter received.
     */
    public function __construct($message = null) {
        global $DB;

        if (is_null($message)) {
            $this->message = new \stdClass();
        } else if (is_object($message)) {
            $this->message = $message;  #QUESTION: should this copy the object or reference it?
        } else if (is_numeric($message)) {
            $this->message = $DB->get_record('email_message', array('id' => $message));
            $this->from = $DB->get_record('email_message_users', array('messageid' => $message, 'type' => EMAIL_USER_TYPE_FROM));
            $this->to   = $DB->get_records('email_message_users', array('messageid' => $message, 'type' => EMAIL_USER_TYPE_TO));
            $this->cc   = $DB->get_records('email_message_users', array('messageid' => $message, 'type' => EMAIL_USER_TYPE_CC));
            $this->bcc  = $DB->get_records('email_message_users', array('messageid' => $message, 'type' => EMAIL_USER_TYPE_BCC));
        }

        if ($this->message) {
            return;
        }

        throw new \coding_exception('Unexpected parameter type passed to message constructor.');
    }

    public function add_recipient($user, $type) {}
    public function send() {}
    public function save_draft() {}

    public function set_viewed($user) {}

    public function delete() {}
}
