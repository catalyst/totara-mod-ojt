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
 * OJT topic item form
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
 * OJT topic item form
 *
 * @package   mod_ojt
 * @author    Alex Morris <alex.morris@catalyst.net.nz
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class topic_item_form extends moodleform {
    function definition() {
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
