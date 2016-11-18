<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
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
 * @author Suan Kan <suankan@catalyst-au.net>
 * @package mod_ojt
 *
 * Unit tests to check source column definitions
 *
 * vendor/bin/phpunit mod_ojt_column_testcase
 *
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');

class mod_ojt_column_testcase extends reportcache_advanced_testcase {

    protected $ojt_data = array(
        'id' => 1,
        'course' => 2,
        'name' => 'Test Course 1',
        'intro' => '<p>TEST OJT description<br></p>',
        'introformat' => 1,
        'completiontopics' => 1,
        'timecreated' => 1479268545,
        'timemodified' => 1479339671,
        'managersignoff' => 1,
        'itemwitness' => 1,
    );

    protected $ojt_topic_data = array(
        'id' => 1,
        'ojtid' => 1,
        'name' => 'Test topic 01',
        'completionreq' => 1,
        'competencies' => '',
        'allowcomments' => 1,
    );

    protected $ojt_completion_data = array(
        'id' => 2,
        'userid' => 2,
        'type' => 1,
        'ojtid' => 1,
        'topicid' => 1,
        'topicitemid' => 0,
        'status' => 2,
        'comment' => '',
        'timemodified' => 1479356197,
        'modifiedby' => 1,
    );

    protected $enrol_data = array(
        'id' => 40,
        'enrol' => 'self',
        'status' => 0,
        'courseid' => 2,
        'sortorder' => 2,
        'name' => '',
        'enrolperiod' => 0,
        'enrolstartdate' => 0,
        'enrolenddate' => 0,
        'expirynotify' => 0,
        'expirythreshold' => 86400,
        'notifyall' => 0,
        'password' => '',
        'cost' => '',
        'currency' => '',
        'roleid' => 1,
        'customint1' => 0,
        'customint2' => 0,
        'customint3' => 0,
        'customint4' => 1,
        'customint5' => 0,
        'customint6' => 1,
        'customint7' => 0,
        'customint8' => 0,
        'customchar1' => '',
        'customchar2' => '',
        'customchar3' => '',
        'customdec1' => 0,
        'customdec2' => 0,
        'customtext1' => '',
        'customtext2' => '',
        'customtext3' => '',
        'customtext4' => '',
        'timecreated' => 1461727153,
        'timemodified' => 1461727153,
    );

    protected $user_enrolments_data = array(
        'id' => 8207,
        'status' => 0,
        'enrolid' => 40,
        'userid' => 2,
        'timestart' => 1462756041,
        'timeend' => 0,
        'modifierid' => 1,
        'timecreated' => 1462756041,
        'timemodified' => 1462756041,
    );

    protected function setUp() {
        global $DB;
        parent::setup();
        set_config('enablecompletion', 1);

        $DB->delete_records('upgrade_log', array());

        // Create a job assignment.
        \totara_job\job_assignment::create_default(2, array('organisationid' => 1, 'positionid' => 1));

        $this->loadDataSet($this->createArrayDataset(array(
            'ojt' => array($this->ojt_data),
            'ojt_topic' => array($this->ojt_topic_data),
            'ojt_completion' => array($this->ojt_completion_data),
            'enrol' => array($this->enrol_data),
            'user_enrolments' => array($this->user_enrolments_data),
        )));
    }

