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

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/ojt/lib.php');
require_once($CFG->dirroot.'/mod/ojt/locallib.php');
require_once($CFG->dirroot .'/totara/core/js/lib/setup.php');

$userid = required_param('userid', PARAM_INT);
$ojtid  = required_param('bid', PARAM_INT);
$itemid = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_TEXT);

$user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
$ojt  = $DB->get_record('ojt', array('id' => $ojtid), '*', MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $ojt->course), '*', MUST_EXIST);
$cm         = get_coursemodule_from_instance('ojt', $ojt->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);

if (!ojt_can_evaluate($userid, context_module::instance($cm->id))) {
    print_error('access denied');
}

// Get the ojt item, joining on topic to ensure the item does belong to the ojt
$sql = "SELECT i.*, t.id AS topicid
    FROM {ojt_topic_item} i
    JOIN {ojt_topic} t ON i.topicid = t.id
    WHERE t.ojtid = ? AND i.id = ?";
$item = $DB->get_record_sql($sql, array($ojt->id, $itemid), MUST_EXIST);

$dateformat = get_string('strftimedatetimeshort', 'core_langconfig');

// Update/insert the user completion record
$params = array('userid' => $userid,
    'ojtid' => $ojtid,
    'topicid' => $item->topicid,
    'topicitemid' => $itemid,
    'type' => OJT_CTYPE_TOPICITEM);
if ($completion = $DB->get_record('ojt_completion', $params)) {
    // Update
    switch ($action) {
        case 'togglecompletion':
            $completion->status = $completion->status == OJT_COMPLETE ? OJT_INCOMPLETE : OJT_COMPLETE;
            break;
        case 'savecomment':
            $completion->comment = required_param('comment', PARAM_TEXT);
            // append a date to the comment string
            $completion->comment .= ' - '.userdate(time(), $dateformat).'.';
            break;
        default:
    }
    $completion->timemodified = time();
    $completion->modifiedby = $USER->id;
    $DB->update_record('ojt_completion', $completion);
} else {
    // Insert
    $completion = (object)$params;
    switch ($action) {
        case 'togglecompletion':
            $completion->status = OJT_COMPLETE;
            break;
        case 'savecomment':
            $completion->comment = required_param('comment', PARAM_TEXT);
            // append a date to the comment string
            $completion->comment .= ' - '.userdate(time(), $dateformat).'.';
            break;
        default:
    }
    $completion->timemodified = time();
    $completion->modifiedby = $USER->id;
    $completion->id = $DB->insert_record('ojt_completion', $completion);
}

$modifiedstr = ojt_get_modifiedstr($completion->timemodified);

$jsonparams = array(
    'item' => $completion,
    'modifiedstr' => $modifiedstr
);
if ($action == 'togglecompletion') {
    $topiccompletion = ojt_update_topic_completion($userid, $ojtid, $item->topicid);
    $jsonparams['topic'] = $topiccompletion;
}

echo json_encode($jsonparams);
