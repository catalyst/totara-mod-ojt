<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 * Copyright (C) 1999 onwards Martin Dougiamas
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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package mod
 * @subpackage ojt
 */

defined('MOODLE_INTERNAL') || die();

class rb_source_ojt_topic_item_completion extends rb_base_source {
    use \core_course\rb\source\report_trait;
    use \totara_job\rb\source\report_trait;
    use \core_tag\rb\source\report_trait;
    use \totara_cohort\rb\source\report_trait;



    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    function __construct($groupid, rb_global_restriction_set $globalrestrictionset = null) {
        if ($groupid instanceof rb_global_restriction_set) {
            throw new coding_exception('Wrong parameter orders detected during report source instantiation.');
        }

        // Remember the active global restriction set.
        $this->globalrestrictionset = $globalrestrictionset;

        // Apply global user restrictions.
        $this->add_global_report_restriction_join('base', 'userid');

        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/ojt/lib.php');

        $this->base = '{ojt_completion}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('ojttopicitemcompletion', 'rb_source_ojt_topic_item_completion');
        $this->sourcewhere = 'base.type = '.OJT_CTYPE_TOPICITEM;
        $this->usedcomponents[] = 'mod_ojt';
        $this->usedcomponents[] = 'totara_cohort';

        parent::__construct();
    }

    /**
     * Global report restrictions are implemented in this source.
     * @return boolean
     */
    public function global_restrictions_supported() {
        return true;
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
                'ojt_topic_item',
                'LEFT',
                '{ojt_topic_item}',
                'base.topicitemid = ojt_topic_item.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'modifyuser',
                'LEFT',
                '{user}',
                'base.modifiedby = modifyuser.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
        );

        // include some standard joins
        $this->add_core_user_tables($joinlist, 'base', 'userid');
        $this->add_core_course_tables($joinlist, 'ojt', 'course');
        // requires the course join
        $this->add_core_course_category_tables($joinlist,
            'course', 'category');
        $this->add_totara_job_tables($joinlist, 'base', 'userid');
        $this->add_core_tag_tables('core', 'course', $joinlist, 'ojt', 'course');
        $this->add_totara_cohort_course_tables($joinlist, 'ojt', 'course');

