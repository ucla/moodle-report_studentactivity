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
 * Renderer for student activity report
 *
 * @package    report_studentactivity
 * @copyright  2017 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Extends the core renderer rather than directly modify it via core edit.
 * @copyright  2017 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_studentactivity_renderer extends core_enrol_renderer {
    
    /**
     * Renders a tracking table
     *
     * Modified from render_course_enrolment_users_table
     *
     * @param course_users_tracking_table $table
     * @param moodleform $mform Form that contains filter controls
     * @return string
     */
    public function render_course_users_tracking_table(course_users_tracking_table $table,
            moodleform $mform) {

        $table->initialise_javascript();

        $content = '';
        if (!empty($buttonhtml)) {
            $content .= $buttonhtml;
        }
        $content .= $mform->render();

        $content .= $this->output->render($table->get_paging_bar());

        // Check if the table has any bulk operations. If it does we want to wrap the table in a
        // form so that we can capture and perform any required bulk operations.
        if ($table->has_bulk_user_enrolment_operations()) {
            $content .= html_writer::start_tag('form', array(
                'action' => new moodle_url('/enrol/bulkchange.php'), 'method' => 'post'
            ));
            foreach ($table->get_combined_url_params() as $key => $value) {
                if ($key == 'action') {
                    continue;
                }
                $content .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $key, 'value' => $value));
            }
            $content .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'bulkchange'));
            $content .= html_writer::table($table);
            $content .= html_writer::start_tag('div', array('class' => 'singleselect bulkuserop'));
            $content .= html_writer::start_tag('select', array('name' => 'bulkuserop'));
            $content .= html_writer::tag('option', get_string('withselectedusers', 'enrol'), array('value' => ''));
            $options = array('' => get_string('withselectedusers', 'enrol'));
            foreach ($table->get_bulk_user_enrolment_operations() as $operation) {
                $content .= html_writer::tag('option', $operation->get_title(), array('value' => $operation->get_identifier()));
            }
            $content .= html_writer::end_tag('select');
            $content .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('go')));
            $content .= html_writer::end_tag('div');

            $content .= html_writer::end_tag('form');
        } else {
            $content .= html_writer::table($table);
        }
        $content .= $this->output->render($table->get_paging_bar());
        // This is kind of a cheat; modifies url above to point to tracking.
        $content = str_replace('/enrol/users.php', '/report/studentactivity/index.php', $content);
        if (!empty($buttonhtml)) {
            $content .= $buttonhtml;
        }
        return $content;
    }
}

/**
 * Table control used for tracking users
 *
 * Modified from class course_enrolment_users_table
 *
 * @copyright 2010 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_users_tracking_table extends course_enrolment_table {

    /**
     * An array of sortable fields
     * @var array
     */
    protected static $sortablefields = array('firstname', 'lastname', 'email', 'lastaccess');
}
