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
 * Internal library of functions for module ojt
 *
 * All the ojt specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Build and return a list of topic item selections.
 *
 * These usually live in the 'other' field of the {ojt_topic_item} table.
 *
 * Since there's user input involved, input has whitespace trimmed and
 * format-filtered.
 *
 * @param string $selectionstring A newline-separated list of topic item choices.
 *
 * @return array A cleaned array of topic item selections.
 */
function ojt_get_selection_list($selectionstring) {
    // Split on new lines.
    $choices = array();
    $parts = preg_split('/\R/', trim($selectionstring));

    foreach ($parts as $part) {
        $choices[] = format_string(trim($part), FORMAT_MOODLE);
    }

    return $choices;
}

function ojt_get_user_ojt($ojtid, $userid) {
    global $DB;

    // Get the ojt details.
    $sql = 'SELECT '.$userid.' AS userid, b.*, CASE WHEN c.status IS NULL THEN '.OJT_INCOMPLETE.' ELSE c.status END AS status, c.comment
        FROM {ojt} b
        LEFT JOIN {ojt_completion} c ON b.id = c.ojtid AND c.type = ? AND c.userid = ?
        WHERE b.id = ?';
    $ojt = $DB->get_record_sql($sql, array(OJT_CTYPE_OJT, $userid, $ojtid), MUST_EXIST);

    // Add topics and completion data.
    $ojt->topics = ojt_get_user_topics($userid, $ojtid);
    foreach ($ojt->topics as $i => $topic) {
        $ojt->topics[$i]->items = array();
    }
    if (empty($ojt->topics)) {
        return $ojt;
    }

    // Add items and completion info.
    list($insql, $params) = $DB->get_in_or_equal(array_keys($ojt->topics));
    $sql = "SELECT i.*, CASE WHEN c.status IS NULL THEN ".OJT_INCOMPLETE." ELSE c.status END AS status,
            c.comment, c.timemodified, c.modifiedby,bw.witnessedby,bw.timewitnessed,".
            get_all_user_name_fields(true, 'moduser', '', 'modifier').",".
            get_all_user_name_fields(true, 'witnessuser', '', 'itemwitness')."
        FROM {ojt_topic_item} i
        LEFT JOIN {ojt_completion} c ON i.id = c.topicitemid AND c.type = ? AND c.userid = ?
        LEFT JOIN {user} moduser ON c.modifiedby = moduser.id
        LEFT JOIN {ojt_item_witness} bw ON bw.topicitemid = i.id AND bw.userid = ?
        LEFT JOIN {user} witnessuser ON bw.witnessedby = witnessuser.id
        WHERE i.topicid {$insql}
        ORDER BY i.topicid, i.position, i.id";
    $params = array_merge(array(OJT_CTYPE_TOPICITEM, $userid, $userid), $params);
    $items = $DB->get_records_sql($sql, $params);

    foreach ($items as $i => $item) {
        $ojt->topics[$item->topicid]->items[$i] = $item;
    }

    return $ojt;
}

function ojt_get_user_topics($userid, $ojtid) {
    global $DB;

    $sql = 'SELECT t.*, CASE WHEN c.status IS NULL THEN '.OJT_INCOMPLETE.' ELSE c.status END AS status,
        s.signedoff, s.modifiedby AS signoffmodifiedby, s.timemodified AS signofftimemodified,'.
        get_all_user_name_fields(true, 'su', '', 'signoffuser').'
        FROM {ojt_topic} t
        LEFT JOIN {ojt_completion} c ON t.id = c.topicid AND c.type = ? AND c.userid = ?
        LEFT JOIN {ojt_topic_signoff} s ON t.id = s.topicid AND s.userid = ?
        LEFT JOIN {user} su ON s.modifiedby = su.id
        WHERE t.ojtid = ?
        ORDER BY t.position, t.id';
    return $DB->get_records_sql($sql, array(OJT_CTYPE_TOPIC, $userid, $userid, $ojtid));
}

