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
 * Page for showing the student activity report
 *
 * @package    report_studentactivity
 * @copyright  2017 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->dirroot/enrol/locallib.php");
require_once("$CFG->dirroot/enrol/users_forms.php");
require_once("$CFG->dirroot/enrol/renderer.php");
require_once("$CFG->dirroot/group/lib.php");

// From mod/forum/user.php.
require_once($CFG->dirroot.'/mod/forum/lib.php');
require_once($CFG->dirroot.'/rating/lib.php');

require_once('lib.php');
require_once('form.php');
require_once('manager.php');
require_once('renderer.php');

$id      = required_param('id', PARAM_INT); // Course id.
$action  = optional_param('action', '', PARAM_ALPHANUMEXT);
$filter  = optional_param('ifilter', 0, PARAM_INT);
$search  = optional_param('search', '', PARAM_RAW);
$role    = optional_param('role', 0, PARAM_INT);
$tgid    = optional_param('tgid', 0, PARAM_INT);
$tfid    = optional_param('tfid', 0, PARAM_INT);

// When users reset the form, redirect back to first page without other params.
if (optional_param('resetbutton', '', PARAM_RAW) !== '') {
    redirect('index.php?id=' . $id);
}

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

if ($course->id == SITEID) {
    redirect(new moodle_url('/'));
}

require_login($course);
require_capability('report/studentactivity:view', $context);
$PAGE->set_pagelayout('admin');

$manager = new studentactivity_course_enrolment_manager($PAGE, $course, $filter, $role, $search);
$table = new course_users_tracking_table($manager, $PAGE);

$PAGE->set_url('/report/studentactivity/index.php', $manager->get_url_params() + $table->get_url_params());
navigation_node::override_active_url(new moodle_url('/report/studentactivity/index.php', array('id' => $id)));

$renderer = $PAGE->get_renderer('report_studentactivity');

$userdetails = array('picture' => false, 'userfullnamedisplay' => false);
// Get all the user names in a reasonable default order.
$allusernames = get_all_user_name_fields(false, null, null, null, true);
// Initialise the variable for the user's names in the table header.
$usernameheader = null;
// Get the alternative full name format for users with the viewfullnames capability.
$fullusernames = $CFG->alternativefullnameformat;
// If fullusernames is empty or accidentally set to language then fall back to default of just first and last name.
if ($fullusernames == 'language' || empty($fullusernames)) {
    // Set $a variables to return 'firstname' and 'lastname'.
    $a = new stdClass();
    $a->firstname = 'firstname';
    $a->lastname = 'lastname';
    // Getting the fullname display will ensure that the order in the language file is maintained.
    $usernameheader = explode(' ', get_string('fullnamedisplay', null, $a));
} else {
    // If everything is as expected then put them in the order specified by the alternative full name format setting.
    $usernameheader = order_in_string($allusernames, $fullusernames);
}

// Loop through each name and return the language string.
foreach ($usernameheader as $key => $username) {
    $userdetails[$username] = get_string(str_replace(',', '', $username));
}

$extrafields = get_extra_user_fields($context);
foreach ($extrafields as $field) {
    $userdetails[$field] = get_user_field_name($field);
}

$fields = array(
    'userdetails' => $userdetails,
    'postdisc' => get_string('postdisc', 'report_studentactivity'),
    'completereport' => get_string('completereport'),
    'alllogs' => get_string('alllogs'),
    'lastcourseaccess' => get_string('lastcourseaccess'),
    'group' => get_string('groups', 'group')
);

// Remove hidden fields if the user has no access.
if (!has_capability('moodle/course:viewhiddenuserfields', $context)) {
    $hiddenfields = array_flip(explode(',', $CFG->hiddenuserfields));
    if (isset($hiddenfields['lastaccess'])) {
        unset($fields['lastcourseaccess']);
    }
    if (isset($hiddenfields['groups'])) {
        unset($fields['group']);
    }
}

$filterform = new tracking_users_filter_form('index.php', array('manager' => $manager, 'id' => $id), 'get',
        '', array('id' => 'filterform'));
