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
 * The assignsubmission_edusharing assessable uploaded event.
 *
 * @package    assignsubmission_edusharing
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_edusharing\event;

use coding_exception;
use dml_exception;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * The assignsubmission_edusharing assessable uploaded event class.
 *
 * @package    assignsubmission_edusharing
 * @since      Moodle 2.6
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assessable_uploaded extends \core\event\assessable_uploaded {

    /**
     * Legacy event files.
     *
     * @var array
     */
    protected $legacyfiles = [];

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has uploaded a file to the submission with id '$this->objectid' " .
            "in the assignment activity with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_name() {
        return get_string(
            'eventassessableuploaded',
            'assignsubmission_edusharing',
            get_config('edusharing', 'application_appname')
        );
    }

    /**
     * Get URL related to the action.
     *
     * @return moodle_url
     * @throws moodle_exception
     */
    public function get_url(): moodle_url {
        return new moodle_url('/mod/assign/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Sets the legacy event data.
     *
     * @param stdClass $legacyfiles legacy event data.
     * @return void
     */
    public function set_legacy_files($legacyfiles): void {
        $this->legacyfiles = $legacyfiles;
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init(): void {
        parent::init();
        $this->data['objecttable'] = 'assign_submission';
    }

    /**
     * Function get_objectid_mapping
     *
     * @return string[]
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'assign_submission', 'restore' => 'submission'];
    }
}
