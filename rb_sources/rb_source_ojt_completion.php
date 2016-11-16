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

defined('MOODLE_INTERNAL') || die();

class rb_source_ojt_completion extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    function __construct() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/ojt/lib.php');

        $this->base = "(
            SELECT ".$DB->sql_concat('ub.courseid', "'-'", 'ub.userid', "'-'", 'ub.ojtid', "'-'", 'ub.topicid', "'-'", 'ub.type')." AS id,
            ub.courseid, ub.userid, ub.ojtid, ub.topicid, ub.type, bc.status, bc.timemodified, bc.modifiedby
            FROM (
                (SELECT ue.courseid, ue.userid, b.id AS ojtid, 0 AS topicid,".OJT_CTYPE_OJT." AS type
                FROM
                    (SELECT distinct courseid, userid
                    FROM {enrol} e
                    JOIN {user_enrolments} ue ON e.id = ue.enrolid) ue
                JOIN {ojt} b ON ue.courseid = b.course)
                UNION
                (SELECT ue.courseid, ue.userid, b.id AS ojtid, t.id AS topicid,".OJT_CTYPE_TOPIC." AS type
                FROM
                    (SELECT DISTINCT courseid, userid
                    FROM {enrol} e
                    JOIN {user_enrolments} ue ON e.id = ue.enrolid) ue
                JOIN {ojt} b ON ue.courseid = b.course
                JOIN {ojt_topic} t ON b.id = t.ojtid)
            ) AS ub
            LEFT JOIN {ojt_completion} bc
                ON bc.userid = ub.userid
                AND bc.ojtid = ub.ojtid
                AND bc.topicid = ub.topicid
                AND bc.type = ub.type
            ORDER BY courseid, userid, ojtid, topicid
        )";

        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('ojtcompletion', 'rb_source_ojt_completion');

        parent::__construct();
    }

    /**
     * Global report restrictions are not yet implemented for this source.
     * @return boolean
     */
    public function global_restrictions_supported() {
        return false;
    }


    //
    //
    // Methods for defining contents of source
    //
    //

    protected function define_joinlist() {
        global $CFG;

        // to get access to constants
        require_once($CFG->dirroot.'/mod/ojt/lib.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');

        $joinlist = array(
            new rb_join(
                'ojt',
                'LEFT',
                '{ojt}',
                'base.ojtid = ojt.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'ojt_topic',
                'LEFT',
                '{ojt_topic}',
                'base.topicid = ojt_topic.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'ojt_topic_signoff',
                'LEFT',
                '{ojt_topic_signoff}',
                'base.topicid = ojt_topic_signoff.topicid
                    AND base.userid = ojt_topic_signoff.userid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'modifyuser',
                'LEFT',
                '{user}',
                'base.modifiedby = modifyuser.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'topicsignoffuser',
                'LEFT',
                '{user}',
                'ojt_topic_signoff.modifiedby = topicsignoffuser.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'ojt_topic_signoff'
            ),
        );

        // include some standard joins
        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_course_table_to_joinlist($joinlist, 'base', 'courseid');
        // requires the course join
        $this->add_course_category_table_to_joinlist($joinlist,
            'course', 'category');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        // requires the position_assignment join
        $this->add_manager_tables_to_joinlist($joinlist,
            'position_assignment', 'reportstoid');
        $this->add_tag_tables_to_joinlist('course', $joinlist, 'base', 'courseid');
        $this->add_cohort_user_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_cohort_course_tables_to_joinlist($joinlist, 'base', 'courseid');

        return $joinlist;
    }

    protected function define_columnoptions() {
        global $DB;

        $columnoptions = array(
            new rb_column_option(
                'ojt',
                'name',
                get_string('ojt', 'rb_source_ojt_completion'),
                'ojt.name',
                array('joins' => 'ojt', 'displayfunc' => 'ojt_link',
                    'extrafields' => array('userid' => 'base.userid', 'ojtid' => 'base.ojtid'))
            ),
            new rb_column_option(
                'ojt',
                'evaluatelink',
                get_string('evaluatelink', 'rb_source_ojt_completion'),
                'ojt.name',
                array('joins' => 'ojt', 'displayfunc' => 'ojt_evaluate_link',
                    'extrafields' => array('userid' => 'base.userid', 'ojtid' => 'base.ojtid'))
            ),

            new rb_column_option(
                'ojt_topic',
                'name',
                get_string('topic', 'rb_source_ojt_completion'),
                'ojt_topic.name',
                array('joins' => 'ojt_topic')
            ),
            new rb_column_option(
                'ojt_topic_signoff',
                'signedoff',
                get_string('topicsignedoff', 'rb_source_ojt_completion'),
                'ojt_topic_signoff.signedoff',
                array('joins' => 'ojt_topic_signoff', 'displayfunc' => 'ojt_topic_signedoff')
            ),
            new rb_column_option(
                'ojt_topic_signoff',
                'timemodified',
                get_string('topicsignedofftime', 'rb_source_ojt_completion'),
                'ojt_topic_signoff.timemodified',
                array('joins' => 'ojt_topic_signoff', 'displayfunc' => 'nice_datetime')
            ),
            new rb_column_option(
                'ojt_topic_signoff',
                'topicsignoffuser',
                get_string('topicsignoffuser', 'rb_source_ojt_completion'),
                $DB->sql_fullname("topicsignoffuser.firstname", "topicsignoffuser.lastname"),
                array(
                    'joins' => 'topicsignoffuser',
                    'displayfunc' => 'link_user',
                    'extrafields' => array('user_id' => "topicsignoffuser.id"),
                )

            ),
            new rb_column_option(
                'base',
                'status',
                get_string('completionstatus', 'rb_source_ojt_completion'),
                'base.status',
                array('displayfunc' => 'ojt_completion_status')
            ),
            new rb_column_option(
                'base',
                'type',
                get_string('type', 'rb_source_ojt_completion'),
                'base.type',
                array('displayfunc' => 'ojt_type')
            ),
            new rb_column_option(
                'base',
                'timemodified',
                get_string('timemodified', 'rb_source_ojt_completion'),
                'base.timemodified',
                array('displayfunc' => 'nice_datetime')
            ),
            new rb_column_option(
                'base',
                'modifiedby',
                get_string('modifiedby', 'rb_source_ojt_completion'),
                $DB->sql_fullname("modifyuser.firstname", "modifyuser.lastname"),
                array(
                    'joins' => 'modifyuser',
                    'displayfunc' => 'link_user',
                    'extrafields' => array('user_id' => "modifyuser.id"),
                )
            ),
        );

        // include some standard columns
        $this->add_user_fields_to_columns($columnoptions);
        $this->add_course_fields_to_columns($columnoptions);
        $this->add_course_category_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);
        $this->add_tag_fields_to_columns('course', $columnoptions);
        $this->add_cohort_user_fields_to_columns($columnoptions);
        $this->add_cohort_course_fields_to_columns($columnoptions);

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array(

            new rb_filter_option(
                'ojt',
                'name',
                get_string('ojtname', 'rb_source_ojt_completion'),
                'text'
            ),
            new rb_filter_option(
                'ojt_topic',
                'name',
                get_string('topicname', 'rb_source_ojt_completion'),
                'text'
            ),
            new rb_filter_option(
                'base',
                'timemodified',
                get_string('timemodified', 'rb_source_ojt_completion'),
                'date'
            ),
            new rb_filter_option(
                'base',
                'status',
                get_string('completionstatus', 'rb_source_ojt_completion'),
                'select',
                array(
                    'selectfunc' => 'ojt_completion_status_list',
                )
            ),
            new rb_filter_option(
                'base',
                'type',
                get_string('type', 'rb_source_ojt_completion'),
                'select',
                array(
                    'selectfunc' => 'ojt_type_list',
                )
            ),

        );

        // include some standard filters
        $this->add_user_fields_to_filters($filteroptions);
        $this->add_course_fields_to_filters($filteroptions);
        $this->add_course_category_fields_to_filters($filteroptions);
        $this->add_position_fields_to_filters($filteroptions);
        $this->add_manager_fields_to_filters($filteroptions);
        $this->add_tag_fields_to_filters('course', $filteroptions);
        $this->add_cohort_user_fields_to_filters($filteroptions);
        $this->add_cohort_course_fields_to_filters($filteroptions);

        return $filteroptions;
    }

    protected function define_contentoptions() {
        $contentoptions = array(
            new rb_content_option(
                'current_pos',
                get_string('currentpos', 'totara_reportbuilder'),
                'position.path',
                'position'
            ),
            new rb_content_option(
                'current_org',
                get_string('currentorg', 'totara_reportbuilder'),
                'organisation.path',
                'organisation'
            ),
            new rb_content_option(
                'user',
                get_string('user', 'rb_source_ojt_completion'),
                array(
                    'userid' => 'base.userid',
                    'managerid' => 'position_assignment.managerid',
                    'managerpath' => 'position_assignment.managerpath',
                    'postype' => 'position_assignment.type',
                ),
                'position_assignment'
            ),
            new rb_content_option(
                'ojt_completion_type',
                get_string('ojtcompletiontype', 'rb_source_ojt_completion'),
                'base.type',
                'base'
            ),
        );
        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option(
                'ojtid',
                'base.ojtid'
            ),
        );

        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'user',
                'value' => 'namelink',
            ),
            array(
                'type' => 'course',
                'value' => 'courselink',
            ),
            array(
                'type' => 'ojt',
                'value' => 'name',
            ),
            array(
                'type' => 'ojt_topic',
                'value' => 'name',
            ),
            array(
                'type' => 'base',
                'value' => 'type',
            ),
            array(
                'type' => 'base',
                'value' => 'status',
            ),

        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'ojt',
                'value' => 'name',
            ),
            array(
                'type' => 'ojt_topic',
                'value' => 'name',
            ),
            array(
                'type' => 'base',
                'value' => 'type',
            ),
            array(
                'type' => 'base',
                'value' => 'status',
            ),
            array(
                'type' => 'user',
                'value' => 'fullname',
            ),
            array(
                'type' => 'course',
                'value' => 'fullname',
                'advanced' => 1,
            ),
        );

        return $defaultfilters;
    }

    protected function define_requiredcolumns() {
        $requiredcolumns = array();
        return $requiredcolumns;
    }

    //
    //
    // Source specific column display methods
    //
    //

    function rb_display_ojt_completion_status($status, $row, $isexport) {
        if (empty($status)) {
            return get_string('completionstatus'.OJT_INCOMPLETE, 'ojt');
        } else {
            return get_string('completionstatus'.$status, 'ojt');
        }
    }

    function rb_display_ojt_type($type, $row, $isexport) {
        return get_string('type'.$type, 'ojt');
    }

    function rb_display_ojt_link($ojtname, $row, $isexport) {
        return html_writer::link(new moodle_url('/mod/ojt/evaluate.php',
            array('userid' => $row->userid, 'bid' => $row->ojtid)), $ojtname);

    }

    function rb_display_ojt_evaluate_link($ojtname, $row, $isexport) {
        return html_writer::link(new moodle_url('/mod/ojt/evaluate.php',
            array('userid' => $row->userid, 'bid' => $row->ojtid)), get_string('evaluate', 'rb_source_ojt_completion'));

    }

    function rb_display_ojt_topic_signedoff($signedoff, $row, $isexport) {

        return !empty($signedoff) ? get_string('yes') : get_string('no');

    }




    //
    //
    // Source specific filter display methods
    //
    //

    function rb_filter_ojt_completion_status_list() {
        $statuses = array(OJT_INCOMPLETE, OJT_REQUIREDCOMPLETE, OJT_COMPLETE);
        $statuslist = array();
        foreach ($statuses as $status) {
            $statuslist[$status] = get_string('completionstatus'.$status, 'ojt');
        }

        return $statuslist;
    }

    function rb_filter_ojt_type_list() {
        $types = array(OJT_CTYPE_OJT, OJT_CTYPE_TOPIC);
        $typelist = array();
        foreach ($types as $type) {
            $typelist[$type] = get_string('type'.$type, 'ojt');
        }

        return $typelist;
    }
} // end of rb_source_course_completion class