$filterform->set_data(array('tgid' => $tgid, 'tfid' => $tfid));

$table->set_fields($fields, $renderer);

$canassign = has_capability('moodle/role:assign', $manager->get_context());
// If no group filter specified, use original function.
if ($tgid == 0) {
    $users = $manager->get_users_for_display($manager, $table->sort, $table->sortdirection, $table->page, $table->perpage);
    // Otherwise, get users by group filter.
} else {
    $users = $manager->get_group_users_for_display(
        $manager, $table->sort, $table->sortdirection, $table->page, $table->perpage, $id, $tgid
    );
}

foreach ($users as $userid => &$user) {
    $user['picture'] = $OUTPUT->render($user['picture']);
    $user['role'] = $renderer->user_roles_and_actions($userid, $user['roles'],
            $manager->get_assignable_roles(), $canassign, $PAGE->url);
    $user['group'] = $renderer->user_groups_and_actions($userid, $user['groups'], $manager->get_all_groups(), false, $PAGE->url);
    $user['enrol'] = $renderer->user_enrolments_and_actions($user['enrolments']);
    $pc = tracking_count($userid, $course->id, false, $tfid);
    $dc = tracking_count($userid, $course->id, true, $tfid);
    $user['postdisc'] = '<center><a href="'.$CFG->wwwroot.'/mod/forum/user.php?id='.
            $userid.'&course='.$id.'&tfid='.$tfid.'&tgid='.$tgid.'">'.$pc.'</a>'.' / '.
            '<a href="'.$CFG->wwwroot.'/mod/forum/user.php?id='.$userid.'&course='.$id.'&tfid='.
            $tfid.'&tgid='.$tgid.'&mode=discussions">'.$dc.'</a></center>';
    $user['completereport'] = '<a href="'.$CFG->wwwroot.'/report/outline/user.php?id='.
            $userid.'&course='.$id.'&mode=complete">'.
            get_string('completereport', 'report_studentactivity').'</a>';
    $user['alllogs'] = '<a href="'.$CFG->wwwroot.
            '/report/log/index.php?chooselog=1&showusers=1&id='.$id.'&user='.
            $userid.'&date=&modid=&modaction=&logformat=showashtml">'.
            get_string('alllogs', 'report_studentactivity').'</a>';
}
// Set page user count according to (no) group filter.
if ($tgid == 0) {
    $table->set_total_users($manager->get_total_users());
} else {
    $table->set_total_users($manager->get_total_group_users($tgid));
}
$table->set_users($users);

$titleextra = "";
if ($tgid != 0) {
    $grouprec = $DB->get_record('groups', array('id' => $tgid), '*', MUST_EXIST);
    $titleextra = $titleextra . ': ' . $grouprec->name;
} else {
    $titleextra = $titleextra . ": " . get_string('allparticipants');
}

if ($tfid != 0) {
    $forumrec = $DB->get_record('forum', array('id' => $tfid), '*', MUST_EXIST);
    $titleextra = $titleextra . ': ' . $forumrec->name;
} else {
    $titleextra = $titleextra . ": " . get_string('allforums', 'report_studentactivity');
}

$PAGE->set_title($PAGE->course->fullname.': '.get_string('tracking', 'report_studentactivity').$titleextra);
$PAGE->set_heading($PAGE->title);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('usertracking', 'report_studentactivity').$titleextra);

// More output hackeyness.
$hackoutput = $renderer->render_course_users_tracking_table($table, $filterform);

// Fix pagebar links on group filtered pages.
if ($tgid != 0) {
    // Fix pagebar links.
    $fixstr = "index.php?tgid=" . $tgid . "&";
    $hackoutput = str_replace('index.php?', $fixstr, $hackoutput);
}

// Make group filter autosubmit on group change.
$groupaction = "this.form.submit()";
$hackoutput = str_replace('name="tgid"', 'onchange="'.$groupaction.'" name="tgid"', $hackoutput);

