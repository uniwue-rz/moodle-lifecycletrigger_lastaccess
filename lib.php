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
 * Interface for the subplugintype trigger
 * It has to be implemented by all subplugins.
 *
 * @package tool_lifecycle_trigger
 * @subpackage lastaccess
 * @copyright  2019 Yorick Reum JMU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lifecycle\trigger;

use tool_lifecycle\manager\settings_manager;
use tool_lifecycle\response\trigger_response;

use context_course;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../lib.php');

/**
 * Class which implements the basic methods necessary for a cleanyp courses trigger subplugin
 *
 * @package tool_lifecycle_trigger
 */
class lastaccess extends base_automatic {
    /**
     * Checks the course and returns a repsonse, which tells if the course should be further processed.
     *
     * @param $course object to be processed.
     * @param $triggerid int id of the trigger instance.
     * @return trigger_response
     * @throws \coding_exception
     */
    public function check_course($course, $triggerid) {
        $delay = settings_manager::get_settings($triggerid, SETTINGS_TYPE_TRIGGER)['delay'];
        $course->lastaccess = self::get_last_access_enrolled_users($course->id);
        $now = time();

        if ($course->lastaccess + $delay < $now) {
            return trigger_response::trigger();
        }

        return trigger_response::next();
    }

    public function instance_settings() {
        return array(
            new instance_setting('delay', PARAM_INT)
        );
    }

    /**
     * Takes a course id, returns the timestamp of the last access / interaction
     * with this course (only counting interactions of users who are enrolled in the course)
     *
     * @param $courseId int
     * @return int
     */
    private static function get_last_access_enrolled_users($courseId) {
        // @TODO Für die Doku: Gastzugriffe stehen auch in lastaccess --> ist okay so, aber erwähnenswert

        global $DB;
        $sql = "SELECT timeaccess, userid FROM mdl_user_lastaccess WHERE courseid = :courseid ORDER BY timeaccess DESC";
        try {
            $records = $DB->get_records_sql($sql, array("courseid" => $courseId));
        } catch (\dml_exception $e) {
            $records = [];
        }

        $context = context_course::instance($courseId);
        foreach ($records as $record) {
            $isEnrolled = is_enrolled($context, $record->userid, '', true);
            if ($isEnrolled) {
                return $record->timeaccess;
            }
        }

        return 0;
    }

    public function extend_add_instance_form_definition($mform) {
        $elementname = 'delay';
        $mform->addElement('duration', $elementname, get_string($elementname, 'lifecycletrigger_lastaccess'));
        $mform->addHelpButton($elementname, $elementname, 'lifecycletrigger_lastaccess');
    }

    public function extend_add_instance_form_definition_after_data($mform, $settings) {
        if (is_array($settings) && array_key_exists('delay', $settings)) {
            $default = $settings['delay'];
        } else {
            $default = 16416000;
        }
        $mform->setDefault('delay', $default);
    }

    public function get_subpluginname() {
        return 'lastaccess';
    }

}