    /**
     * Checks:
     * 1. Number of records (uncached/cached)
     * 2. Filters ojt.name and ojt_topic.name
     */
    public function test_columns_and_filters() {
        global $DB, $SESSION;

        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();

        $i = 1;
        $reportname = 'Test Report';
        $filtername = 'filtering_testreport';
        $sourcename = 'ojt_completion';

        // Create a report.
        $report = new stdClass();
        $report->fullname = $reportname;
        $report->shortname = 'test' . $i++;
        $report->source = $sourcename;
        $report->hidden = 0;
        $report->accessmode = 0;
        // Consider content restrictions in the report.
        $report->contentmode = 2;
        $reportid = $DB->insert_record('report_builder', $report);

        // Create the reportbuilder object.
        $rb = new reportbuilder($reportid);
        // Activate content restriction options:
        reportbuilder::update_setting($reportid, 'ojt_completion_type_content', 'enable', '1');
        reportbuilder::update_setting($reportid, 'ojt_completion_type_content', 'completiontype', '0');

        // Compose error message:
        $sql = $rb->build_query(false, true);
        $message = "Report sourcename : {$sourcename}\n";
        $message .= "SQL : {$sql[0]}\n";
        $message .= "SQL Params : " . var_export($sql[1], true) . "\n";

        // Test that created report contains one dummy record.
        $this->assertEquals('1', $rb->get_full_count(), $message);

        // Now, test the same with report caching.
        $rb = new reportbuilder($reportid);
        $this->enable_caching($reportid);
        reportbuilder::update_setting($reportid, 'ojt_completion_type_content', 'enable', '1');
        reportbuilder::update_setting($reportid, 'ojt_completion_type_content', 'completiontype', '0');

        $sql = $rb->build_query();
        $message .= "Report sourcename : {$sourcename}\n";
        $message .= "SQL : {$sql[0]}\n";
        $message .= "SQL Params : " . var_export($sql[1], true) . "\n";

        $this->assertEquals('1', $rb->get_full_count(), $message);

        // Test filters:
        $src = reportbuilder::get_source_object($sourcename, true);
        $sortorder = 1;

        foreach ($src->filteroptions as $filter) {
            // Check only custom filters that exist in the 'ojt_completion' report source.
            if ( ($filter->type == 'ojt' and $filter->value == 'name') or ($filter->type == 'ojt_topic' and $filter->value == 'name') ) {
                // Create a report.
                $report = new stdClass();
                $report->fullname = $reportname;
                $report->shortname = 'test' . $i++;
                $report->source = $sourcename;
                $report->hidden = 0;
                $report->accessmode = 0;
                $report->contentmode = 2;
                $reportid = $DB->insert_record('report_builder', $report);
                // If the filter is based on a column, include that column.
                if (empty($filter->field)) {
                    // Add a single column.
                    $col = new stdClass();
                    $col->reportid = $reportid;
                    $col->type = $filter->type;
                    $col->value = $filter->value;
                    $col->heading = 'Test' . $i++;
                    $col->sortorder = 1;
                    $colid = $DB->insert_record('report_builder_columns', $col);
                }
                // Add a single filter.
                $fil = new stdClass();
                $fil->reportid = $reportid;
                $fil->type = $filter->type;
                $fil->value = $filter->value;
                $fil->sortorder = $sortorder++;
                $filid = $DB->insert_record('report_builder_filters', $fil);

                // Set session to filter by this column.
                $fname = $filter->type . '-' . $filter->value;
                switch($filter->filtertype) {
                    case 'date':
                        $search = array('before' => null, 'after' => 1);
                        break;
                    case 'text':
                    case 'number':
                    case 'select':
                    default:
                        $search = array('operator' => 1, 'value' => 2);
                        break;
                }
                $SESSION->{$filtername} = array();
                $SESSION->{$filtername}[$fname] = array($search);

                // Create the reportbuilder object.
                $rb = new reportbuilder($reportid);
                reportbuilder::update_setting($reportid, 'ojt_completion_type_content', 'enable', '1');
                reportbuilder::update_setting($reportid, 'ojt_completion_type_content', 'completiontype', '0');

                $sql = $rb->build_query(false, true);
                $message .= "Report sourcename : {$sourcename}\n";
                $message .= "Filter option : Test {$filter->type}_{$filter->value} filter\n";
                $message .= "SQL : {$sql[0]}\n";
                $message .= "SQL Params : " . var_export($sql[1], true) . "\n";

                $this->assertRegExp('/[012]/', (string)$rb->get_filtered_count(), $message);
            }
        }
    }
}
