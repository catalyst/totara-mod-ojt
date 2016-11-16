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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir . '/formslib.php');

/**
 * OJT topic form
 */
class ojt_topic_form extends moodleform {
    function definition() {
        global $CFG;
        $mform =& $this->_form;
        $courseid = $this->_customdata['courseid'];
        $ojtid = $this->_customdata['ojtid'];

        $mform->addElement('text', 'name', get_string('name', 'ojt'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'completionreq', get_string('optionalcompletion', 'ojt'));

        if (!empty($CFG->enablecompetencies)) {
            require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/lib.php');
            $competency = new competency();
            $coursecomps = $competency->get_course_evidence($courseid);
            $competencies = array();
            foreach ($coursecomps as $c) {
                $competencies[$c->id] = format_string($c->fullname);
            }
            if (!empty($competencies)) {
                $select = $mform->addElement('select', 'competencies', get_string('competencies', 'ojt'), $competencies, array('size' => 7));
                $select->setMultiple(true);
                $mform->setType('competencies', PARAM_INT);
                $mform->addHelpButton('competencies', 'competencies', 'ojt');
            }
        }

        if ($CFG->usecomments) {
            $mform->addElement('advcheckbox', 'allowcomments', get_string('allowcomments', 'ojt'));
        } else {
            $mform->addElement('hidden', 'allowcomments', false);
        }
        $mform->setType('allowcomments', PARAM_BOOL);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'bid');
        $mform->setType('bid', PARAM_INT);
        $mform->setDefault('bid', $ojtid);

        $this->add_action_buttons(false);
    }
}


/**
 * OJT topic item form
 */
class ojt_topic_item_form extends moodleform {
    function definition() {
        global $CFG;
        $mform =& $this->_form;
        $ojtid = $this->_customdata['ojtid'];
        $topicid = $this->_customdata['topicid'];

        $mform->addElement('text', 'name', get_string('name', 'ojt'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'completionreq', get_string('optionalcompletion', 'ojt'));

        $mform->addElement('advcheckbox', 'allowfileuploads', get_string('allowfileuploads', 'ojt'));
        $mform->setType('allowfileuploads', PARAM_BOOL);

        $mform->addElement('advcheckbox', 'allowselffileuploads', get_string('allowselffileuploads', 'ojt'));
        $mform->setType('allowselffileuploads', PARAM_BOOL);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'bid');
        $mform->setType('bid', PARAM_INT);
        $mform->setDefault('bid', $ojtid);
        $mform->addElement('hidden', 'tid');
        $mform->setType('tid', PARAM_INT);
        $mform->setDefault('tid', $topicid);

        $this->add_action_buttons(false);
    }
}

