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
 * Prints a particular instance of ojt for the current user.
 *
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/ojt/lib.php');
require_once($CFG->dirroot.'/mod/ojt/locallib.php');
require_once($CFG->dirroot .'/totara/core/js/lib/setup.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$b  = optional_param('n', 0, PARAM_INT);  // ojt instance ID.

if ($id) {
    $cm         = get_coursemodule_from_id('ojt', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $ojt  = $DB->get_record('ojt', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($b) {
    $ojt  = $DB->get_record('ojt', array('id' => $b), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $ojt->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('ojt', $ojt->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

$event = \mod_ojt\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $ojt);
$event->trigger();

// Print the page header.

$PAGE->set_url('/mod/ojt/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($ojt->name));
$PAGE->set_heading(format_string($course->fullname));

$jsmodule = array(
    'name' => 'mod_ojt_expandcollapse',
    'fullpath' => '/mod/ojt/expandcollapse.js',
    'requires' => array('json')
);
$PAGE->requires->js_init_call('M.mod_ojt_expandcollapse.init', array(), false, $jsmodule);

// Check access - we're assuming only $USER access on this page
$modcontext = context_module::instance($cm->id);
$canevaluate = has_capability('mod/ojt:evaluate', $modcontext);
$canevalself = has_capability('mod/ojt:evaluateself', $modcontext);
$cansignoff = has_capability('mod/ojt:signoff', $modcontext);

if ($canevalself && !($canevaluate || $cansignoff)) {
    // Seeing as the user can only self-evaluate, but nothing else, redirect them straight to the eval page
    redirect(new moodle_url($CFG->wwwroot.'/mod/ojt/evaluate.php',
        array('userid' => $USER->id, 'bid' => $ojt->id)));
}


// Output starts here.
echo $OUTPUT->header();

// "Evaluate students" button
if (($canevaluate || $cansignoff)) {
    echo html_writer::start_tag('div', array('class' => 'mod-ojt-evalstudents-btn'));
    echo $OUTPUT->single_button(new moodle_url('/mod/ojt/report.php', array('cmid' => $cm->id)),
        get_string('evaluatestudents', 'ojt'), 'get');
    echo html_writer::end_tag('div');
}

$userojt = ojt_get_user_ojt($ojt->id, $USER->id);

// "Evaluate self" button
if ($canevalself) {
    echo html_writer::start_tag('div', array('class' => 'mod-ojt-evalself-btn'));
    echo $OUTPUT->single_button(new moodle_url('/mod/ojt/evaluate.php', array('userid' => $USER->id, 'bid' => $userojt->id)),
        get_string('evaluate', 'ojt'), 'get');
    echo html_writer::end_tag('div');

}

// Replace the following lines with you own code.
echo $OUTPUT->heading(format_string($ojt->name));

// Conditions to show the intro can change to look for own settings or whatever.
if ($ojt->intro) {
    echo $OUTPUT->box(format_module_intro('ojt', $ojt, $cm->id), 'generalbox mod_introbox', 'ojtintro');
}

$renderer = $PAGE->get_renderer('ojt');
echo $renderer->user_ojt($userojt);

// Finish the page.
echo $OUTPUT->footer();