function ojt_update_topic_completion($userid, $ojtid, $topicid) {
    global $DB, $USER;

    $ojt = $DB->get_record('ojt', array('id' => $ojtid), '*', MUST_EXIST);

    // Check if all required topic items have been completed.
    $items = $DB->get_records_sql(
        'SELECT
            i.id,
            i.completionreq,
            s.signedoff,
            CASE WHEN c.status IS NULL THEN ? ELSE c.status END AS status
        FROM {ojt_topic_item} i
        LEFT JOIN {ojt_completion} c
            ON i.id = c.topicitemid
            AND c.ojtid = ?
            AND c.type = ?
            AND c.userid = ?
        LEFT JOIN {ojt_topic_signoff} s
            ON s.userid = ?
            AND s.topicid = ?
        WHERE i.topicid = ?',
        [
            OJT_INCOMPLETE,
            $ojtid,
            OJT_CTYPE_TOPICITEM,
            $userid,
            $userid,
            $topicid,
            $topicid
        ]
    );

    $status = OJT_COMPLETE;
    if (!empty($items)) {
        // Iterate through topic items, check conditions to determine if OJT is incomplete.
        foreach ($items as $item) {
            if ($item->status == OJT_INCOMPLETE) {
                if ($item->completionreq == OJT_REQUIRED) {
                    // All required items not complete - bail!
                    $status = OJT_INCOMPLETE;
                    break;
                } else if ($item->completionreq == OJT_OPTIONAL) {
                    // Degrade status a bit
                    $status = OJT_REQUIREDCOMPLETE;
                }
            } else if (!empty($ojt->managersignoff) && empty($item->signedoff)) {
                // Item requires manager sign-off and isn't signed off - also bail!
                $status = OJT_INCOMPLETE;
                break;
            }
        }
    } else {
        // If there are no items in the topic, check topic manager sign-off conditions.
        $topicsignedoff = $DB->get_field_sql(
            'SELECT s.signedoff
            FROM {ojt_topic} t
            LEFT JOIN {ojt_topic_signoff} s
                ON s.topicid = ?
                AND s.userid = ?',
            [$topicid, $userid],
            MUST_EXIST
        );
        if (!empty($ojt->managersignoff) && empty($topicsignedoff)) {
            $status = OJT_INCOMPLETE;
        }
    }

    if (in_array($status, array(OJT_COMPLETE, OJT_REQUIREDCOMPLETE))
            && $ojt->itemwitness && !ojt_topic_items_witnessed($topicid, $userid)) {

        // All required items must also be witnessed - degrade status
        $status = OJT_INCOMPLETE;
    }

    $currentcompletion = $DB->get_record('ojt_completion',
        array('userid' => $userid, 'topicid' => $topicid, 'type' => OJT_CTYPE_TOPIC));
    if (empty($currentcompletion->status) || $status != $currentcompletion->status) {
        // Update topic completion
        $transaction = $DB->start_delegated_transaction();

        $completion = empty($currentcompletion) ? new stdClass() : $currentcompletion;
        $completion->status = $status;
        $completion->timemodified = time();
        $completion->modifiedby = $USER->id;
        if (empty($currentcompletion)) {
            $completion->userid = $userid;
            $completion->type = OJT_CTYPE_TOPIC;
            $completion->ojtid = $ojtid;
            $completion->topicid = $topicid;
            $completion->id = $DB->insert_record('ojt_completion', $completion);
        } else {
            $DB->update_record('ojt_completion', $completion);
        }

        // Also update ojt completion.
        ojt_update_completion($userid, $ojtid);

        ojt_update_topic_competency_proficiency($userid, $topicid, $status);

        $transaction->allow_commit();
    }

    return empty($completion) ? $currentcompletion : $completion;
}

