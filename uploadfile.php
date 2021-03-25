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
 * Upload a file to a ojt topic item
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
require_once($CFG->dirroot . '/mod/ojt/locallib.php');
require_once('uploadfile_form.php');
require_once('lib.php');

require_login();

$userid = required_param('userid', PARAM_INT);
$topicitemid = required_param('tiid', PARAM_INT);

$sql = "SELECT b.*, ti.allowfileuploads, ti.allowselffileuploads
    FROM {ojt_topic_item} ti
    JOIN {ojt_topic} t ON ti.topicid = t.id
    JOIN {ojt} b ON t.ojtid = b.id
    WHERE ti.id = ?";
if (!$ojt = $DB->get_record_sql($sql, array($topicitemid))) {
    print_error('ojt not found');
}
$course = $DB->get_record('course', array('id' => $ojt->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('ojt', $ojt->id, $course->id, false, MUST_EXIST);
$modcontext = context_module::instance($cm->id);

// Check access
if (!($ojt->allowfileuploads || $ojt->allowselffileuploads)) {
    print_error('files cannot be uploaded for this topic item');
}
// Only users with evaluate perm or evaluateself that's also the ojt user should be able to upload a file (if config allows)
// Also allow ojt owners to upload files, if configured
$canevaluate = ojt_can_evaluate($userid, $modcontext);
$canselfupload = $ojt->allowselffileuploads && $userid == $USER->id;
if (!($canevaluate || $canselfupload)) {
    print_error('access denied');
}

require_login($course, true, $cm);

if ($canevaluate) {
    $returnurl = new moodle_url('/mod/ojt/evaluate.php', array('userid' => $userid, 'bid' => $ojt->id));
} else {
    $returnurl = new moodle_url('/mod/ojt/view.php', array('id' => $cm->id));
}

$PAGE->set_pagelayout('noblocks');
$PAGE->set_url('/mod/ojt/uploadfile.php', array('tiid' => $topicitemid, 'userid' => $userid));

if (!$user = $DB->get_record('user', array('id' => $userid))) {
    print_error('user not found');
}

$fileoptions = $FILEPICKER_OPTIONS;
$fileoptions['maxfiles'] = 10;

$item = new stdClass();
$item->topicitemid = $topicitemid;
$item->userid = $userid;
$item = file_prepare_standard_filemanager($item, 'topicitemfiles',
        $fileoptions, $modcontext, 'mod_ojt', "topicitemfiles{$topicitemid}", $userid);

$mform = new ojt_topicitem_files_form(
    null,
    array(
        'topicitemid' => $topicitemid,
        'userid' => $userid,
        'fileoptions' => $fileoptions
    )
);
$mform->set_data($item);

if ($data = $mform->get_data()) {
    // process files, update the data record
    $data = file_postupdate_standard_filemanager($data, 'topicitemfiles',
            $fileoptions, $modcontext, 'mod_ojt', "topicitemfiles{$topicitemid}", $userid);

    \core\notification::success(get_string('filesupdated', 'ojt'));
} else if ($mform->is_cancelled()) {
    redirect($returnurl);
}

$strheading = get_string('updatefiles', 'ojt');
$PAGE->navbar->add(get_string('evaluate', 'ojt'));
$PAGE->navbar->add(fullname($user), new moodle_url('/mod/ojt/evaluate.php', array('userid' => $userid, 'bid' => $ojt->id)));
$PAGE->navbar->add(get_string('updatefiles', 'ojt'));
$PAGE->set_title($strheading);
$PAGE->set_heading($strheading);

echo $OUTPUT->header();

echo $OUTPUT->heading($strheading, 1);

$mform->display();

echo $OUTPUT->footer();