// Fix pagebar links on forum filtered pages.
if ($tfid != 0) {
    // Fix pagebar links.
    $fixstr = "index.php?tfid=" . $tfid . "&";
    $hackoutput = str_replace('index.php?', $fixstr, $hackoutput);
}

// Make forum filter autosubmit on forum change.
$forumaction = "this.form.submit()";
$hackoutput = str_replace('name="tfid"', 'onchange="'.$forumaction.'" name="tfid"', $hackoutput);

echo $hackoutput;

echo $OUTPUT->footer();


/**
 * Gets count of posts
 *
 * @param string $u
 * @param int $c
 * @param bool $disco
 * @param int $tfid
 * @return int
 */
function tracking_count($u, $c, $disco, $tfid=0) {
    global $DB, $USER, $CFG;

    $res = new stdClass;
    $us = $DB->get_record("user", array("id" => $u), '*', MUST_EXIST);
    $co = $DB->get_record("course", array("id" => $c), '*', MUST_EXIST);
    $courses = array($c => $co);
    $res = tracking_get_posts_count_by_user($us, $courses, false, $disco, 0, 50, $tfid);

    return $res->totalcount;
}

/**
 *  Gets the number of posts by user
 *
 * @param object $user
 * @param array $courses
 * @param bool $musthaveaccess
 * @param bool $discussionsonly
 * @param int $limitfrom
 * @param int $limitnum
 * @param int $tfid
 * @return \stdClass
 */