/**
 * Restrict content by ojt completion type
 *
 * Pass in an integer that represents a ojt completion type, e.g OJT_CTYPE_TOPIC
 */
class rb_ojt_completion_type_content extends rb_base_content {

    /**
     * Generate the SQL to apply this content restriction
     *
     * @param string $field SQL field to apply the restriction against
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    public function sql_restriction($field, $reportid) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/ojt/lib.php');

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);

        return array('base.type = :crbct', array('crbct' => $settings['completiontype']));
    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    public function text_restriction($title, $reportid) {
        global $DB;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);

        return !empty($settings['completiontype']) ? $title.' - '.get_string('type'.$settings['completiontype'], 'ojt') : '';
    }


    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    public function form_template(&$mform, $reportid, $title) {
        // get current settings
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');
        $completiontype = reportbuilder::get_setting($reportid, $type, 'completiontype');

        $mform->addElement('header', 'ojt_completion_type_header',
            get_string('showbyx', 'totara_reportbuilder', lcfirst($title)));
        $mform->setExpanded('ojt_completion_type_header');
        $mform->addElement('checkbox', 'ojt_completion_type_enable', '',
            get_string('completiontypeenable', 'rb_source_ojt_completion'));
        $mform->setDefault('ojt_completion_type_enable', $enable);
        $mform->disabledIf('ojt_completion_type_enable', 'contentenabled', 'eq', 0);
        $radiogroup = array();
        $radiogroup[] =& $mform->createElement('radio', 'ojt_completion_type_completiontype',
            '', get_string('type'.OJT_CTYPE_OJT, 'ojt'), OJT_CTYPE_OJT);
        $radiogroup[] =& $mform->createElement('radio', 'ojt_completion_type_completiontype',
            '', get_string('type'.OJT_CTYPE_TOPIC, 'ojt'), OJT_CTYPE_TOPIC);
        $mform->addGroup($radiogroup, 'ojt_completion_type_completiontype_group',
            get_string('includecompltyperecords', 'rb_source_ojt_completion'), html_writer::empty_tag('br'), false);
        $mform->setDefault('ojt_completion_type_completiontype', $completiontype);
        $mform->disabledIf('ojt_completion_type_completiontype_group', 'contentenabled',
            'eq', 0);
        $mform->disabledIf('ojt_completion_type_completiontype_group', 'ojt_completion_type_enable',
            'notchecked');
    }


    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    public function form_process($reportid, $fromform) {
        $status = true;
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);

        // enable checkbox option
        $enable = (isset($fromform->ojt_completion_type_enable) &&
            $fromform->ojt_completion_type_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        // recursive radio option
        $recursive = isset($fromform->ojt_completion_type_completiontype) ?
            $fromform->ojt_completion_type_completiontype : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'completiontype', $recursive);

        return $status;
    }
}

