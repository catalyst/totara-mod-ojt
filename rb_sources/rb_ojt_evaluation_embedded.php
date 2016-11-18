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

global $CFG;
require_once($CFG->dirroot.'/totara/reportbuilder/classes/rb_base_content.php');

# Include the ojt rb source, to ensure all default settings get created upon report creation
require_once($CFG->dirroot.'/mod/ojt/rb_sources/rb_source_ojt_completion.php');

require_once($CFG->dirroot.'/mod/ojt/lib.php');

class rb_ojt_evaluation_embedded extends rb_base_embedded {

    public $url, $source, $fullname, $filters, $columns;
    public $contentmode, $contentsettings, $embeddedparams;
    public $hidden, $accessmode, $accesssettings, $shortname;
    public $defaultsortcolumn, $defaultsortorder;

    public function __construct($data) {
        $ojtid = array_key_exists('ojtid', $data) ? $data['ojtid'] : null;

        $url = new moodle_url('/mod/ojt/report.php', $data);
        $this->url = $url->out_as_local_url();
        $this->source = 'ojt_completion';
        $this->defaultsortcolumn = 'user_namelink';
        $this->shortname = 'ojt_evaluation';
        $this->fullname = get_string('ojtevaluation', 'rb_source_ojt_completion');
        $this->columns = array(
            array(
                'type' => 'user',
                'value' => 'namelink',
                'heading' => get_string('name', 'rb_source_user'),
            ),
            array(
                'type' => 'base',
                'value' => 'status',
                'heading' => get_string('status', 'rb_source_ojt_completion'),
            ),
            array(
                'type' => 'ojt',
                'value' => 'evaluatelink',
                'heading' => ' ',
            ),
        );

        // no filters
        $this->filters = array(
            array(
                'type' => 'user',
                'value' => 'fullname',
                'advanced' => 0,
            ),
            array(
                'type' => 'base',
                'value' => 'status',
                'advanced' => 0,
            ),
        );

        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_ALL;
        $this->contentsettings = array(
            'ojt_completion_type' => array(
                'enable' => 1,
                'completiontype' => OJT_CTYPE_OJT
            )
        );

        // only show non-deleted users
        $this->embeddedparams = array();
        if (!empty($ojtid)) {
            $this->embeddedparams['ojtid'] = $ojtid;
        }

        parent::__construct($data);
    }

    /**
     * Check if the user is capable of accessing this report.
     * We use $reportfor instead of $USER->id and $report->get_param_value() instead of getting params
     * some other way so that the embedded report will be compatible with the scheduler (in the future).
     *
     * @param int $reportfor userid of the user that this report is being generated for
     * @param reportbuilder $report the report object - can use get_param_value to get params
     * @return boolean true if the user can access this report
     */
    public function is_capable($reportfor, $report) {
        return true;
    }

    /**
     * Ignore this reportbuilder source in Totara core unit tests
     * PHPUnit test for this custom reportbuilder source should be inside of this plugin.
     * TODO: write unit tests for this embedded source.
     *
     * @return true if this is a phpunit test execution and false otherwise.
     */
    public function is_ignored() {
        if (PHPUNIT_TEST) {
            return true;
        }

        return false;
    }
}
