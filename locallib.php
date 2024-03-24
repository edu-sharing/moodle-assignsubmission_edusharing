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
            unset ($exception);
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
        return;
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

            $name = get_string('maxfilessubmission', 'assignsubmission_edusharing',
                get_config('edusharing', 'application_appname'));
            $mform->addElement('select', 'assignsubmission_edusharing_maxfiles', $name, $options);
            $mform->addHelpButton('assignsubmission_edusharing_maxfiles',
                'maxfilessubmission',
                'assignsubmission_edusharing', get_config('edusharing', 'application_appname'));
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
        return true;
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
        $existingfilename = '';
        // If there is one file we are in edit mode.
        if ($this->count_files($submission->id, ASSIGNSUBMISSION_EDUSHARING_FILEAREA) > 0) {
            $allesfiles = $this->get_es_files($submission);
            $existingfilename = array_keys($allesfiles)[0];
            $lastslash = strrpos($existingfilename, '/');
            if ($lastslash !== false) {
                $existingfilename = substr($existingfilename, $lastslash + 1);
            }
        }
        try {
            $service = new EduSharingService();
            $ticket  = $service->get_ticket();
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
        $reposearch = trim(
                get_config('edusharing', 'application_cc_gui_url'), '/'
            ) . '/components/workspace?&applyDirectories=true&reurl=WINDOW&ticket=' . $ticket;
        $PAGE->requires->js_call_amd('assignsubmission_edusharing/EventListeners', 'init', [
            $reposearch,
        ]);
        $mform->addElement('static', 'description',
            get_string('description', 'assignsubmission_edusharing',
                get_config('edusharing', 'application_appname')), '');

        $mform->addElement('text', 'edu_edit_mode', 'edit_mode', ['readonly' => 'true']);
        $mform->setType('edu_edit_mode', PARAM_RAW_TRIMMED);
        // Toggle edit mode.
        $mform->setDefault('edu_edit_mode', $existingfilename !== "" ? 1 : 0);

        $mform->addElement('text', 'edu_url',
            get_string('edu_url', 'assignsubmission_edusharing',
                get_config('edusharing', 'application_appname')), ['readonly' => 'true']);
        $mform->setType('edu_url', PARAM_RAW_TRIMMED);
        $mform->addRule('edu_url', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $checkextension = function ($val) {
            $fileparts = pathinfo($val);
            if (empty($fileparts["extension"])) {
                return false;
            }
            return true;
        };

        $mform->addElement('text', 'edu_filename',
            get_string('edu_filename', 'assignsubmission_edusharing',
                get_config('edusharing', 'application_appname')), ['readonly' => 'true']);
        $mform->setType('edu_filename', PARAM_RAW_TRIMMED);
        $mform->addRule('edu_filename',
            get_string('edu_extension_error', 'assignsubmission_edusharing'),
            'callback',
            $checkextension,
            'server',
            false,
            true
        );
        if ($existingfilename !== "") {
            $mform->setDefault('edu_filename', $existingfilename);
        }

        $searchbutton     = $mform->addElement(
            'button',
            'searchbutton',
            get_string('searchrec',
                'assignsubmission_edusharing',
                get_config('edusharing',
                    'application_appname')
            )
        );
        $buttonattributes = [
            'title' => get_string('uploadrec', 'assignsubmission_edusharing',
                get_config('edusharing', 'application_appname')),
        ];
        $searchbutton->updateAttributes($buttonattributes);

        // For edit mode we add a remove es-item button.
        if ($existingfilename !== "") {
            $removebutton = $mform->addElement('button', 'eduRemoveButton',
                get_string('remove_es_object', 'assignsubmission_edusharing')
            );
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
                        'maxfiles'     => 1,
                        'return_types' => (FILE_EXTERNAL | FILE_REFERENCE),
            ];

        return $fileoptions;
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
        $files = $fs->get_area_files($this->assignment->get_context()->id,
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

        // No edu url? This means there is no edu-object to be submitted.
        // In this case, we do not want an error message.
        if (empty($data->edu_url)) {
            if ((int)$data->edu_edit_mode === 1) {
                $this->remove($submission);
            }
            return true;
        }
        // If we are in edit mode and the edu_url is not empty, an object from the repo was added.
        // We have to delete the old one.
        if ((int)$data->edu_edit_mode === 1) {
            $this->remove($submission);
        }

        try {
            $service = new EduSharingService();
            $ticket  = $service->get_ticket();
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }

        if (str_ends_with($data->edu_filename, '.php')) {
            trigger_error("Invalid file type", E_USER_WARNING);
            return false;
        }

        $edusharingsubmission = $this->get_file_submission($submission->id);

        $fileurl = $data->edu_url;
        if (strpos($fileurl, '?')) {
            $fileurl .= '&ticket=' . $ticket;
        } else {
            $fileurl .= '?ticket=' . $ticket;
        }

        $fileurl .= '&onlyDownloadable=true';

        $fileinfo = [
            'contextid' => $this->assignment->get_context()->id,    // ID of the context.
            'component' => 'assignsubmission_edusharing',           // Your component name.
            'filearea'  => ASSIGNSUBMISSION_EDUSHARING_FILEAREA,    // Usually = table name.
            'itemid'    => $submission->id,                         // Usually = ID of row in table.
            'filepath'  => '/',                                     // Any path beginning and ending in /.
            'filename'  => $data->edu_filename,                     // Any filename.
            'maxfiles'  => 1,
        ];
        $fs          = get_file_storage();
        $utils       = new UtilityFunctions();
        $internalurl = $utils->get_internal_url();
        if (!empty($internalurl)) {
            $fileurl = str_replace(rtrim(get_config('edusharing', 'application_cc_gui_url'), '/'), $internalurl, $fileurl);
        }
        $fs->create_file_from_url($fileinfo, $fileurl);

        $fs    = get_file_storage();
        $files = $fs->get_area_files($this->assignment->get_context()->id,
            'assignsubmission_edusharing',
            ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
            $submission->id,
            'id',
            false);

        $count = $this->count_files($submission->id, ASSIGNSUBMISSION_FILE_FILEAREA);

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
            $edusharingsubmission->numfiles = $this->count_files($submission->id,
                ASSIGNSUBMISSION_EDUSHARING_FILEAREA);
            $updatestatus                   = $DB->update_record('assignsubmission_edusharing', $edusharingsubmission);
            $params['objectid']             = $edusharingsubmission->id;

            $event = \assignsubmission_edusharing\event\submission_updated::create($params);
            $event->set_assign($this->assignment);
            $event->trigger();

            return $updatestatus;
        } else {
            $edusharingsubmission             = new stdClass();
            $edusharingsubmission->numfiles   = $this->count_files($submission->id,
                ASSIGNSUBMISSION_EDUSHARING_FILEAREA);
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
     * Remove files from this submission.
     *
     * @param stdClass $submission The submission
     * @return boolean
     * @throws dml_exception
     */
    public function remove(stdClass $submission) {
        global $DB;
        $fs = get_file_storage();

        $fs->delete_area_files($this->assignment->get_context()->id,
            'assignsubmission_edusharing',
            ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
            $submission->id);

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
    private function get_es_files(stdClass $submission): Array {
        $result = [];
        $fs     = get_file_storage();

        $files = $fs->get_area_files($this->assignment->get_context()->id,
            'assignsubmission_edusharing',
            ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
            $submission->id,
            'timemodified',
            false);

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
            return $this->assignment->render_area_files('assignsubmission_edusharing',
                ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
                $submission->id);
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
        return $this->assignment->render_area_files('assignsubmission_edusharing',
            ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
            $submission->id);
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
        $DB->delete_records('assignsubmission_edusharing',
            ['assignment' => $this->assignment->get_instance()->id]);

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
        global $USER;

        return empty($data->edu_url);
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
        $files     = $fs->get_area_files($contextid,
            'assignsubmission_edusharing',
            ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
            $sourcesubmission->id,
            'id',
            false);
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
