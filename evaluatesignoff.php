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
 * OJT item completion ajax toggler
 */

define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/ojt/lib.php');
require_once($CFG->dirroot.'/mod/ojt/locallib.php');
require_once($CFG->dirroot .'/totara/core/js/lib/setup.php');

require_sesskey();

$userid = required_param('userid', PARAM_INT);
$ojtid  = required_param('bid', PARAM_INT);
$topicid = required_param('id', PARAM_INT);

$user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
$ojt  = $DB->get_record('ojt', array('id' => $ojtid), '*', MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $ojt->course), '*', MUST_EXIST);
$cm         = get_coursemodule_from_instance('ojt', $ojt->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);

require_capability('mod/ojt:signoff', context_module::instance($cm->id));

if (!$ojt->managersignoff) {
    print_error('manager signoff not enabled for this ojt');
}

// Get the ojt topic
$sql = "SELECT t.*, t.id AS topicid
    FROM {ojt_topic} t
    WHERE t.ojtid = ? AND t.id = ?";
$topic = $DB->get_record_sql($sql, array($ojt->id, $topicid), MUST_EXIST);

// Update/delete the signoff record
$topicsignoff = new stdClass();
$topicsignoff->userid = $userid;
$topicsignoff->topicid = $topic->id;
$topicsignoff->timemodified = time();
$topicsignoff->modifiedby = $USER->id;

if ($currentsignoff = $DB->get_record('ojt_topic_signoff', array('userid' => $userid, 'topicid' => $topicid))) {
    // Update
    $topicsignoff->id = $currentsignoff->id;
    $topicsignoff->signedoff = !($currentsignoff->signedoff);
    $DB->update_record('ojt_topic_signoff', $topicsignoff);
} else {
    // Insert
    $topicsignoff->signedoff = 1;
    $topicsignoff->id = $DB->insert_record('ojt_topic_signoff', $topicsignoff);
}

$modifiedstr = ojt_get_modifiedstr($topicsignoff->timemodified);
$topiccompletion = ojt_update_topic_completion($userid, $ojtid, $topicid);

$jsonparams = array(
    'topicsignoff' => $topicsignoff,
    'modifiedstr' => $modifiedstr,
    'topic' => $topiccompletion
);

echo json_encode($jsonparams);
