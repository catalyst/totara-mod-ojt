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
 * OJT witness ajax toggler
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/ojt/lib.php');
require_once($CFG->dirroot.'/mod/ojt/locallib.php');
require_once($CFG->dirroot .'/totara/core/js/lib/setup.php');

$userid = required_param('userid', PARAM_INT);
$ojtid  = required_param('bid', PARAM_INT);
$itemid = required_param('id', PARAM_INT);

$user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
$ojt  = $DB->get_record('ojt', array('id' => $ojtid), '*', MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $ojt->course), '*', MUST_EXIST);
$cm         = get_coursemodule_from_instance('ojt', $ojt->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);

require_capability('mod/ojt:witnessitem', context_module::instance($cm->id));

if (!$ojt->itemwitness) {
    print_error('itemwitness disabled for this ojt');
}

// Get the ojt item, joining on topic to ensure the item does belong to the ojt
$sql = "SELECT i.*, t.id AS topicid
    FROM {ojt_topic_item} i
    JOIN {ojt_topic} t ON i.topicid = t.id
    WHERE t.ojtid = ? AND i.id = ?";
$item = $DB->get_record_sql($sql, array($ojt->id, $itemid), MUST_EXIST);

$dateformat = get_string('strftimedatetimeshort', 'core_langconfig');

// Update/insert the user completion record
$transaction = $DB->start_delegated_transaction();
$params = array(
    'userid' => $userid,
    'topicitemid' => $itemid
);
if ($witness = $DB->get_record('ojt_item_witness', $params)) {
    // Update
    $removewitness = !empty($witness->witnessedby);
    $witness->witnessedby = $removewitness ? 0 : $USER->id;
    $witness->timewitnessed = $removewitness ? 0 : time();
    $DB->update_record('ojt_item_witness', $witness);
} else {
    // Insert
    $witness = (object)$params;
    $witness->witnessedby = $USER->id;
    $witness->timewitnessed = time();
    $witness->id = $DB->insert_record('ojt_item_witness', $witness);
}

// Update topic completion
$topiccompletion = ojt_update_topic_completion($userid, $ojt->id, $item->topicid);

$transaction->allow_commit();

$modifiedstr = ojt_get_modifiedstr($witness->timewitnessed);

$jsonparams = array(
    'item' => $witness,
    'modifiedstr' => $modifiedstr,
    'topic' => $topiccompletion
);

echo json_encode($jsonparams);
