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
 * Library of interface functions and constants for module ojt
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the ojt specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 */

defined('MOODLE_INTERNAL') || die();

/**
 * OJT completion types
 */
define('OJT_CTYPE_OJT', 0);
define('OJT_CTYPE_TOPIC', 1);
define('OJT_CTYPE_TOPICITEM', 2);

/**
 * OJT completion statuses
 */
define('OJT_INCOMPLETE', 0);
define('OJT_REQUIREDCOMPLETE', 1);
define('OJT_COMPLETE', 2);

/**
 * OJT completion requirements
 */
define('OJT_REQUIRED', 0);
define('OJT_OPTIONAL', 1);

/* Moodle core API */

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function ojt_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the ojt into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $ojt Submitted data from the form in mod_form.php
 * @param mod_ojt_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted ojt record
 */
function ojt_add_instance(stdClass $ojt, mod_ojt_mod_form $mform = null) {
    global $DB;

    $ojt->timecreated = time();

    // You may have to add extra stuff in here.

    $ojt->id = $DB->insert_record('ojt', $ojt);

    return $ojt->id;
}

/**
 * Updates an instance of the ojt in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $ojt An object from the form in mod_form.php
 * @param mod_ojt_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function ojt_update_instance(stdClass $ojt, mod_ojt_mod_form $mform = null) {
    global $DB;

    $ojt->timemodified = time();
    $ojt->id = $ojt->instance;

    // You may have to add extra stuff in here.

    $result = $DB->update_record('ojt', $ojt);

    return $result;
}

/**
 * Removes an instance of the ojt from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function ojt_delete_instance($id) {
    global $DB;

    if (!$ojt = $DB->get_record('ojt', array('id' => $id))) {
        return false;
    }

    $transaction = $DB->start_delegated_transaction();

    // Delete witnesses
    $DB->delete_records_select('ojt_item_witness', 'topicitemid IN (SELECT ti.id FROM {ojt_topic_item} ti JOIN {ojt_topic} t ON ti.topicid = t.id WHERE t.ojtid = ?)', array($ojt->id));

    // Delete signoffs
    $DB->delete_records_select('ojt_topic_signoff', 'topicid IN (SELECT id FROM {ojt_topic} WHERE ojtid = ?)', array($ojt->id));

    // Delete completions
    $DB->delete_records('ojt_completion', array('ojtid' => $ojt->id));

    // Delete comments
    $topics = $DB->get_records('ojt_topic', array('ojtid' => $ojt->id));
    foreach ($topics as $topic) {
        $DB->delete_records('comments', array('commentarea' => 'ojt_topic_item_'.$topic->id));
    }

    // Delete topic items
    $DB->delete_records_select('ojt_topic_item', 'topicid IN (SELECT id FROM {ojt_topic} WHERE ojtid = ?)', array($ojt->id));

    // Delete topics
    $DB->delete_records('ojt_topic', array('ojtid' => $ojt->id));

    // Finally, delete the ojt ;)
    $DB->delete_records('ojt', array('id' => $ojt->id));

    $transaction->allow_commit();

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record
 * @param stdClass $user The user record
 * @param cm_info|stdClass $mod The course module info object or record
 * @param stdClass $ojt The ojt instance record
 * @return stdClass|null
 */
