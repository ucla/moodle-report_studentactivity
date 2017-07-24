<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Form for student activity report table controls
 *
 * @package    report_studentactivity
 * @copyright  2017 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Form that lets users filter the student activity list by group membership.
 *
 * @copyright  2017 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tracking_users_filter_form extends moodleform {

    /**
     * Defines the form
     * @return void
     */
    public function definition() {
        global $CFG, $DB;

        $manager = $this->_customdata['manager'];

        $mform = $this->_form;
        $allgroups = $manager->get_all_groups();
        $groupnames = array();
        foreach ($allgroups as $group) {
            $groupnames[$group->id] = $group->name;
        }

        // Filter by student group membership.
        $dirtyclass = array('class' => 'ignoredirty', 'style' => 'width: auto');
        $mform->addElement('select', 'tgid', get_string('groupfilter', 'report_studentactivity'),
                array(0 => get_string('allparticipants', 'report_studentactivity')) + $groupnames, $dirtyclass);

        $courseid = $this->_customdata['id'];
        $allforums = forum_menu_list($courseid);

        // Filter by forum.
        $mform->addElement(
                'select', 'tfid', '&nbsp;' . get_string('forumfilter', 'report_studentactivity'),
                array(0 => get_string('allforums', 'report_studentactivity')) + $allforums, $dirtyclass
        );

        // Submit button does not use add_action_buttons because that adds
        // another fieldset which causes the CSS style to break in an unfixable
        // way due to fieldset quirks.

        // Add hidden fields required by page.
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
    }
}
