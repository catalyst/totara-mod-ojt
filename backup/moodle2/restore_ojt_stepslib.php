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
 * Structure step to restore one ojt activity
 */
class restore_ojt_activity_structure_step extends restore_activity_structure_step {

    /**
     * Defines structure of path elements to be processed during the restore
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_structure() {

        // Get know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        $paths = array();
        $paths[] = new restore_path_element('ojt', '/activity/ojt');
        $paths[] = new restore_path_element('ojt_topic', '/activity/ojt/topics/topic');
        $paths[] = new restore_path_element('ojt_topic_item', '/activity/ojt/topics/topic/items/item');
        if ($userinfo) {
            $paths[] = new restore_path_element('ojt_completion', '/activity/ojt/completions/completion');
            $paths[] = new restore_path_element('ojt_topic_signoff', '/activity/ojt/topic_signoffs/topic_signoff');
            $paths[] = new restore_path_element('ojt_item_witness', '/activity/ojt/item_witnesses/item_witness');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the given restore path element data for the ojt activity
     *
     * @param array $data parsed element data
     */
    protected function process_ojt($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->timecreated = time();
        $data->timemodified = time();

        // Create the ojt instance.
        $newitemid = $DB->insert_record('ojt', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process the given restore path element data for ojt topics
     *
     * @param array $data parsed element data
     */
    protected function process_ojt_topic($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->ojtid = $this->get_new_parentid('ojt');

        // Add ojt topic.
        $newitemid = $DB->insert_record('ojt_topic', $data);
        $this->set_mapping('ojt_topic', $oldid, $newitemid);
    }

    /**
     * Process the given restore path element data for ojt topic items
     *
     * @param array $data parsed element data
     */
    protected function process_ojt_topic_item($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->topicid = $this->get_new_parentid('ojt_topic');

        // Add ojt topic.
        $newitemid = $DB->insert_record('ojt_topic_item', $data);
        $this->set_mapping('ojt_topic_item', $oldid, $newitemid);
    }

    /**
     * Process the given restore path element data for ojt completion
     *
     * @param array $data parsed element data
     */
    protected function process_ojt_completion($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->ojtid = $this->get_new_parentid('ojt');
        $data->topicid = $this->get_mappingid('ojt_topic', $data->topicid);
        $data->topicitemid = $this->get_mappingid('ojt_topic_item', $data->topicitemid);
        $data->modifiedby = $this->get_mappingid('user', $data->userid);

        // Add ojt topic.
        $newitemid = $DB->insert_record('ojt_completion', $data);
    }

    /**
     * Process the given restore path element data for ojt topic signoffs
     *
     * @param array $data parsed element data
     */
    protected function process_ojt_topic_signoff($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->topicid = $this->get_mappingid('ojt_topic', $data->topicid);
        $data->modifiedby = $this->get_mappingid('user', $data->userid);

        // Add ojt topic.
        $newitemid = $DB->insert_record('ojt_topic_signoff', $data);
    }

    /**
     * Process the given restore path element data for ojt item completion witnesses
     *
     * @param array $data parsed element data
     */
    protected function process_ojt_item_witness($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->topicitemid = $this->get_mappingid('ojt_topic_item', $data->topicitemid);
        $data->witnessedby = $this->get_mappingid('user', $data->witnessedby);

        // Add ojt topic.
        $newitemid = $DB->insert_record('ojt_item_witness', $data);
    }



    /**
     * Post-execution actions
     */
    protected function after_execute() {
        // Add ojt related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_ojt', 'intro', null);
    }
}