function ojt_user_outline($course, $user, $mod, $ojt) {

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * It is supposed to echo directly without returning a value.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $ojt the module instance record
 */
function ojt_user_complete($course, $user, $mod, $ojt) {
}

/**
 * Obtains the specific requirements for completion.
 *
 * @param object $cm Course-module
 * @return array Requirements for completion
 */
function ojt_get_completion_requirements($cm) {
    global $DB;

    $ojt = $DB->get_record('ojt', array('id' => $cm->instance));

    $result = array();

    if ($ojt->completiontopics) {
        $result[] = get_string('completiontopics', 'ojt');
    }

    return $result;
}

/**
 * Obtains the completion progress.
 *
 * @param object $cm      Course-module
 * @param int    $userid  User ID
 * @return string The current status of completion for the user
 */
function ojt_get_completion_progress($cm, $userid) {
    global $DB;

    // Get ojt details.
    $ojt = $DB->get_record('ojt', array('id' => $cm->instance), '*', MUST_EXIST);

    $result = array();

    if ($ojt->completiontopics) {
        $ojtcomplete = $DB->record_exists_select('ojt_completion',
            'ojtid = ? AND userid =? AND type = ? AND status IN (?, ?)',
            array($ojt->id, $userid, OJT_CTYPE_OJT, OJT_COMPLETE, OJT_REQUIREDCOMPLETE));
        if ($ojtcomplete) {
            $result[] = get_string('completiontopics', 'ojt');
        }
    }

    return $result;
}


/**
 * Obtains the automatic completion state for this ojt activity based on any conditions
 * in ojt settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function ojt_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    // Get ojt.
    $ojt = $DB->get_record('ojt', array('id' => $cm->instance), '*', MUST_EXIST);

    // This means that if only view is required we don't end up with a false state.
    if (empty($ojt->completiontopics)) {
        return $type;
    }

    return $DB->record_exists_select('ojt_completion',
        'ojtid = ? AND userid =? AND type = ? AND status IN (?, ?)',
        array($ojt->id, $userid, OJT_CTYPE_OJT, OJT_COMPLETE, OJT_REQUIREDCOMPLETE));

}


/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in ojt activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function ojt_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link ojt_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 */
function ojt_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@link ojt_get_recent_mod_activity()}
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function ojt_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 *
 * @return boolean
 */
function ojt_cron () {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/totara/message/messagelib.php');

    $lastcron = $DB->get_field('modules', 'lastcron', array('name' => 'ojt'));

    // Send topic completion task to managers
    // Get all topic completions that happended after last cron run.
    // We can safely use the timemodified field here, as topics don't have comments ;)
    $sql = "SELECT bc.id AS completionid, u.id AS userid, u.*,
        b.id AS ojtid, b.name AS ojtname,
        t.id AS topicid, t.name AS topicname,
        c.shortname AS courseshortname
        FROM {ojt_completion} bc
        JOIN {ojt} b ON bc.ojtid = b.id
        JOIN {course} c ON b.course = c.id
        JOIN {ojt_topic} t ON bc.topicid = t.id
        JOIN {user} u ON bc.userid = u.id
        WHERE bc.type = ? AND bc.status = ? AND bc.timemodified > ?
        AND b.id IN (SELECT id FROM {ojt} WHERE managersignoff = 1)";
    $tcompletions = $DB->get_records_sql($sql, array(OJT_CTYPE_TOPIC, OJT_COMPLETE, $lastcron));
    foreach ($tcompletions as $completion) {
        if ($manager = totara_get_manager($completion->userid)) {
            $eventdata = new stdClass();
            $eventdata->userto = $manager;
            $eventdata->userfrom = $completion;
            $eventdata->icon = 'elearning-complete';
            $eventdata->contexturl = new moodle_url('/mod/ojt/evaluate.php',
                array('userid' => $completion->userid, 'bid' => $completion->ojtid));
            $eventdata->contexturl = $eventdata->contexturl->out();
            $strobj = new stdClass();
            $strobj->user = fullname($completion);
            $strobj->ojt = format_string($completion->ojtname);
            $strobj->topic = format_string($completion->topicname);
            $strobj->topicurl = $eventdata->contexturl;
            $strobj->courseshortname = format_string($completion->courseshortname);
            $eventdata->subject = get_string('managertasktcompletionsubject', 'ojt', $strobj);
            $eventdata->fullmessage = get_string('managertasktcompletionmsg', 'ojt', $strobj);
            // $eventdata->sendemail = TOTARA_MSG_EMAIL_NO;

            tm_task_send($eventdata);
        }
    }

    return true;
}