function ojt_update_topic_competency_proficiency($userid, $topicid, $status) {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/evidence/lib.php');

    if (!in_array($status, array(OJT_COMPLETE, OJT_REQUIREDCOMPLETE))) {
        return;
    }

    $competencies = $DB->get_field('ojt_topic', 'competencies', array('id' => $topicid));
    if (empty($competencies)) {
        // Nothing to do here :)
        return;
    }
    $competencies = explode(',', $competencies);

    foreach ($competencies as $competencyid) {
        // this is copied from totara/hierarchy/prefix/competency/evidence/lib.php - hierarchy_add_competency_evidence()
        $todb = new competency_evidence(
            array(
                'competencyid'  => $competencyid,
                'userid'        => $userid,
                'manual'        => 0,
                'reaggregate'   => 1,
                'assessmenttype' => 'ojt'
            )
        );

        if ($recordid = $DB->get_field('comp_record', 'id', array('userid' => $userid, 'competencyid' => $competencyid))) {
            $todb->id = $recordid;
        }

        // Get the first 'proficient' scale value for the competency
        $sql = "SELECT csv.id
            FROM {comp_scale} cs
            JOIN {comp_scale_values} csv ON cs.id = csv.scaleid
            JOIN {comp_scale_assignments} csa ON cs.id = csa.scaleid
            JOIN {comp} c ON csa.frameworkid = c.frameworkid
            WHERE c.id = ? AND csv.proficient = 1 ORDER BY csv.id LIMIT 1";
        $proficiencyid = $DB->get_field_sql($sql, array($competencyid), MUST_EXIST);

        // Update the user to 'proficient' for this competency
        $transaction = $DB->start_delegated_transaction();
        $todb->update_proficiency($proficiencyid);

        // Update stats block
        $currentuser = $userid;
        $event = STATS_EVENT_COMP_ACHIEVED;
        $data2 = $competencyid;
        $time = time();
        $count = $DB->count_records('block_totara_stats', array('userid' => $currentuser, 'eventtype' => $event, 'data2' => $data2));
        $isproficient = $DB->get_field('comp_scale_values', 'proficient', array('id' => $proficiencyid));

        // Check the proficiency is set to "proficient" and check for duplicate data.
        if ($isproficient && $count == 0) {
            totara_stats_add_event($time, $currentuser, $event, '', $data2);
        }
        $transaction->allow_commit();
    }
}

function ojt_update_completion($userid, $ojtid) {
    global $DB, $USER;

    // Check if all required ojt topics have been completed, then complete the ojt
    $topics = ojt_get_user_topics($userid, $ojtid);

    $status = OJT_COMPLETE;
    foreach ($topics as $topic) {
        if ($topic->status == OJT_INCOMPLETE) {
            if ($topic->completionreq == OJT_REQUIRED) {
                // All required topics not complete - bail!
                $status = OJT_INCOMPLETE;
                break;
            } else if ($topic->completionreq == OJT_OPTIONAL) {
                // Degrade status a bit
                $status = OJT_REQUIREDCOMPLETE;
            }
        } else if ($topic->status == OJT_REQUIREDCOMPLETE) {
            // Degrade status a bit
            $status = OJT_REQUIREDCOMPLETE;
        }
    }

    $transaction = $DB->start_delegated_transaction();
    $currentcompletion = $DB->get_record('ojt_completion',
        array('userid' => $userid, 'ojtid' => $ojtid, 'type' => OJT_CTYPE_OJT));
    if (empty($currentcompletion->status) || $status != $currentcompletion->status) {
        // Update ojt completion
        $completion = empty($currentcompletion) ? new stdClass() : $currentcompletion;
        $completion->status = $status;
        $completion->timemodified = time();
        $completion->modifiedby = $USER->id;
        if (empty($currentcompletion)) {
            $completion->userid = $userid;
            $completion->type = OJT_CTYPE_OJT;
            $completion->ojtid = $ojtid;
            $completion->id = $DB->insert_record('ojt_completion', $completion);
        } else {
            $DB->update_record('ojt_completion', $completion);
        }

        // Update activity completion state
        ojt_update_activity_completion($ojtid, $userid, $status);
    }
    $transaction->allow_commit();

    return empty($completion) ? $currentcompletion : $completion;
}


function ojt_update_activity_completion($ojtid, $userid, $ojtstatus) {
    global $DB;

    $ojt = $DB->get_record('ojt', array('id' => $ojtid), '*', MUST_EXIST);
    if ($ojt->completiontopics) {
        $course = $DB->get_record('course', array('id' => $ojt->course), '*', MUST_EXIST);

        $cm = get_coursemodule_from_instance('ojt', $ojt->id, $ojt->course, false, MUST_EXIST);
        $ccompletion = new completion_info($course);
        if ($ccompletion->is_enabled($cm)) {
            if (in_array($ojtstatus, array(OJT_COMPLETE, OJT_REQUIREDCOMPLETE))) {
                $ccompletion->update_state($cm, COMPLETION_COMPLETE, $userid);
            } else {
                $ccompletion->update_state($cm, COMPLETION_INCOMPLETE, $userid);
            }
        }
    }
}

