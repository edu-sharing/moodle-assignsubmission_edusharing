<?php

/**
 * edu-sharing submisson settings
 *
 * @package    assignsubmission_edusharing
 * @copyright  metaVentis GmbH — http://metaventis.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


$settings->add(new admin_setting_configcheckbox('assignsubmission_edusharing/default',
    new lang_string('default', 'assignsubmission_edusharing'),
    new lang_string('default_help', 'assignsubmission_edusharing'), 0));

$settings->add(new admin_setting_configtext('assignsubmission_edusharing/maxfiles',
    new lang_string('maxfiles', 'assignsubmission_edusharing'),
    new lang_string('maxfiles_help', 'assignsubmission_edusharing'), 8, PARAM_INT));