/**
 * Returns all other caps used in the module
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function ojt_get_extra_capabilities() {
    return array(
        'mod/ojt:evaluate',
        'mod/ojt:signoff',
        'mod/ojt:manage'
    );
}


/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function ojt_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for ojt file areas
 *
 * @package mod_ojt
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function ojt_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the ojt file areas
 *
 * @package mod_ojt
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the ojt's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function ojt_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    $userid = $args[0];
    require_once($CFG->dirroot.'/mod/ojt/locallib.php');
    if (!(ojt_can_evaluate($userid, $context) || $userid == $USER->id)) {
        // Only evaluators and/or owners have access to files
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_ojt/$filearea/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) || $file->is_directory()) {
        send_file_not_found();
    }

    // finally send the file
    send_stored_file($file, null, 0, $forcedownload, $options);
}

/* Navigation API */

/**
 * Extends the global navigation tree by adding ojt nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the ojt module instance
 * @param stdClass $course current course record
 * @param stdClass $module current ojt instance record
 * @param cm_info $cm course module information
 */
function ojt_extend_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $cm) {
    $context = context_module::instance($cm->id);
    if (has_capability('mod/ojt:evaluate', $context) || has_capability('mod/ojt:signoff', $context)) {
        $link = new moodle_url('/mod/ojt/report.php', array('cmid' => $cm->id));
        $node = $navref->add(get_string('evaluatestudents', 'ojt'), $link, navigation_node::TYPE_SETTING);
    }

}

/**
 * Extends the settings navigation with the ojt settings
 *
 * This function is called when the context for the page is a ojt module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav complete settings navigation tree
 * @param navigation_node $ojtnode ojt administration node
 */
function ojt_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $ojtnode=null) {
    global $PAGE;

    if (has_capability('mod/ojt:evaluate', $PAGE->cm->context) || has_capability('mod/ojt:signoff', $PAGE->cm->context)) {
        $link = new moodle_url('/mod/ojt/report.php', array('cmid' => $PAGE->cm->id));
        $node = navigation_node::create(get_string('evaluatestudents', 'ojt'),
                new moodle_url('/mod/ojt/report.php', array('cmid' => $PAGE->cm->id)),
                navigation_node::TYPE_SETTING, null, 'mod_ojt_evaluate',
                new pix_icon('i/valid', ''));
        $ojtnode->add_node($node);
    }

    if (has_capability('mod/ojt:manage', $PAGE->cm->context)) {
        $node = navigation_node::create(get_string('edittopics', 'ojt'),
                new moodle_url('/mod/ojt/manage.php', array('cmid' => $PAGE->cm->id)),
                navigation_node::TYPE_SETTING, null, 'mod_ojt_manage',
                new pix_icon('t/edit', ''));
        $ojtnode->add_node($node);
    }
}


/**
 * Comments helper functions and callbacks
 *
 */

/**
 * Validate comment parameters, before other comment actions are performed
 *
 * @package  block_comments
 * @category comment
 *
 * @param stdClass $comment_param {
 *              context  => context the context object
 *              courseid => int course id
 *              cm       => stdClass course module object
 *              commentarea => string comment area
 *              itemid      => int itemid
 * }
 * @return boolean
 */
function ojt_comment_validate($comment_param) {
    if (!strstr($comment_param->commentarea, 'ojt_topic_item_')) {
        throw new comment_exception('invalidcommentarea');
    }
    if (empty($comment_param->itemid)) {
        throw new comment_exception('invalidcommentitemid');
    }

    return true;
}

/**
 * Running addtional permission check on plugins
 *
 * @package  block_comments
 * @category comment
 *
 * @param stdClass $args
 * @return array
 */
function ojt_comment_permissions($args) {
    global $CFG;
    require_once($CFG->dirroot.'/mod/ojt/locallib.php');

    if (!ojt_can_evaluate($args->itemid, $args->context)) {
        return array('post'=>false, 'view'=>true);
    }

    return array('post'=>true, 'view'=>true);
}

function ojt_comment_template() {
    global $OUTPUT, $PAGE;

    // Use the totara default comment template
    $renderer = $PAGE->get_renderer('totara_core');

    return $renderer->comment_template();
}