/**
 * Check if all the required items in a topic have been witnessed
 */
function ojt_topic_items_witnessed($topicid, $userid) {
    global $DB;

    $sql = "SELECT ti.id
        FROM {ojt_topic_item} ti
        LEFT JOIN {ojt_item_witness} iw ON ti.id = iw.topicitemid AND iw.witnessedby != 0 AND iw.userid = ?
        WHERE ti.completionreq = ? AND ti.topicid = ? AND iw.witnessedby IS NULL";

    return !$DB->record_exists_sql($sql, array($userid, OJT_REQUIRED, $topicid));
}

function ojt_get_modifiedstr($timemodified, $user=null) {
    global $USER;

    if (empty($user)) {
        $user = $USER;
    }

    if (empty($timemodified)) {
        return '';
    }

    return 'by '.fullname($user).' on '.userdate($timemodified, get_string('strftimedatetimeshort', 'core_langconfig'));
}

function ojt_delete_topic($topicid) {
    global $DB;

    $transaction = $DB->start_delegated_transaction();

    $DB->delete_records('ojt_topic', array('id' => $topicid));
    $DB->delete_records('ojt_topic_item', array('topicid' => $topicid));
    $DB->delete_records('ojt_completion', array('topicid' => $topicid));
    $DB->delete_records('ojt_topic_signoff', array('topicid' => $topicid));

    $transaction->allow_commit();
}

function ojt_delete_topic_item($itemid, $context) {
    global $DB;

    $transaction = $DB->start_delegated_transaction();

    $DB->delete_records('ojt_topic_item', array('id' => $itemid));
    $DB->delete_records('ojt_completion', array('topicitemid' => $itemid));
    $DB->delete_records('ojt_item_witness', array('topicitemid' => $itemid));

    // Delete item files
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_ojt', 'topicitemfiles'.$itemid);

    $transaction->allow_commit();
}

function ojt_can_evaluate($userid, $context) {
    global $USER;

    if (!has_capability('mod/ojt:evaluate', $context) && !(has_capability('mod/ojt:evaluateself', $context) && $USER->id == $userid)) {
        return false;
    }

    return true;
}

/**
 * Reorder topics and topic items in edit mode.
 *
 * @param $items
 * @param $oldpos
 * @param $newpos
 * @return false
 */
function ojt_array_move(&$items, $oldpos, $newpos) {
    if ($oldpos == $newpos) {
        return false;
    }
    array_splice(
        $items,
        max($newpos, 0),
        0,
        array_splice(
            $items,
            max($oldpos, 0),
            1)
    );
}

/**
 * Ensure topic positions are ordered as expected.
 *
 * Positions start with a default value of '0', so implicit ordering from topic ID is used.
 *
 * @param object $ojt An OJT record.
 * @param bool $includeitems Reorder topic items as well?
 */
function ojt_reorder_topics($ojt, $includeitems = true) {
    global $DB;

    $topicrs = $DB->get_recordset_sql(
        "SELECT * FROM {ojt_topic} WHERE ojtid = :ojtid ORDER BY position, id",
        array('ojtid' => $ojt->id)
    );

    $topiccounter = 0;
    foreach ($topicrs as $topic) {
        if ($topiccounter != $topic->position) {
            // Update OJT topic position.
            $topic->position = $topiccounter;
            $DB->update_record('ojt_topic', $topic);
        }
        if ($includeitems) {
            ojt_reorder_topic_items($topic);
        }

        $topiccounter++;
    }
}

/**
 * Ensure topic item positions are ordered as expected.
 *
 * Positions start with a default value of '0', so implicit ordering from topic item ID is used.
 *
 * @param object $topic An OJT topic record.
 */
function ojt_reorder_topic_items($topic) {
    global $DB;

    $itemrs = $DB->get_recordset_sql(
        "SELECT * FROM {ojt_topic_item} WHERE topicid = :topicid ORDER BY position, id",
        array('topicid' => $topic->id)
    );

    $itemcounter = 0;
    foreach ($itemrs as $item) {
        if ($itemcounter != $item->position) {
            // Update OJT topic item position.
            $item->position = $itemcounter;
            $DB->update_record('ojt_topic_item', $item);
        }
        $itemcounter++;
    }
}
