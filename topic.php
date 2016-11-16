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
$topicid  = optional_param('id', 0, PARAM_INT);  // Topic id.
$delete = optional_param('delete', 0, PARAM_BOOL);

$ojt = $DB->get_record('ojt', array('id' => $ojtid), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $ojt->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('ojt', $ojt->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/ojt:manage', context_module::instance($cm->id));

$PAGE->set_url('/mod/ojt/topic.php', array('bid' => $ojtid, 'id' => $topicid));

// Handle actions
if ($delete) {
    $confirm = optional_param('confirm', 0, PARAM_BOOL);
    if (!$confirm) {
        echo $OUTPUT->header();
        $confirmurl = $PAGE->url;
        $confirmurl->params(array('delete' => 1, 'confirm' => 1, 'sesskey' => sesskey()));
        echo $OUTPUT->confirm(get_string('confirmtopicdelete', 'ojt'), $confirmurl, $PAGE->url);
        echo $OUTPUT->footer();
        die();
    }

    ojt_delete_topic($topicid);
    $redirecturl = new moodle_url('/mod/ojt/manage.php', array('cmid' => $cm->id));
    totara_set_notification(get_string('topicdeleted', 'ojt'), $redirecturl, array('class' => 'notifysuccess'));
}

$form = new ojt_topic_form(null, array('courseid' => $course->id, 'ojtid' => $ojtid));
if ($data = $form->get_data()) {
    // Save topic
    $topic = new stdClass();
    $topic->ojtid = $data->bid;
    $topic->name = $data->name;
    $topic->completionreq = $data->completionreq;
    $topic->competencies = !empty($data->competencies) ? implode(',', $data->competencies) : '';
    $topic->allowcomments = $data->allowcomments;

    if (empty($data->id)) {
        // Add
        $DB->insert_record('ojt_topic', $topic);
    } else {
        // Update
        $topic->id = $data->id;

        $transaction = $DB->start_delegated_transaction();
        $DB->update_record('ojt_topic', $topic);

        if (!empty($topic->competencies)) {
            // We need to add 'proficient' competency records for any historical user topic completions
            $topiccompletions = $DB->get_records_select('ojt_completion', 'topicid = ? AND type = ? AND status IN(?,?)',
                array($data->id, OJT_CTYPE_TOPIC, OJT_REQUIREDCOMPLETE, OJT_COMPLETE));
            foreach ($topiccompletions as $tc) {
                ojt_update_topic_competency_proficiency($tc->userid, $tc->topicid, $tc->status);
            }
        }
        $transaction->allow_commit();
    }


    redirect(new moodle_url('/mod/ojt/manage.php', array('cmid' => $cm->id)));
}

// Print the page header.
$actionstr = empty($topicid) ? get_string('addtopic', 'ojt') : get_string('edittopic', 'ojt');
$PAGE->set_title(format_string($ojt->name));
$PAGE->set_heading(format_string($ojt->name).' - '.$actionstr);

// Output starts here.
echo $OUTPUT->header();

// Replace the following lines with you own code.
echo $OUTPUT->heading($PAGE->heading);

if (!empty($topicid)) {
    $topic = $DB->get_record('ojt_topic', array('id' => $topicid), '*', MUST_EXIST);
    $topic->competencies = explode(',', $topic->competencies);
    $form->set_data($topic);
}

// Display
$form->display();

// Finish the page.
echo $OUTPUT->footer();
