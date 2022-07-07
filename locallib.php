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

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot.'/mod/edusharing/lib/cclib.php');
require_once($CFG->dirroot.'/mod/edusharing/locallib.php');

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
    public function get_name() {
        return get_string('edusharing', 'assignsubmission_edusharing', get_config('edusharing', 'application_appname'));
    }

    private function get_file_submission($submissionid) {
        global $DB;
        return $DB->get_record('assignsubmission_edusharing', array('submission'=>$submissionid));
    }



    public function get_settings(MoodleQuickForm $mform) {
        global $CFG, $COURSE;

        if ($this->assignment->has_instance()) {
            $defaultmaxfilesubmissions = $this->get_config('edumaxfilesubmissions');
        } else {
            $defaultmaxfilesubmissions = get_config('assignsubmission_edusharing', 'maxfiles');
        }

        $settings = array();
        $options = array();
        for ($i = 1; $i <= get_config('assignsubmission_edusharing', 'maxfiles'); $i++) {
            $options[$i] = $i;
        }

        $name = get_string('maxfilessubmission', 'assignsubmission_edusharing', get_config('edusharing', 'application_appname'));
        $mform->addElement('select', 'assignsubmission_edusharing_maxfiles', $name, $options);
        $mform->addHelpButton('assignsubmission_edusharing_maxfiles',
            'maxfilessubmission',
            'assignsubmission_edusharing', get_config('edusharing', 'application_appname'));
        $mform->setDefault('assignsubmission_edusharing_maxfiles', $defaultmaxfilesubmissions);
        $mform->hideIf('assignsubmission_edusharing_maxfiles', 'assignsubmission_edusharing_enabled', 'notchecked');

    }

    public function save_settings(stdClass $data) {

        $this->set_config('edumaxfilesubmissions', $data->assignsubmission_edusharing_maxfiles);

        return true;
    }

    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        global $CFG;

        if ($this->get_config('edumaxfilesubmissions') <= 0) {
            return false;
        }

        try {
            $ccauth = new mod_edusharing_web_service_factory();
            $ticket = $ccauth->edusharing_authentication_get_ticket();
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }

        $mform->addElement('static', 'description', get_string('description', 'assignsubmission_edusharing', get_config('edusharing', 'application_appname')),
            '');

        // object-uri
        $mform->addElement('text', 'edu_url', get_string('edu_url', 'assignsubmission_edusharing', get_config('edusharing', 'application_appname')), array('readonly' => 'true'));
        $mform->setType('edu_url', PARAM_RAW_TRIMMED);
        $mform->addRule('edu_url', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $checkExtension = function ($val) {
            $file_parts  = pathinfo($val);
            if ( empty($file_parts["extension"]) ) {
                //error_log('no extension');
                return false;
            }
            return true;
        };

        $mform->addElement('text', 'edu_filename', get_string('edu_filename', 'assignsubmission_edusharing', get_config('edusharing', 'application_appname')));
        $mform->setType('edu_filename', PARAM_RAW_TRIMMED);
        $mform->addRule('edu_filename', get_string('edu_extension_error', 'assignsubmission_edusharing'), 'callback', $checkExtension, 'server', false, true);




        $repoSearch = trim(get_config('edusharing', 'application_cc_gui_url'), '/') . '/components/search?&applyDirectories=true&reurl=WINDOW&ticket=' . $ticket;
        $searchbutton = $mform->addElement('button', 'searchbutton', get_string('searchrec', 'assignsubmission_edusharing', get_config('edusharing', 'application_appname')));
        $repoOnClick = "
                            function openRepo(){
                                window.addEventListener('message', function handleRepo(event) {
                                    if (event.data.event == 'APPLY_NODE') {
                                        const node = event.data.data;
                                        //window.console.log(node);
                                        window.win.close();
                                        
                                        let filename = node.properties['cm:name'][0];
                                        let extension = filename.slice((filename.lastIndexOf('.') - 1 >>> 0) + 2);
                                                 
                                        if(!extension || extension.length === 0){
                                            const mimeType = node.mimetype;
                                            //console.log('mimetype: ' + mimeType);
                                            
                                            const typeMap = {
                                                'image/jpeg': 'jpeg',
                                                'image/png': 'png',
                                                'image/gif': 'gif',
                                                'image/bmp': 'bmp',
                                                'image/tiff': 'tiff',
                                                'image/tif': 'tif',
                                                'image/photoshop': 'psd',
                                                'image/xcf': 'xcf',
                                                'image/pcx': 'pcx',
                                                
                                                'video/x-msvideo': 'avi',
                                                'video/mpeg': 'mpg',
                                                'video/x-flash': 'flv',
                                                'video/x-ms-wmv': 'wmv',
                                                'video/mp4': 'mp4',
                                                'video/3gpp': '3gp',
                                                
                                                'audio/wav': 'wav',
                                                'audio/mpeg': 'mp3',
                                                'audio/mid': 'mid',
                                                'audio/ogg': 'ogg',
                                                'audio/aiff': 'aif',
                                                'audio/basic': 'au',
                                                'audio/voxware': 'vox',
                                                'audio/x-ms-wma': 'wma',
                                                'audio/x-pn-realaudi': 'ram',
                                                
                                                'application/vnd.oasis.opendocument.text': 'odt',
                                                'application/vnd.oasis.opendocument.text-template': 'ott',
                                                'application/vnd.oasis.opendocument.text-web': 'oth',
                                                'application/vnd.oasis.opendocument.text-master': 'odm',
                                                'application/vnd.oasis.opendocument.graphics': 'odg',
                                                'application/vnd.oasis.opendocument.graphics-template': 'otg',
                                                'application/vnd.oasis.opendocument.presentation': 'odp',
                                                'application/vnd.oasis.opendocument.presentation-template': 'otp',
                                                'application/vnd.oasis.opendocument.spreadsheet': 'ods',
                                                'application/vnd.oasis.opendocument.spreadsheet-template': 'ots',
                                                'application/vnd.oasis.opendocument.chart': 'odc',
                                                'application/vnd.oasis.opendocument.formula': 'odf',
                                                'application/vnd.oasis.opendocument.database': 'odb',
                                                'application/vnd.oasis.opendocument.image': 'odi',
                                                'application/vnd.oasis.opendocument.image': 'odi',
                                                
                                                'application/vnd.ms-powerpoint': 'ppt',
                                                
                                                'application/msword': 'doc',
                                                'application/vnd.ms-word.document.macroEnabled.12': 'docm',
                                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
                                                'application/vnd.ms-word.template.macroEnabled.12': 'dotm',
                                                'application/vnd.openxmlformats-officedocument.wordprocessingml.template': 'dotx',
                                                'application/vnd.ms-powerpoint.slideshow.macroEnabled.12': 'ppsm',
                                                'application/vnd.openxmlformats-officedocument.presentationml.slideshow': 'ppsx',
                                                'application/vnd.ms-powerpoint.presentation.macroEnabled.12': 'pptm',
                                                'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'pptx',
                                                'application/vnd.ms-excel.sheet.binary.macroEnabled.12': 'xlsb',
                                                'application/vnd.ms-excel.sheet.macroEnabled.12': 'xlsm',
                                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
                                                'application/vnd.ms-xpsdocument': 'xps',
                                                'application/vnd.ms-excel': 'xls',
                                                
                                                'text/plain': 'txt',
                                                'application/pdf': 'pdf',
                                                'application/zip': 'zip',
                                                'application/epub+zip': 'epub',
                                                'text/xml': 'xml',
                                                
                                                'application/vnd.apple.pages': 'pages',
                                                'application/vnd.apple.keynote': 'keynote',
                                                'application/vnd.apple.numbers': 'numbers',
                                            };                                             
                                            
                                            if(typeMap[mimeType]){
                                                filename += '.' + typeMap[mimeType];
                                            }                                            
                                            
                                        }
                                        
                                        window.document.getElementById('id_edu_url').value = node.downloadUrl;
                                        window.document.getElementById('id_edu_filename').value = filename;
                                        
                                        window.removeEventListener('message', handleRepo, false );
                                    }                                    
                                }, false);
                                window.win = window.open('".$repoSearch."');                                                          
                            }
                            openRepo();
                        ";
        $buttonattributes = array('title' => get_string('uploadrec', 'assignsubmission_edusharing', get_config('edusharing', 'application_appname')), 'onclick' => $repoOnClick);
        $searchbutton->updateAttributes($buttonattributes);

        return true;
    }

    private function get_file_options() {
        $fileoptions = array('subdirs' => 1,
            'maxfiles' => $this->get_config('edumaxfilesubmissions'),
            'return_types' => (FILE_EXTERNAL | FILE_REFERENCE));

        return $fileoptions;
    }

    /**
     * Count the number of files
     *
     * @param int $submissionid
     * @param string $area
     * @return int
     */
    private function count_files($submissionid, $area) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->assignment->get_context()->id,
            'assignsubmission_edusharing',
            $area,
            $submissionid,
            'id',
            false);

        return count($files);
    }

    public function save(stdClass $submission, stdClass $data) {
        global $USER, $DB, $CFG;

        if (empty($data->edu_url)){
            return;
        }

        try {
            $ccauth = new mod_edusharing_web_service_factory();
            $ticket = $ccauth->edusharing_authentication_get_ticket();
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }

        $edusharingsubmission = $this->get_file_submission($submission->id);


        $file_url = $data->edu_url;
        if (strpos($file_url, '?')){
            $file_url .= '&ticket=' . $ticket;
        }else{
            $file_url .= '?ticket=' . $ticket;
        }

        $fileinfo = [
            'contextid' => $this->assignment->get_context()->id,    // ID of the context.
            'component' => 'assignsubmission_edusharing',           // Your component name.
            'filearea'  => ASSIGNSUBMISSION_EDUSHARING_FILEAREA,    // Usually = table name.
            'itemid'    => $submission->id,                         // Usually = ID of row in table.
            'filepath'  => '/',                                     // Any path beginning and ending in /.
            'filename'  => $data->edu_filename,                     // Any filename.
            'maxfiles' => $this->get_config('edumaxfilesubmissions'),
        ];
        $fs = get_file_storage();
        // Create a new file containing the text 'hello world'.
        $fs->create_file_from_url($fileinfo, $file_url);


        $fs = get_file_storage();
        $files = $fs->get_area_files($this->assignment->get_context()->id,
            'assignsubmission_edusharing',
            ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
            $submission->id,
            'id',
            false);

        $count = $this->count_files($submission->id, ASSIGNSUBMISSION_FILE_FILEAREA);

        $params = array(
            'context' => context_module::instance($this->assignment->get_course_module()->id),
            'courseid' => $this->assignment->get_course()->id,
            'objectid' => $submission->id,
            'other' => array(
                'content' => '',
                'pathnamehashes' => array_keys($files)
            )
        );
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
        $groupid = 0;
        // Get the group name as other fields are not transcribed in the logs and this information is important.
        if (empty($submission->userid) && !empty($submission->groupid)) {
            $groupname = $DB->get_field('groups', 'name', array('id' => $submission->groupid), MUST_EXIST);
            $groupid = $submission->groupid;
        } else {
            $params['relateduserid'] = $submission->userid;
        }

        // Unset the objectid and other field from params for use in submission events.
        unset($params['objectid']);
        unset($params['other']);
        $params['other'] = array(
            'submissionid' => $submission->id,
            'submissionattempt' => $submission->attemptnumber,
            'submissionstatus' => $submission->status,
            'filesubmissioncount' => $count,
            'groupid' => $groupid,
            'groupname' => $groupname
        );

        if ($edusharingsubmission) {
            $edusharingsubmission->numfiles = $this->count_files($submission->id,
                ASSIGNSUBMISSION_EDUSHARING_FILEAREA);
            $updatestatus = $DB->update_record('assignsubmission_edusharing', $edusharingsubmission);
            $params['objectid'] = $edusharingsubmission->id;

            $event = \assignsubmission_edusharing\event\submission_updated::create($params);
            $event->set_assign($this->assignment);
            $event->trigger();

            return $updatestatus;
        } else {
            $edusharingsubmission = new stdClass();
            $edusharingsubmission->numfiles = $this->count_files($submission->id,
                ASSIGNSUBMISSION_EDUSHARING_FILEAREA);
            $edusharingsubmission->submission = $submission->id;
            $edusharingsubmission->assignment = $this->assignment->get_instance()->id;
            $edusharingsubmission->id = $DB->insert_record('assignsubmission_edusharing', $edusharingsubmission);
            $params['objectid'] = $edusharingsubmission->id;

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
     */
    public function get_files(stdClass $submission, stdClass $user) {
        $result = array();
        $fs = get_file_storage();

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
                $result[$file->get_filepath().$file->get_filename()] = $file;
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
     */
    public function view_summary(stdClass $submission, & $showviewlink) {
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
     */
    public function delete_instance() {
        global $DB;
        // Will throw exception on failure.
        $DB->delete_records('assignsubmission_edusharing',
            array('assignment'=>$this->assignment->get_instance()->id));

        return true;
    }

    /**
     * Return true if there are no submission files
     * @param stdClass $submission
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
        return array(ASSIGNSUBMISSION_EDUSHARING_FILEAREA=>$this->get_name());
    }

    /**
     * Copy the student's submission from a previous submission. Used when a student opts to base their resubmission
     * on the last submission.
     * @param stdClass $sourcesubmission
     * @param stdClass $destsubmission
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission) {
        global $DB;

        // Copy the files across.
        $contextid = $this->assignment->get_context()->id;
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid,
            'assignsubmission_edusharing',
            ASSIGNSUBMISSION_EDUSHARING_FILEAREA,
            $sourcesubmission->id,
            'id',
            false);
        foreach ($files as $file) {
            $fieldupdates = array('itemid' => $destsubmission->id);
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