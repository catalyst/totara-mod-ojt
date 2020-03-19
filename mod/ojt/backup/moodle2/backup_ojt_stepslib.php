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

defined('MOODLE_INTERNAL') || die;

class backup_ojt_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the backup structure of the module
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        // Get know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define the root element describing the ojt instance.
        $ojt = new backup_nested_element('ojt', array('id'), array(
            'name', 'intro', 'introformat', 'grade'));

        $topics = new backup_nested_element('topics');
        $topic = new backup_nested_element('topic', array('id'), array(
            'name', 'completionreq'));

        $items = new backup_nested_element('items');
        $item = new backup_nested_element('item', array('id'), array(
            'name', 'completionreq'));

        $completions = new backup_nested_element('completions');
        $completion = new backup_nested_element('completion', array('id'), array(
            'userid', 'type', 'topicid', 'topicitemid', 'status', 'complcomment', 'timemodified', 'modifiedby'));

        $topic_signoffs = new backup_nested_element('topic_signoffs');
        $topic_signoff = new backup_nested_element('topic_signoff', array('id'), array(
            'userid', 'topicid', 'signedoff', 'timemodified', 'modifiedby'));

        $item_witnesses = new backup_nested_element('item_witnesses');
        $item_witness = new backup_nested_element('item_witness', array('id'), array(
            'userid', 'topicitemid', 'witnessedby', 'timewitnessed'));

        // Build the tree
        $ojt->add_child($topics);
        $topics->add_child($topic);
        $topic->add_child($items);
        $items->add_child($item);

        $ojt->add_child($completions);
        $completions->add_child($completion);

        $ojt->add_child($topic_signoffs);
        $topic_signoffs->add_child($topic_signoff);

        $ojt->add_child($item_witnesses);
        $item_witnesses->add_child($item_witness);

        // Define data sources.
        $ojt->set_source_table('ojt', array('id' => backup::VAR_ACTIVITYID));

        $topic->set_source_sql('
            SELECT *
              FROM {ojt_topic}
             WHERE ojtid = ?',
            array(backup::VAR_PARENTID));

        $item->set_source_sql('
            SELECT *
              FROM {ojt_topic_item}
             WHERE topicid = ?',
            array(backup::VAR_PARENTID));

        if ($userinfo) {
            $completion->set_source_sql('
                SELECT *
                  FROM {ojt_completion}
                 WHERE ojtid = ?',
                array(backup::VAR_ACTIVITYID));

            $topic_signoff->set_source_sql('
                SELECT *
                  FROM {ojt_topic_signoff}
                  WHERE topicid IN (
                      SELECT id
                      FROM {ojt_topic}
                      WHERE ojtid = ?
                  )',
                array(backup::VAR_ACTIVITYID));

            $item_witness->set_source_sql('
                SELECT *
                  FROM {ojt_item_witness}
                  WHERE topicitemid IN (
                      SELECT ti.id
                      FROM {ojt_topic} t
                      JOIN {ojt_topic_item} ti ON t.id = ti.topicid
                      WHERE t.ojtid = ?
                  )',
                array(backup::VAR_ACTIVITYID));

        }

        // If we were referring to other tables, we would annotate the relation
        // with the element's annotate_ids() method.
        $completion->annotate_ids('user', 'userid');
        $completion->annotate_ids('user', 'modifiedby');

        $topic_signoff->annotate_ids('user', 'userid');
        $topic_signoff->annotate_ids('user', 'modifiedby');

        $item_witness->annotate_ids('user', 'userid');

        // Define file annotations (we do not use itemid in this example).
        $ojt->annotate_files('mod_ojt', 'intro', null);

        // Return the root element (ojt), wrapped into standard activity structure.
        return $this->prepare_activity_structure($ojt);
    }
}
