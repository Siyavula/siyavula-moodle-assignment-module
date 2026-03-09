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
 * Library of interface functions and constants.
 *
 * @package     mod_siyavulaassignment
 * @copyright   2026 Siyavula
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/filter/siyavula/lib.php');

/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
function siyavulaassignment_supports($feature) {
    switch ($feature) {
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the mod_siyavulaassignment into the database.
 *
 * @param object $moduleinstance An object from the form.
 * @param mod_siyavulaassignment_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function siyavulaassignment_add_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timecreated = time();
    $moduleinstance->timemodified = time();

    $id = $DB->insert_record('siyavulaassignment', $moduleinstance);
    $moduleinstance->id = $id;

    siyavulaassignment_grade_item_update($moduleinstance);

    return $id;
}

/**
 * Updates an instance of the mod_siyavulaassignment in the database.
 *
 * @param object $moduleinstance An object from the form in mod_form.php.
 * @param mod_siyavulaassignment_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function siyavulaassignment_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    $result = $DB->update_record('siyavulaassignment', $moduleinstance);

    siyavulaassignment_grade_item_update($moduleinstance);

    return $result;
}

/**
 * Removes an instance of the mod_siyavulaassignment from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function siyavulaassignment_delete_instance($id) {
    global $DB;

    $exists = $DB->get_record('siyavulaassignment', array('id' => $id));
    if (!$exists) {
        return false;
    }

    siyavulaassignment_grade_item_delete($exists);
    $DB->delete_records('siyavulaassignment', array('id' => $id));

    return true;
}

/**
 * Is a given scale used by the instance of mod_siyavulaassignment?
 *
 * @param int $moduleinstanceid ID of an instance of this module.
 * @param int $scaleid ID of the scale.
 * @return bool Always false — this module uses value grades, not scales.
 */
function siyavulaassignment_scale_used($moduleinstanceid, $scaleid) {
    return false;
}

/**
 * Checks if scale is being used by any instance of mod_siyavulaassignment.
 *
 * @param int $scaleid ID of the scale.
 * @return bool Always false — this module uses value grades, not scales.
 */
function siyavulaassignment_scale_used_anywhere($scaleid) {
    return false;
}

/**
 * Creates or updates grade item for the given mod_siyavulaassignment instance.
 *
 * @param stdClass $moduleinstance Instance object.
 * @param mixed $grades Optional grades to write, or 'reset'.
 * @return int grade_update() return code.
 */
function siyavulaassignment_grade_item_update($moduleinstance, $grades = null) {
    global $CFG;
    if (!function_exists('grade_update')) {
        require_once($CFG->libdir . '/gradelib.php');
    }

    $params = [
        'itemname' => $moduleinstance->name,
        'idnumber' => $moduleinstance->id,
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax'  => 100,
        'grademin'  => 0,
    ];

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/siyavulaassignment', $moduleinstance->course, 'mod', 'siyavulaassignment',
                        $moduleinstance->id, 0, $grades, $params);
}

/**
 * Delete grade item for given mod_siyavulaassignment instance.
 *
 * @param stdClass $moduleinstance Instance object.
 * @return int grade_update() return code.
 */
function siyavulaassignment_grade_item_delete($moduleinstance) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/siyavulaassignment', $moduleinstance->course, 'mod', 'siyavulaassignment',
                        $moduleinstance->id, 0, null, ['deleted' => 1]);
}

/**
 * Update mod_siyavulaassignment grades in the gradebook.
 *
 * Needed by {@see grade_update_mod_grades()}.
 *
 * @param stdClass $moduleinstance Instance object.
 * @param int $userid Update grade of specific user only, 0 means all participants.
 */
function siyavulaassignment_update_grades($moduleinstance, $userid = 0) {
    // Grades are written directly via the AJAX service; nothing to pull from an external source.
    siyavulaassignment_grade_item_update($moduleinstance);
}

/**
 * Sets activity completion state based on score and gradepass.
 *
 * @param stdClass $moduleinstance Instance object.
 * @param int $userid User ID.
 * @param float $score The score achieved (0-100).
 */
function siyavulaassignment_set_completion($moduleinstance, $userid, $score = 0) {
    $course = new stdClass();
    $course->id = $moduleinstance->course;
    $completion = new completion_info($course);

    if (!$completion->is_enabled()) {
        return;
    }

    $cm = get_coursemodule_from_instance('siyavulaassignment', $moduleinstance->id, $moduleinstance->course);
    if (empty($cm) || !$completion->is_enabled($cm)) {
        return;
    }

    if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
        if ($score > 0) {
            if ($score >= $moduleinstance->gradepass) {
                $completion->update_state($cm, COMPLETION_COMPLETE_PASS, $userid);
            } else {
                $completion->update_state($cm, COMPLETION_COMPLETE_FAIL, $userid);
            }
        } else {
            $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
        }
    }
}
