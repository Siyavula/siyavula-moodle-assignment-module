<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Prints an instance of mod_siyavulaassignment.
 *
 * @package     mod_siyavulaassignment
 * @copyright   2025 Siyavula
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_once($CFG->dirroot . '/filter/siyavula/lib.php');

use filter_siyavula\renderables\assignment_activity_renderable;

// Course module id.
$id = optional_param('id', 0, PARAM_INT);
// Activity instance id.
$s = optional_param('s', 0, PARAM_INT);

if ($id) {
    $coursemodule = get_coursemodule_from_id('siyavulaassignment', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $coursemodule->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('siyavulaassignment', array('id' => $coursemodule->instance), '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('siyavulaassignment', array('id' => $s), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $coursemodule = get_coursemodule_from_instance('siyavulaassignment', $moduleinstance->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $coursemodule);

$modulecontext = context_module::instance($coursemodule->id);

$event = \mod_siyavulaassignment\event\course_module_viewed::create(array(
    'objectid' => $moduleinstance->id,
    'context' => $modulecontext,
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('siyavulaassignment', $moduleinstance);
$event->trigger();

$PAGE->set_url('/mod/siyavulaassignment/view.php', array('id' => $coursemodule->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

$PAGE->requires->css('/filter/siyavula/styles/general.css');

echo $OUTPUT->header();

// Check if user is guest or not logged in.
if (isguestuser() || !isloggedin()) {
    $loginurl = $CFG->wwwroot . '/login/index.php';
    echo '<div class="alert alert-info" role="alert">' .
         '<strong>Siyavula Assignment:</strong> Please <a href="' . $loginurl . '">log in</a> to access this content.' .
         '</div>';
    echo $OUTPUT->footer();
    exit;
}

// Assignment ID not configured.
if (empty($moduleinstance->assignmentid)) {
    echo \core\notification::error(get_string('assignmentidmissing', 'mod_siyavulaassignment'), false);
    echo $OUTPUT->footer();
    exit;
}

$clientip = $_SERVER['REMOTE_ADDR'];
$siyavulaconfig = get_config('filter_siyavula');
$baseurl = $siyavulaconfig->url_base;
$token = siyavula_get_user_token($siyavulaconfig, $clientip);
$usertoken = siyavula_get_external_user_token($siyavulaconfig, $clientip, $token);

$activitytype = 'assignment';

// Current version is Moodle 4.0 or higher use the event types. Otherwise use the older versions.
if ($CFG->version >= 2022041912) {
    $PAGE->requires->js_call_amd('filter_siyavula/initmathjax', 'init', ['issupported' => $CFG->version <= 2025040100]);
} else {
    $PAGE->requires->js_call_amd('filter_siyavula/initmathjax-backward', 'init');
}

$renderer = $PAGE->get_renderer('filter_siyavula');

$activityrenderable = new assignment_activity_renderable();
$activityrenderable->activitytype = $activitytype;
$activityrenderable->assignmentid = $moduleinstance->assignmentid;
$activityrenderable->uniqueid = uniqid('siyavula-activity-');

$config = new \stdClass();
$config->wwwroot = $CFG->wwwroot;
$config->baseurl = $baseurl;
$config->token = $token;
$config->usertoken = $usertoken->token;
$config->cmid = $coursemodule->id;

echo $renderer->render_assignment_activity($activityrenderable);
echo $renderer->render_assets([$activityrenderable], $config);

echo $OUTPUT->footer();
