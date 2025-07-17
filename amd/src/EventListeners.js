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
 * Tiny edu-sharing Content configuration.
 *
 * @module      assignsubmission_edusharing/EventListeners
 * @copyright   2024 metaVentis GmbH <http://metaventis.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @param       {string} repoUrl
 * @param       {string} ticket
 */
export const init = (repoUrl, ticket) => {
    let isRepoListenerRegistered = false;
    const applyEventListener = () => {
        if (isRepoListenerRegistered) {
            return;
        }
        window.addEventListener('message', function handleRepo(event) {
            if (event.data.event === 'APPLY_NODE') {
                const node = event.data.data;
                window.win.close();
                let filename = node.properties['cm:name'][0];
                let extension = filename.split('.').pop();
                if (!extension || extension.length === 0) {
                    const mimeType = node.mimetype;
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
                    if (typeMap[mimeType]) {
                        filename += '.' + typeMap[mimeType];
                    }
                }
                window.document.getElementById('id_edu_url').value = node.downloadUrl;
                window.document.getElementById('id_edu_filename').value = filename;
                window.removeEventListener('message', handleRepo, false);
            }
        }, false);
        isRepoListenerRegistered = true;
    };

    /**
     * Function getRepoTargetUrl
     * @param {Element} element
     */
    const getRepoTargetUrl = (element) => {
        const isSimpleSearchButton = element.id === 'id_searchbutton';
        if (isSimpleSearchButton) {
            return repoUrl + '/components/search' + '?reurl=WINDOW&applyDirectories=false&ticket=' + ticket;
        }
        const target = element.getAttribute('data-target');
        let repoTarget;
        switch (target) {
            case 'collections':
                repoTarget = '/components/collections';
                break;
            case 'workspace':
                repoTarget = '/components/workspace/files';
                break;
            default:
                repoTarget = '/components/search';
        }
        return repoUrl + repoTarget + '?reurl=WINDOW&applyDirectories=false&ticket=' + ticket;
    };

    /**
     * Function onClick
     * @param {Event}event
     */
    const onClick = (event) => {
        applyEventListener();
        window.win = window.open(getRepoTargetUrl(event.target));
    };

    const repoButton = document.getElementById('id_searchbutton');
    if (repoButton !== null) {
        repoButton.addEventListener("click", onClick);
    }
    const buttonGroupContainer = document.getElementById('eduChooserButtonGroup');
    if (buttonGroupContainer !== null) {
        for (const button of buttonGroupContainer.querySelectorAll('button')) {
            button.addEventListener("click", onClick);
        }
    }

    const removeButton = document.getElementById('id_eduRemoveButton');
    if (removeButton !== null) {
        const removeCallback = () => {
            window.document.getElementById('id_edu_filename').value = '';
            window.document.getElementById('id_edu_url').value = '';
        };
        removeButton.addEventListener("click", removeCallback);
    }
};

