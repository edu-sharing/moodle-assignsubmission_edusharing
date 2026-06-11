<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Internal library of functions for edusharing submissions
 *
 * All the edusharing specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    assignsubmission_edusharing
 * @copyright  metaVentis GmbH — http://metaventis.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_edusharing\EduSharingService;
use mod_edusharing\UtilityFunctions;

defined('MOODLE_INTERNAL') || die();
global $CFG;

define('ASSIGNSUBMISSION_EDUSHARING_MAXSUMMARYFILES', 5);
define('ASSIGNSUBMISSION_EDUSHARING_FILEAREA', 'submission_edusharing');

/**
 * library class for edusharing submission plugin extending submission plugin base class
 *
 * @package    assignsubmission_edusharing
 * @copyright  metaVentis GmbH — http://metaventis.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_edusharing extends assign_submission_plugin {
    /**
     * Get the name of the online text submission plugin
     * @return string
     */
    public function get_name(): string {
        try {
            return get_string('edusharing', 'assignsubmission_edusharing', get_config('edusharing', 'application_appname'));
        } catch (Exception $exception) {
            unset($exception);
            return '';
        }
    }

    /**
     * Function get_file_submission
     *
     * @param mixed $submissionid
     * @return false|mixed|stdClass
     * @throws dml_exception
     */
    private function get_file_submission($submissionid): mixed {
        global $DB;
        return $DB->get_record('assignsubmission_edusharing', ['submission' => $submissionid]);
    }


    /**
     * Function get_settings
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform): void {
        try {
            if ($this->assignment->has_instance()) {
                $defaultmaxfilesubmissions = $this->get_config('edumaxfilesubmissions');
            } else {
                $defaultmaxfilesubmissions = get_config('assignsubmission_edusharing', 'maxfiles');
            }

            $options = [];
            for ($i = 1; $i <= get_config('assignsubmission_edusharing', 'maxfiles'); $i++) {
                $options[$i] = $i;
            }

            $name = get_string(
                'maxfilessubmission',
                'assignsubmission_edusharing',
                get_config('edusharing', 'application_appname')
            );
            $mform->addElement('select', 'assignsubmission_edusharing_maxfiles', $name, $options);
            $mform->addHelpButton(
                'assignsubmission_edusharing_maxfiles',
                'maxfilessubmission',
                'assignsubmission_edusharing',
                get_config('edusharing', 'application_appname')
            );
        } catch (Exception $exception) {
            debugging($exception->getMessage());
            return;
        }
        $mform->setDefault('assignsubmission_edusharing_maxfiles', $defaultmaxfilesubmissions);
        $mform->hideIf('assignsubmission_edusharing_maxfiles', 'assignsubmission_edusharing_enabled', 'notchecked');
    }

    /**
     * Function save_settings
     *
     * @param stdClass $formdata
     * @return bool
     */
    public function save_settings(stdClass $formdata): bool {
        $this->set_config('edumaxfilesubmissions', $formdata->assignsubmission_edusharing_maxfiles);
        return true;
    }

    /**
     * Maximum number of edu-sharing objects a student may submit for this assignment.
     *
     * Falls back to the site-wide default and never returns less than one.
     *
     * @return int
     * @throws dml_exception
     */
    private function get_max_file_submissions(): int {
        $max = (int) $this->get_config('edumaxfilesubmissions');
        if ($max < 1) {
            $max = (int) get_config('assignsubmission_edusharing', 'maxfiles');
        }
        return max(1, $max);
    }

    /**
     * Function get_form_elements
     *
     * @param mixed $submission
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        global $PAGE;
        $utils = new UtilityFunctions();
        $appname = get_config('edusharing', 'application_appname');
        $repotargetchooserenabled = (bool)$utils->get_config_entry('enable_repo_target_chooser');
        $maxfiles = $this->get_max_file_submissions();

        // Build the list of already-submitted objects so the JS can render and manage them.
        $existing = [];
        foreach ($this->get_raw_es_files($submission) as $filename => $file) {
            $existing[] = ['filename' => $filename, 'url' => '', 'existing' => true];
        }

        $repourl = trim(get_config('edusharing', 'application_cc_gui_url'), '/');
        $PAGE->requires->js_call_amd('assignsubmission_edusharing/EventListeners', 'init', [
            $repourl, $repotargetchooserenabled, $maxfiles,
        ]);
        $mform->addElement(
            'static',
            'description',
            get_string('description', 'assignsubmission_edusharing', $appname),
            ''
        );

        // Hidden field carrying the selected objects as JSON. Managed entirely by the JS, which
        // appends picked objects (up to $maxfiles) and removes them again. An explicit id is set
        // because hidden elements do not get the auto-generated "id_<name>" the JS relies on.
        $mform->addElement('hidden', 'edu_objects', json_encode($existing), ['id' => 'id_edu_objects']);
        $mform->setType('edu_objects', PARAM_RAW);

        // Container the JS renders the selected-objects list into.
        $mform->addElement(
            'static',
            'edu_object_list',
            get_string('selectedobjects', 'assignsubmission_edusharing', $appname),
            '<div id="eduObjectList"></div>'
        );

        if ($repotargetchooserenabled) {
            // phpcs:disable -- just messy html and js.
            $buttongrouphtml = '
                        <div id="eduChooserButtonGroup" class="btn-group" role="group" aria-label="Repository options">
                            <button type="button" class="btn btn-secondary" data-target="search">' . get_string('repoSearch', 'edusharing') . '</button>
                            <button type="button" class="btn btn-secondary" data-target="workspace">' . get_string('repoWorkspace', 'edusharing') . '</button>
                            <button type="button" class="btn btn-secondary" data-target="collections">' . get_string('repoCollection', 'edusharing') . '</button>
                        </div>
                    ';
            // phpcs:enable
            $mform->addElement('static', 'repo_buttons', '', $buttongrouphtml);
        } else {
            $searchbutton = $mform->addElement(
                'button',
                'searchbutton',
                get_string('searchrec', 'assignsubmission_edusharing', $appname)
            );
            $buttonattributes = [
                'title' => get_string('uploadrec', 'assignsubmission_edusharing', $appname),
            ];
            $searchbutton->updateAttributes($buttonattributes);
        }

        return true;
    }

    /**
     * Function get_file_options
     *
     * @return array
     */
    private function get_file_options() {
        $fileoptions = ['subdirs'      => 1,
                        'maxfiles'     => $this->get_max_file_submissions(),
                        'return_types' => (FILE_EXTERNAL | FILE_REFERENCE),
            ];

        return $fileoptions;
    }

    /**
     * Return the submitted edu-sharing files keyed by file name.
     *
     * @param stdClass $submission
     * @return stored_file[]
     * @throws coding_exception
     */
    private function get_raw_es_files(stdClass $submission): array {
        $fs    = get_file_storage();
        $files = $fs->get_area_files(
            $this->assignment->get_context()->id,
            'assignsubmission_edusharing',
            ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
            $submission->id,
            'timemodified',
            false
        );
        $result = [];
        foreach ($files as $file) {
            $result[$file->get_filename()] = $file;
        }
        return $result;
    }

    /**
     * Count the number of files
     *
     * @param int $submissionid
     * @param string $area
     * @return int
     * @throws coding_exception
     */
    private function count_files($submissionid, $area) {
        $fs    = get_file_storage();
        $files = $fs->get_area_files(
            $this->assignment->get_context()->id,
            'assignsubmission_edusharing',
            $area,
            $submissionid,
            'id',
            false
        );

        return count($files);
    }

    /**
     * Function save
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     * @throws file_exception
     */
    public function save(stdClass $submission, stdClass $data) {
        global $USER, $DB;

        $maxfiles = $this->get_max_file_submissions();

        // The form posts back the complete set of selected objects as JSON. Existing objects carry
        // the "existing" flag, newly picked ones carry a download url.
        $objects = [];
        if (!empty($data->edu_objects)) {
            $decoded = json_decode($data->edu_objects, true);
            if (is_array($decoded)) {
                $objects = $decoded;
            }
        }

        // Remove already-submitted files the student dropped from the list.
        $keptnames = [];
        foreach ($objects as $object) {
            if (!empty($object['existing']) && !empty($object['filename'])) {
                $keptnames[$object['filename']] = true;
            }
        }
        foreach ($this->get_raw_es_files($submission) as $filename => $file) {
            if (empty($keptnames[$filename])) {
                $file->delete();
            }
        }

        // Download and store the newly picked objects, up to the configured maximum.
        $newobjects = array_values(array_filter($objects, function ($object) {
            return empty($object['existing']) && !empty($object['url']) && !empty($object['filename']);
        }));
        if (!empty($newobjects)) {
            try {
                $service = new EduSharingService();
                $ticket  = $service->get_ticket();
            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
                return false;
            }
            $utils = new UtilityFunctions();
            foreach ($newobjects as $object) {
                if ($this->count_files($submission->id, ASSIGNSUBMISSION_EDUSHARING_FILEAREA) >= $maxfiles) {
                    trigger_error(get_string('maxfilesreached', 'assignsubmission_edusharing', $maxfiles), E_USER_WARNING);
                    break;
                }
                $this->store_edu_object($submission, (string) $object['url'], (string) $object['filename'], $ticket, $utils);
            }
        }

        $edusharingsubmission = $this->get_file_submission($submission->id);

        $fs    = get_file_storage();
        $files = $fs->get_area_files(
            $this->assignment->get_context()->id,
            'assignsubmission_edusharing',
            ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
            $submission->id,
            'id',
            false
        );

        $count = count($files);

        // No files left in the submission - keep the bookkeeping record in sync and stop here.
        if ($count === 0) {
            if ($edusharingsubmission) {
                $edusharingsubmission->numfiles = 0;
                $DB->update_record('assignsubmission_edusharing', $edusharingsubmission);
            }
            return true;
        }

        $params = [
            'context'  => context_module::instance($this->assignment->get_course_module()->id),
            'courseid' => $this->assignment->get_course()->id,
            'objectid' => $submission->id,
            'other'    => [
                'content'        => '',
                'pathnamehashes' => array_keys($files),
            ],
        ];
        if (!empty($submission->userid) && ($submission->userid != $USER->id)) {
            $params['relateduserid'] = $submission->userid;
        }
        if ($this->assignment->is_blind_marking()) {
            $params['anonymous'] = 1;
        }
        $event = \assignsubmission_edusharing\event\assessable_uploaded::create($params);
        $event->set_legacy_files($files);
        $event->trigger();

        $groupname = null;
        $groupid   = 0;
        // Get the group name as other fields are not transcribed in the logs and this information is important.
        if (empty($submission->userid) && !empty($submission->groupid)) {
            $groupname = $DB->get_field('groups', 'name', ['id' => $submission->groupid], MUST_EXIST);
            $groupid   = $submission->groupid;
        } else {
            $params['relateduserid'] = $submission->userid;
        }

        // Unset the objectid and other field from params for use in submission events.
        unset($params['objectid']);
        unset($params['other']);
        $params['other'] = [
            'submissionid'        => $submission->id,
            'submissionattempt'   => $submission->attemptnumber,
            'submissionstatus'    => $submission->status,
            'filesubmissioncount' => $count,
            'groupid'             => $groupid,
            'groupname'           => $groupname,
        ];

        if ($edusharingsubmission) {
            $edusharingsubmission->numfiles = $this->count_files(
                $submission->id,
                ASSIGNSUBMISSION_EDUSHARING_FILEAREA
            );
            $updatestatus                   = $DB->update_record('assignsubmission_edusharing', $edusharingsubmission);
            $params['objectid']             = $edusharingsubmission->id;

            $event = \assignsubmission_edusharing\event\submission_updated::create($params);
            $event->set_assign($this->assignment);
            $event->trigger();

            return $updatestatus;
        } else {
            $edusharingsubmission             = new stdClass();
            $edusharingsubmission->numfiles   = $this->count_files(
                $submission->id,
                ASSIGNSUBMISSION_EDUSHARING_FILEAREA
            );
            $edusharingsubmission->submission = $submission->id;
            $edusharingsubmission->assignment = $this->assignment->get_instance()->id;
            $edusharingsubmission->id         = $DB->insert_record('assignsubmission_edusharing', $edusharingsubmission);
            $params['objectid']               = $edusharingsubmission->id;

            $event = \assignsubmission_edusharing\event\submission_created::create($params);
            $event->set_assign($this->assignment);
            $event->trigger();
            return $edusharingsubmission->id > 0;
        }
    }

    /**
     * Download a single edu-sharing object and store it as a submission file.
     *
     * Validates the file name and source url, appends the ticket, rewrites the host to the
     * internal url when one is configured and skips duplicates. Failures are logged and reported
     * back as false so the remaining objects can still be stored.
     *
     * @param stdClass $submission
     * @param string $url The edu-sharing download url.
     * @param string $filename
     * @param string $ticket
     * @param UtilityFunctions $utils
     * @return bool true when the file was stored.
     * @throws coding_exception
     * @throws dml_exception
     */
    private function store_edu_object(
        stdClass $submission,
        string $url,
        string $filename,
        string $ticket,
        UtilityFunctions $utils
    ): bool {
        $filename = trim($filename);
        if ($filename === '' || $url === '') {
            return false;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === '') {
            trigger_error(get_string('edu_extension_error', 'assignsubmission_edusharing'), E_USER_WARNING);
            return false;
        }
        $blockedextensions = [
            'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8',
            'phar', 'phpt', 'pht', 'phtm', 'shtml', 'shtm',
            'htaccess', 'svg',
        ];
        if (in_array($extension, $blockedextensions, true)) {
            trigger_error("Invalid file type", E_USER_WARNING);
            return false;
        }

        $repourl      = trim(get_config('edusharing', 'application_cc_gui_url'), '/');
        $fileurl      = $url;
        $repourlparts = parse_url($repourl);
        $fileurlparts = parse_url($fileurl);
        if (
            empty($repourlparts['scheme']) ||
            empty($repourlparts['host']) ||
            empty($fileurlparts['scheme']) ||
            empty($fileurlparts['host']) ||
            strcasecmp($repourlparts['scheme'], $fileurlparts['scheme']) !== 0 ||
            strcasecmp($repourlparts['host'], $fileurlparts['host']) !== 0
        ) {
            trigger_error("Invalid repo url: $fileurl", E_USER_WARNING);
            return false;
        }

        $fileurl .= (strpos($fileurl, '?') ? '&' : '?') . 'ticket=' . $ticket;
        $fileurl .= '&onlyDownloadable=true';

        $fs = get_file_storage();
        if ($fs->file_exists(
            $this->assignment->get_context()->id,
            'assignsubmission_edusharing',
            ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
            $submission->id,
            '/',
            $filename
        )) {
            // A file with this name is already stored - avoid a duplicate.
            return false;
        }

        $internalurl = $utils->get_internal_url();
        if (!empty($internalurl)) {
            // Replace the origin (scheme://host:port) of the file url with the internal url's
            // origin, keeping the path, query and fragment of the original file url intact. Only
            // the internal origin is used so any context path on the internal url is not duplicated
            // with the one already present in the file url's path.
            $fileauthstart = strpos($fileurl, '://') + 3;
            $filepathstart = $fileauthstart + strcspn($fileurl, '/?#', $fileauthstart);

            $intschemeend   = strpos($internalurl, '://');
            $internalorigin = $intschemeend === false
                ? $internalurl
                : substr($internalurl, 0, ($intschemeend + 3) + strcspn($internalurl, '/?#', $intschemeend + 3));

            $fileurl = $internalorigin . substr($fileurl, $filepathstart);
        }

        $fileinfo = [
            'contextid' => $this->assignment->get_context()->id,
            'component' => 'assignsubmission_edusharing',
            'filearea'  => ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
            'itemid'    => $submission->id,
            'filepath'  => '/',
            'filename'  => $filename,
        ];
        try {
            $fs->create_file_from_url($fileinfo, $fileurl);
        } catch (Exception $exception) {
            trigger_error($exception->getMessage(), E_USER_WARNING);
            return false;
        }
        return true;
    }

    /**
     * Remove files from this submission.
     *
     * @param stdClass $submission The submission
     * @return boolean
     * @throws dml_exception
     */
    public function remove(stdClass $submission) {
        global $DB;
        $fs = get_file_storage();

        $fs->delete_area_files(
            $this->assignment->get_context()->id,
            'assignsubmission_edusharing',
            ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
            $submission->id
        );

        $currentsubmission = $this->get_file_submission($submission->id);
        if ($currentsubmission) {
            $currentsubmission->numfiles = 0;
            $DB->update_record('assignsubmission_edusharing', $currentsubmission);
        }

        return true;
    }

    /**
     * Produce a list of files suitable for export that represent this feedback or submission
     *
     * @param stdClass $submission The submission
     * @param stdClass $user The user record - unused
     * @return array - return an array of files indexed by filename
     * @throws coding_exception
     */
    public function get_files(stdClass $submission, stdClass $user) {
        return $this->get_es_files($submission);
    }

    /**
     * Get all area files for edu-sharing
     *
     * @param stdClass $submission
     * @return array
     * @throws coding_exception
     */
    private function get_es_files(stdClass $submission): array {
        $result = [];
        $fs     = get_file_storage();

        $files = $fs->get_area_files(
            $this->assignment->get_context()->id,
            'assignsubmission_edusharing',
            ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
            $submission->id,
            'timemodified',
            false
        );

        foreach ($files as $file) {
            // Do we return the full folder path or just the file name?
            if (isset($submission->exportfullpath) && $submission->exportfullpath == false) {
                $result[$file->get_filename()] = $file;
            } else {
                $result[$file->get_filepath() . $file->get_filename()] = $file;
            }
        }
        return $result;
    }

    /**
     * Display the list of files  in the submission status table
     *
     * @param stdClass $submission
     * @param bool $showviewlink Set this to true if the list of files is long
     * @return string
     * @throws coding_exception
     */
    public function view_summary(stdClass $submission, &$showviewlink) {
        $count = $this->count_files($submission->id, ASSIGNSUBMISSION_EDUSHARING_FILEAREA);
        // Show we show a link to view all files for this plugin?
        $showviewlink = $count > ASSIGNSUBMISSION_EDUSHARING_MAXSUMMARYFILES;
        if ($count <= ASSIGNSUBMISSION_EDUSHARING_MAXSUMMARYFILES) {
            return $this->assignment->render_area_files(
                'assignsubmission_edusharing',
                ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
                $submission->id
            );
        } else {
            return get_string('countfiles', 'assignsubmission_edusharing', $count);
        }
    }

    /**
     * No full submission view - the summary contains the list of files and that is the whole submission
     *
     * @param stdClass $submission
     * @return string
     */
    public function view(stdClass $submission) {
        return $this->assignment->render_area_files(
            'assignsubmission_edusharing',
            ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
            $submission->id
        );
    }

    /**
     * The assignment has been deleted - cleanup
     *
     * @return bool
     * @throws dml_exception
     */
    public function delete_instance() {
        global $DB;
        // Will throw exception on failure.
        $DB->delete_records(
            'assignsubmission_edusharing',
            ['assignment' => $this->assignment->get_instance()->id]
        );

        return true;
    }

    /**
     * Return true if there are no submission files
     * @param stdClass $submission
     * @throws coding_exception
     */
    public function is_empty(stdClass $submission) {
        return $this->count_files($submission->id, ASSIGNSUBMISSION_EDUSHARING_FILEAREA) == 0;
    }

    /**
     * Determine if a submission is empty
     *
     * This is distinct from is_empty in that it is intended to be used to
     * determine if a submission made before saving is empty.
     *
     * @param stdClass $data The submission data
     * @return bool
     */
    public function submission_is_empty(stdClass $data) {
        if (empty($data->edu_objects)) {
            return true;
        }
        $decoded = json_decode($data->edu_objects, true);
        return !is_array($decoded) || count($decoded) === 0;
    }

    /**
     * Get file areas returns a list of areas this plugin stores files
     * @return array - An array of fileareas (keys) and descriptions (values)
     */
    public function get_file_areas() {
        return [ASSIGNSUBMISSION_EDUSHARING_FILEAREA => $this->get_name()];
    }

    /**
     * Copy the student's submission from a previous submission. Used when a student opts to base their resubmission
     * on the last submission.
     *
     * @param stdClass $sourcesubmission
     * @param stdClass $destsubmission
     * @return true
     * @throws coding_exception
     * @throws dml_exception
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission) {
        global $DB;

        // Copy the files across.
        $contextid = $this->assignment->get_context()->id;
        $fs        = get_file_storage();
        $files     = $fs->get_area_files(
            $contextid,
            'assignsubmission_edusharing',
            ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
            $sourcesubmission->id,
            'id',
            false
        );
        foreach ($files as $file) {
            $fieldupdates = ['itemid' => $destsubmission->id];
            $fs->create_file_from_storedfile($fieldupdates, $file);
        }

        // Copy the assignsubmission_file record.
        if ($filesubmission = $this->get_file_submission($sourcesubmission->id)) {
            unset($filesubmission->id);
            $filesubmission->submission = $destsubmission->id;
            $DB->insert_record('assignsubmission_edusharing', $filesubmission);
        }
        return true;
    }
}
