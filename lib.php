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
 * Has supporting functions for the student activity report.
 *
 * @package    report_studentactivity
 * @copyright  UC Regents 2017
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * This function extends the navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_studentactivity_extend_navigation_course($navigation, $course, $context) {
    global $CFG, $OUTPUT;
    if (has_capability('report/studentactivity:view', $context)) {
        $url = new moodle_url('/report/studentactivity/index.php', array('id' => $course->id));
        $navigation->add(get_string('pluginname', 'report_studentactivity'), $url,
                navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }
}

/**
 * Gets total group users.
 *
 * @param string $tgid
 * @return int
 */
function get_total_group_users($tgid) {
    global $DB;
    if ($this->totalusers === null) {
        list($instancessql, $params, $filter) = $this->get_instance_sql();
        list($filtersql, $moreparams) = $this->get_filter_sql();
        $params += $moreparams;
        $sqltotal = "SELECT COUNT(DISTINCT u.id)
                       FROM {user} u
                       JOIN {user_enrolments} ue ON (ue.userid = u.id  AND ue.enrolid $instancessql)
                       JOIN {enrol} e ON (e.id = ue.enrolid)
                       JOIN {groups_members} gm ON (gm.userid = u.id)
                       JOIN {groups} g ON (g.id = gm.groupid)
                      WHERE $filtersql and g.id=$tgid";
        $this->totalusers = (int)$DB->count_records_sql($sqltotal, $params);
    }
    return $this->totalusers;
}

/**
 * Modified from function get_users.
 *
 * Gets all of the users enrolled in this course who are members
 * in a specific group.
 *
 * If a filter was specified this will be the users who were enrolled
 * in this course by means of that instance. If role or search filters were
 * specified then these will also be applied.
 *
 * @param string $sort
 * @param string $direction ASC or DESC
 * @param int $page First page should be 0
 * @param int $perpage Defaults to 25
 * @param int $tcid - course id
 * @param int $tgid - group id
 * @return array
 */
function get_group_users($sort, $direction='ASC', $page=0, $perpage=25, $tcid, $tgid=0) {
    global $DB;
    if ($direction !== 'ASC') {
        $direction = 'DESC';
    }
    $key = md5("$sort-$direction-$page-$perpage");
    if (!array_key_exists($key, $this->users)) {
        list($instancessql, $params, $filter) = $this->get_instance_sql();
        list($filtersql, $moreparams) = $this->get_filter_sql();
        $params += $moreparams;
        $extrafields = get_extra_user_fields($this->get_context());
        $extrafields[] = 'lastaccess';
        $ufields = user_picture::fields('u', $extrafields);
            $sql = "SELECT DISTINCT $ufields, COALESCE(ul.timeaccess, 0) AS lastcourseaccess
                  FROM {user} u
                  JOIN {user_enrolments} ue ON (ue.userid = u.id  AND ue.enrolid $instancessql)
                  JOIN {enrol} e ON (e.id = ue.enrolid)
                  JOIN {groups_members} gm on (gm.userid = u.id)
                  JOIN {groups} g on (g.id = gm.groupid)
             LEFT JOIN {user_lastaccess} ul ON (ul.courseid = e.courseid AND ul.userid = u.id)
                 WHERE ($filtersql) and gm.groupid=$tgid and g.courseid=$tcid";
        if ($sort === 'firstname') {
            $sql .= " ORDER BY u.firstname $direction, u.lastname $direction";
        } else if ($sort === 'lastname') {
            $sql .= " ORDER BY u.lastname $direction, u.firstname $direction";
        } else if ($sort === 'email') {
            $sql .= " ORDER BY u.email $direction, u.lastname $direction, u.firstname $direction";
        } else if ($sort === 'lastseen') {
            $sql .= " ORDER BY ul.timeaccess $direction, u.lastname $direction, u.firstname $direction";
        }
        $this->users[$key] = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
    }
    return $this->users[$key];
}

/**
 * Modified from function get_users_for_display.
 *
 * Gets an array of users for display, this includes minimal user information
 * as well as minimal information on the users roles, groups, and enrolments.
 * Limits selected users to members of specified group.
 *
 * @param course_enrolment_manager $manager
 * @param int $sort
 * @param string $direction ASC or DESC
 * @param int $page
 * @param int $perpage
 * @param int $tcid - course id
 * @param int $tgid - group id
 * @return array
 */
function get_group_users_for_display(course_enrolment_manager $manager, $sort, $direction, $page, $perpage, $tcid, $tgid) {
    $pageurl = $manager->get_moodlepage()->url;
    $users = $this->get_group_users($sort, $direction, $page, $perpage, $tcid, $tgid);

    $now = time();
    $straddgroup = get_string('addgroup', 'group');
    $strunenrol = get_string('unenrol', 'enrol');
    $stredit = get_string('edit');

    $allroles   = $this->get_all_roles();
    $assignable = $this->get_assignable_roles();
    $allgroups  = $this->get_all_groups();
    $context    = $this->get_context();
    $canmanagegroups = has_capability('moodle/course:managegroups', $context);

    $url = new moodle_url($pageurl, $this->get_url_params());
    $extrafields = get_extra_user_fields($context);

    $enabledplugins = $this->get_enrolment_plugins(true);

    $userdetails = array();
    foreach ($users as $user) {
        $details = $this->prepare_user_for_display($user, $extrafields, $now);

        // Roles.
        $details['roles'] = array();
        foreach ($this->get_user_roles($user->id) as $rid => $rassignable) {
            $unchangeable = !$rassignable;
            if (!is_siteadmin() and !isset($assignable[$rid])) {
                $unchangeable = true;
            }
            $details['roles'][$rid] = array('text' => $allroles[$rid]->localname, 'unchangeable' => $unchangeable);
        }

        // Users.
        $usergroups = $this->get_user_groups($user->id);
        $details['groups'] = array();
        foreach ($usergroups as $gid => $unused) {
            $details['groups'][$gid] = $allgroups[$gid]->name;
        }

        // Enrolments.
        $details['enrolments'] = array();
        foreach ($this->get_user_enrolments($user->id) as $ue) {
            if (!isset($enabledplugins[$ue->enrolmentinstance->enrol])) {
                $details['enrolments'][$ue->id] = array(
                    'text' => $ue->enrolmentinstancename,
                    'period' => null,
                    'dimmed' => true,
                    'actions' => array()
                );
                continue;
            } else if ($ue->timestart and $ue->timeend) {
                $period = get_string('periodstartend', 'enrol',
                        array('start' => userdate($ue->timestart), 'end' => userdate($ue->timeend)));
                $periodoutside = ($ue->timestart && $ue->timeend && ($now < $ue->timestart || $now > $ue->timeend));
            } else if ($ue->timestart) {
                $period = get_string('periodstart', 'enrol', userdate($ue->timestart));
                $periodoutside = ($ue->timestart && $now < $ue->timestart);
            } else if ($ue->timeend) {
                $period = get_string('periodend', 'enrol', userdate($ue->timeend));
                $periodoutside = ($ue->timeend && $now > $ue->timeend);
            } else {
                // If there is no start or end show when user was enrolled.
                $period = get_string('periodnone', 'enrol', userdate($ue->timecreated));
                $periodoutside = false;
            }
            $details['enrolments'][$ue->id] = array(
                'text' => $ue->enrolmentinstancename,
                'period' => $period,
                'dimmed' => ($periodoutside or $ue->status != ENROL_USER_ACTIVE
                        or $ue->enrolmentinstance->status != ENROL_INSTANCE_ENABLED),
                'actions' => $ue->enrolmentplugin->get_user_enrolment_actions($manager, $ue)
            );
        }
        $userdetails[$user->id] = $details;
    }
    return $userdetails;
}

/**
 * Creates the forum menu list.
 *
 * @param object $course
 * @return array
 */
function forum_menu_list($course) {

    $menu = array();

    $modinfo = get_fast_modinfo($course);

    if (empty($modinfo->instances['forum'])) {
        return $menu;
    }

    foreach ($modinfo->instances['forum'] as $cm) {
        if (!$cm->uservisible) {
            continue;
        }
        $context = context_module::instance($cm->id);
        if (!has_capability('mod/forum:viewdiscussion', $context)) {
            continue;
        }
        $menu[$cm->instance] = format_string($cm->name);
    }

    return $menu;
}