function tracking_get_posts_count_by_user($user, array $courses, $musthaveaccess = false,
        $discussionsonly = false, $limitfrom = 0, $limitnum = 50, $tfid=0) {
    global $DB, $USER, $CFG;

    $return = new stdClass;
    $return->totalcount = 0;    // The total number of posts that the current user is able to view.
    $return->courses = array(); // The courses the current user can access.
    $return->forums = array();  // The forums that the current user can access that contain posts.
    $return->posts = array();   // The posts to display.

    // First up a small sanity check. If there are no courses to check we can
    // return immediately, there is obviously nothing to search.
    if (empty($courses)) {
        return $return;
    }

    // A couple of quick setups.
    $isloggedin = isloggedin();
    $isguestuser = $isloggedin && isguestuser();
    $iscurrentuser = $isloggedin && $USER->id == $user->id;

    // Checkout whether or not the current user has capabilities over the requested
    // user and if so they have the capabilities required to view the requested
    // users content.
    $usercontext = context_user::instance($user->id, MUST_EXIST);
    $hascapsonuser = !$iscurrentuser && $DB->record_exists(
        'role_assignments', array('userid' => $USER->id, 'contextid' => $usercontext->id)
    );
    $hascapsonuser = $hascapsonuser && has_all_capabilities(array('moodle/user:viewdetails',
        'moodle/user:readuserposts'), $usercontext);

    // Before we actually search each course we need to check the user's access to the
    // course. If the user doesn't have the appropraite access then we either throw an
    // error if a particular course was requested or we just skip over the course.
    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course->id, MUST_EXIST);
        if ($iscurrentuser || $hascapsonuser) {
            // If it is the current user, or the current user has capabilities to the
            // requested user then all we need to do is check the requested users
            // current access to the course.
            // Note: There is no need to check group access or anything of the like
            // as either the current user is the requested user, or has granted
            // capabilities on the requested user. Either way they can see what the
            // requested user posted, although its VERY unlikely in the `parent` situation
            // that the current user will be able to view the posts in context.
            if (!is_viewing($coursecontext, $user) && !is_enrolled($coursecontext, $user)) {
                // Need to have full access to a course to see the rest of own info.
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'forum');
                }
                continue;
            }
        } else {
            // Check whether the current user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!can_access_course($course)) {
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'forum');
                }
                continue;
            }

            // Check whether the requested user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!is_enrolled($coursecontext, $user)) {
                if ($musthaveaccess) {
                    print_error('notenrolled', 'forum');
                }
                continue;
            }

            // If groups are in use and enforced throughout the course then make sure
            // we can meet in at least one course level group.
            // Note that we check if either the current user or the requested user have
            // the capability to access all groups. This is because with that capability
            // a user in group A could post in the group B forum. Grrrr.
            if (groups_get_course_groupmode($course) == SEPARATEGROUPS && $course->groupmodeforce
              && !has_capability('moodle/site:accessallgroups', $coursecontext)
                    && !has_capability('moodle/site:accessallgroups', $coursecontext, $user->id)) {
                // If it's the guest user too bad... the guest user cannot access groups.
                if (!$isloggedin or $isguestuser) {
                    // Do not use require_login() here because we might have already used require_login($course).
                    if ($musthaveaccess) {
                        redirect(get_login_url());
                    }
                    continue;
                }
                // Get the groups of the current user.
                $mygroups = array_keys(groups_get_all_groups($course->id, $USER->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Get the groups the requested user is a member of.
                $usergroups = array_keys(groups_get_all_groups($course->id, $user->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Check whether they are members of the same group. If they are great.
                $intersect = array_intersect($mygroups, $usergroups);
                if (empty($intersect)) {
                    // But they're not... if it was a specific course throw an error otherwise
                    // just skip this course so that it is not searched.
                    if ($musthaveaccess) {
                        print_error("groupnotamember", '', $CFG->wwwroot."/course/view.php?id=$course->id");
                    }
                    continue;
                }
            }
        }
        // Woo hoo we got this far which means the current user can search this
        // this course for the requested user. Although this is only the course accessibility
        // handling that is complete, the forum accessibility tests are yet to come.
        $return->courses[$course->id] = $course;
    }
    // No longer beed $courses array - lose it not it may be big.
    unset($courses);

    // Make sure that we have some courses to search.
    if (empty($return->courses)) {
        // If we don't have any courses to search then the reality is that the current
        // user doesn't have access to any courses is which the requested user has posted.
        // Although we do know at this point that the requested user has posts.
        if ($musthaveaccess) {
            print_error('permissiondenied');
        } else {
            return $return;
        }
    }

    // Next step: Collect all of the forums that we will want to search.
    // It is important to note that this step isn't actually about searching, it is
    // about determining which forums we can search by testing accessibility.
    $forums = forum_get_forums_user_posted_in($user, array_keys($return->courses), $discussionsonly, null, null, $tfid);

    // Will be used to build the where conditions for the search.
    $forumsearchwhere = array();
    // Will be used to store the where condition params for the search.
    $forumsearchparams = array();
    // Will record forums where the user can freely access everything.
    $forumsearchfullaccess = array();
    // DB caching friendly.
    $now = round(time(), -2);
    // For each course to search we want to find the forums the user has posted in
    // and providing the current user can access the forum create a search condition
    // for the forum to get the requested users posts.
    foreach ($return->courses as $course) {
        // Now we need to get the forums.
        $modinfo = get_fast_modinfo($course);
        if (empty($modinfo->instances['forum'])) {
            // Hmmm, no forums? well at least its easy... skip!
            continue;
        }
        // Iterate.
        foreach ($modinfo->get_instances_of('forum') as $forumid => $cm) {
            if (!$cm->uservisible or !isset($forums[$forumid])) {
                continue;
            }
            // Get the forum in question.
            $forum = $forums[$forumid];
            // This is needed for functionality later on in the forum code....
            $forum->cm = $cm;

            // Check that either the current user can view the forum, or that the
            // current user has capabilities over the requested user and the requested
            // user can view the discussion.
            if (!has_capability('mod/forum:viewdiscussion', $cm->context) && !($hascapsonuser
                    && has_capability('mod/forum:viewdiscussion', $cm->context, $user->id))) {
                continue;
            }

            // This will contain forum specific where clauses.
            $forumsearchselect = array();
            if (!$iscurrentuser && !$hascapsonuser) {
                // Make sure we check group access.
                if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS
                        and !has_capability('moodle/site:accessallgroups', $cm->context)) {
                    $groups = $modinfo->get_groups($cm->groupingid);
                    $groups[] = -1;
                    list($groupidsql, $groupidparams) = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'grps'.$forumid.'_');
                    $forumsearchparams = array_merge($forumsearchparams, $groupidparams);
                    $forumsearchselect[] = "d.groupid $groupidsql";
                }

                // Hidden timed discussions.
                if (!empty($CFG->forum_enabletimedposts) && !has_capability('mod/forum:viewhiddentimedposts', $cm->context)) {
                    $forumsearchselect[] = "(d.userid = :userid{$forumid} OR "
                    . "(d.timestart < :timestart{$forumid} AND (d.timeend = 0 OR d.timeend > :timeend{$forumid})))";
                    $forumsearchparams['userid'.$forumid] = $user->id;
                    $forumsearchparams['timestart'.$forumid] = $now;
                    $forumsearchparams['timeend'.$forumid] = $now;
                }

                // Qanda access.
                if ($forum->type == 'qanda' && !has_capability('mod/forum:viewqandawithoutposting', $cm->context)) {
                    // We need to check whether the user has posted in the qanda forum.
                    $discussionspostedin = forum_discussions_user_has_posted_in($forum->id, $user->id);
                    if (!empty($discussionspostedin)) {
                        // Holds discussion ids for the discussions the user is allowed to see in this forum.
                        $forumonlydiscussions = array();
                        foreach ($discussionspostedin as $d) {
                            $forumonlydiscussions[] = $d->id;
                        }
                        list($discussionidsql, $discussionidparams) = $DB->get_in_or_equal($forumonlydiscussions,
                                SQL_PARAMS_NAMED, 'qanda'.$forumid.'_');
                        $forumsearchparams = array_merge($forumsearchparams, $discussionidparams);
                        $forumsearchselect[] = "(d.id $discussionidsql OR p.parent = 0)";
                    } else {
                        $forumsearchselect[] = "p.parent = 0";
                    }

                }

                if (count($forumsearchselect) > 0) {
                    $forumsearchwhere[] = "(d.forum = :forum{$forumid} AND ".implode(" AND ", $forumsearchselect).")";
                    $forumsearchparams['forum'.$forumid] = $forumid;
                } else {
                    $forumsearchfullaccess[] = $forumid;
                }
            } else {
                // The current user/parent can see all of their own posts.
                $forumsearchfullaccess[] = $forumid;
            }
        }
    }

    // If we dont have any search conditions, and we don't have any forums where
    // the user has full access then we just return the default.
    if (empty($forumsearchwhere) && empty($forumsearchfullaccess)) {
        return $return;
    }

    // Prepare a where condition for the full access forums.
    if (count($forumsearchfullaccess) > 0) {
        list($fullidsql, $fullidparams) = $DB->get_in_or_equal($forumsearchfullaccess, SQL_PARAMS_NAMED, 'fula');
        $forumsearchparams = array_merge($forumsearchparams, $fullidparams);
        $forumsearchwhere[] = "(d.forum $fullidsql)";
    }

    // Prepare SQL to both count and search.
    $userfields = user_picture::fields('u', null, 'userid');
    $countsql = 'SELECT COUNT(*) ';
    $selectsql = 'SELECT p.*, d.forum, d.name AS discussionname, '.$userfields.' ';
    $wheresql = implode(" OR ", $forumsearchwhere);

    if ($discussionsonly) {
        if ($wheresql == '') {
            $wheresql = 'p.parent = 0';
        } else {
            $wheresql = 'p.parent = 0 AND ('.$wheresql.')';
        }
    }

    $sql = "FROM {forum_posts} p
            JOIN {forum_discussions} d ON d.id = p.discussion
            JOIN {user} u ON u.id = p.userid
           WHERE ($wheresql)
             AND p.userid = :userid ";
    $orderby = "ORDER BY p.modified DESC";
    $forumsearchparams['userid'] = $user->id;

    // Set the total number posts made by the requested user that the current user can see.
    $return->totalcount = $DB->count_records_sql($countsql.$sql, $forumsearchparams);
    return $return;
}