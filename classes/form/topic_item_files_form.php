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
 * OJT topic item file upload form
 *
 * @package   mod_ojt
 * @author    Alex Morris <alex.morris@catalyst.net.nz
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ojt\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

/**
 * OJT topic item file upload form
 *
 * @package   mod_ojt
 * @author    Alex Morris <alex.morris@catalyst.net.nz
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class topic_item_files_form extends moodleform {
    /**
     * Requires the following $_customdata to be passed into the constructor:
     * topicitemid, userid.
     *
     * @global object $DB
     */
    function definition() {
        global $FILEPICKER_OPTIONS;

        $mform =& $this->_form;

        // Determine permissions from evidence
        $topicitemid = $this->_customdata['topicitemid'];
        $userid = $this->_customdata['userid'];
        $fileoptions = isset($this->_customdata['fileoptions']) ? $this->_customdata['fileoptions'] : $FILEPICKER_OPTIONS;

        $mform->addElement('hidden', 'tiid', $topicitemid);
        $mform->setType('tiid', PARAM_INT);

        $mform->addElement('hidden', 'userid', $userid);
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('filemanager', 'topicitemfiles_filemanager',
            get_string('topicitemfiles', 'ojt'), null, $fileoptions);

        $this->add_action_buttons(true, get_string('updatefiles', 'ojt'));
    }
}
