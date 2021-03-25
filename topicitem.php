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

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/forms.php');

$ojtid = required_param('bid', PARAM_INT); // OJT instance id.
$topicid  = required_param('tid', PARAM_INT);  // Topic id.
$itemid = optional_param('id', 0, PARAM_INT);  // Topic item id.
$delete = optional_param('delete', 0, PARAM_BOOL);

$ojt = $DB->get_record('ojt', array('id' => $ojtid), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $ojt->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('ojt', $ojt->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ojt:manage', $context);

$PAGE->set_url('/mod/ojt/topicitem.php', array('bid' => $ojtid, 'tid' => $topicid, 'id' => $itemid));

// Handle actions
if ($delete) {
    $confirm = optional_param('confirm', 0, PARAM_BOOL);
    if (!$confirm) {
        echo $OUTPUT->header();
        $confirmurl = $PAGE->url;
        $confirmurl->params(array('delete' => 1, 'confirm' => 1, 'sesskey' => sesskey()));
        echo $OUTPUT->confirm(get_string('confirmitemdelete', 'ojt'), $confirmurl, $PAGE->url);
        echo $OUTPUT->footer();
        die();
    }

    require_sesskey();

    ojt_delete_topic_item($itemid, $context);
    $redirecturl = new moodle_url('/mod/ojt/manage.php', array('cmid' => $cm->id));
    redirect($redirecturl, get_string('itemdeleted', 'ojt'));
}

$form = new ojt_topic_item_form(null, array('ojtid' => $ojtid, 'topicid' => $topicid));
if ($data = $form->get_data()) {
    // Save topic
    $item = new stdClass();
    $item->topicid = $data->tid;
    $item->name = $data->name;
    $item->completionreq = $data->completionreq;
    $item->allowfileuploads = $data->allowfileuploads;
    $item->allowselffileuploads = $data->allowselffileuploads;

    if (empty($data->id)) {
        // Add
        $DB->insert_record('ojt_topic_item', $item);
    } else {
        // Update
        $item->id = $data->id;
        $DB->update_record('ojt_topic_item', $item);
    }

    redirect(new moodle_url('/mod/ojt/manage.php', array('cmid' => $cm->id)));
}

// Print the page header.
$actionstr = empty($itemid) ? get_string('additem', 'ojt') : get_string('edititem', 'ojt');
$PAGE->set_title(format_string($ojt->name));
$PAGE->set_heading(format_string($ojt->name).' - '.$actionstr);

// Output starts here.
echo $OUTPUT->header();

// Replace the following lines with you own code.
echo $OUTPUT->heading($PAGE->heading);

if (!empty($itemid)) {
    $item = $DB->get_record('ojt_topic_item', array('id' => $itemid), '*', MUST_EXIST);
    $form->set_data($item);
}

// Display
$form->display();

// Finish the page.
echo $OUTPUT->footer();