        return $joinlist;
    }

    protected function define_columnoptions() {
        global $DB;

        $columnoptions = array(
            new rb_column_option(
                'ojt',
                'name',
                get_string('ojt', 'rb_source_ojt_topic_item_completion'),
                'ojt.name',
                array('joins' => 'ojt', 'displayfunc' => 'ojt_link',
                    'extrafields' => array('userid' => 'base.userid', 'ojtid' => 'base.ojtid'))
            ),
            new rb_column_option(
                'ojt',
                'evaluatelink',
                get_string('evaluatelink', 'rb_source_ojt_topic_item_completion'),
                'ojt.name',
                array('joins' => 'ojt', 'displayfunc' => 'ojt_evaluate_link',
                    'extrafields' => array('userid' => 'base.userid', 'ojtid' => 'base.ojtid'))
            ),
            new rb_column_option(
                'ojt_topic',
                'name',
                get_string('topic', 'rb_source_ojt_topic_item_completion'),
                'ojt_topic.name',
                array('joins' => 'ojt_topic', 'displayfunc' => 'format_string')
            ),
            new rb_column_option(
                'ojt_topic_item',
                'name',
                get_string('topicitem', 'rb_source_ojt_topic_item_completion'),
                'ojt_topic_item.name',
                array('joins' => 'ojt_topic_item', 'displayfunc' => 'format_string')
            ),
            new rb_column_option(
                'base',
                'status',
                get_string('completionstatus', 'rb_source_ojt_topic_item_completion'),
                'base.status',
                array('displayfunc' => 'ojt_completion_status')
            ),
            new rb_column_option(
                'base',
                'timemodified',
                get_string('timemodified', 'rb_source_ojt_topic_item_completion'),
                'base.timemodified',
                array('displayfunc' => 'nice_datetime')
            ),
            new rb_column_option(
                'base',
                'modifiedby',
                get_string('modifiedby', 'rb_source_ojt_topic_item_completion'),
                $DB->sql_fullname("modifyuser.firstname", "modifyuser.lastname"),
                array(
                    'joins' => 'modifyuser',
                    'displayfunc' => 'user_link',
                    'extrafields' => array('user_id' => "modifyuser.id"),
                )
            ),
            new rb_column_option(
                'base',
                'comment',
                get_string('comment', 'rb_source_ojt_topic_item_completion'),
                'base.comment',
                array('displayfunc' => 'format_string')
            ),

        );

        // include some standard columns
        $this->add_core_user_columns($columnoptions);
        $this->add_core_course_columns($columnoptions);
        $this->add_core_course_category_columns($columnoptions);
        $this->add_totara_job_columns($columnoptions);
        $this->add_core_tag_columns('core', 'course', $columnoptions);
        $this->add_totara_cohort_course_columns($columnoptions);

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array(

            new rb_filter_option(
                'ojt',
                'name',
                get_string('ojtname', 'rb_source_ojt_topic_item_completion'),
                'text'
            ),
            new rb_filter_option(
                'ojt_topic',
                'name',
                get_string('topicname', 'rb_source_ojt_topic_item_completion'),
                'text'
            ),
            new rb_filter_option(
                'ojt_topic_item',
                'name',
                get_string('topicitemname', 'rb_source_ojt_topic_item_completion'),
                'text'
            ),
            new rb_filter_option(
                'base',
                'comment',
                get_string('comment', 'rb_source_ojt_topic_item_completion'),
                'text'
            ),
            new rb_filter_option(
                'base',
                'timemodified',
                get_string('timemodified', 'rb_source_ojt_topic_item_completion'),
                'date'
            ),
            new rb_filter_option(
                'base',
                'status',
                get_string('completionstatus', 'rb_source_ojt_topic_item_completion'),
                'select',
                array(
                    'selectfunc' => 'ojt_completion_status_list',
                )
            ),
        );

        // include some standard filters
        $this->add_core_user_filters($filteroptions);
        $this->add_core_course_filters($filteroptions);
        $this->add_core_course_category_filters($filteroptions);
        $this->add_totara_job_filters($columnoptions);
        $this->add_core_tag_filters('core', 'course', $filteroptions);
        $this->add_totara_cohort_course_filters($filteroptions);

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
                get_string('user', 'rb_source_ojt_topic_item_completion'),
                array(
                    'userid' => 'base.userid',
                    'managerid' => 'position_assignment.managerid',
                    'managerpath' => 'position_assignment.managerpath',
                    'postype' => 'position_assignment.type',
                ),
                'position_assignment'
            ),
        );
        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array();

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
                'type' => 'ojt_topic_item',
                'value' => 'name',
            ),
            array(
                'type' => 'base',
                'value' => 'status',
            ),
            array(
                'type' => 'base',
                'value' => 'comment',
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
                'type' => 'ojt_topic_item',
                'value' => 'name',
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
        $requiredcolumns = array(
            new rb_column(
                'ojt_topic_item',
                'id',
                '',
                'ojt_topic_item.id',
                array('joins' => 'ojt_topic_item')
            ),
        );

        return $requiredcolumns;
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


    /**
     * Unit test data
     */

    /**
     * Inject column_test data into database.
     * @param totara_reportbuilder_column_testcase $testcase
     */
    public function phpunit_column_test_add_data(totara_reportbuilder_column_testcase $testcase) {
       global $DB;

       if (!PHPUNIT_TEST) {
           throw new coding_exception('phpunit_prepare_test_data() cannot be used outside of unit tests');
       }
       $data = array(
            'ojt' => array(
                array('id' => 1, 'course' => 1, 'name' => 'test ojt', 'intro' => '', 'timecreated' => 1)
            ),
            'ojt_topic' => array(
                array('id' => 1, 'ojtid' => 1, 'name' => 'test ojt topic')
            ),
            'ojt_topic_item' => array(
                array('id' => 1, 'ojtid' => 1, 'topicid' => 1, 'name' => 'test ojt topic item')
            ),
            'ojt_completion' => array(
                array('id' => 1, 'userid' => 2, 'type' => 0, 'ojtid' => 1, 'topicid' => 0, 'topicitemid' => 0, 'status' => 1, 'modifiedby' => 1),
                array('id' => 2, 'userid' => 2, 'type' => 1, 'ojtid' => 1, 'topicid' => 1, 'topicitemid' => 0, 'status' => 1, 'modifiedby' => 1),
                array('id' => 3, 'userid' => 2, 'type' => 2, 'ojtid' => 1, 'topicid' => 1, 'topicitemid' => 1, 'status' => 1, 'modifiedby' => 1),
            ),
            'user_enrolments' => array(
                array('id' => 1, 'status' => 0, 'enrolid' => 1, 'userid' => 2)
            ),
        );
        foreach ($data as $table => $data) {
            foreach($data as $datarow) {
                $DB->import_record($table, $datarow);
            }
            $DB->get_manager()->reset_sequence(new xmldb_table($table));
       }
    }

} // class

