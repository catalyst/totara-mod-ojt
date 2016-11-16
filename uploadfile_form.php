<?php
/*
 * Copyright (C) 2015 onwards Catalyst IT
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author  Eugene Venter <eugene@catalyst.net.nz>
 * @package mod_ojt
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The form for editing evidence
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    //  It must be included from a Moodle page
}

require_once("{$CFG->libdir}/formslib.php");
require_once("{$CFG->libdir}/uploadlib.php");

class ojt_topicitem_files_form extends moodleform {

    /**
     * Requires the following $_customdata to be passed into the constructor:
     * topicitemid, userid.
     *
     * @global object $DB
     */
    function definition() {
        global $DB, $FILEPICKER_OPTIONS;

        $mform =& $this->_form;

        // Determine permissions from evidence
        $topicitemid = $this->_customdata['topicitemid'];
        $userid = $this->_customdata['userid'];
        $evidencetypeid = isset($this->_customdata['evidencetypeid']) ? $this->_customdata['evidencetypeid'] : null;
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
