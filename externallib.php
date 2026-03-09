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
 * External web service functions for mod_siyavulaassignment.
 *
 * @package     mod_siyavulaassignment
 * @copyright   2025 Siyavula
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once(__DIR__ . '/lib.php');

class mod_siyavulaassignment_external extends external_api {

    /**
     * Parameter definition for update_grade.
     */
    public static function update_grade_parameters() {
        return new external_function_parameters([
            'cmid'      => new external_value(PARAM_INT,   'Course module ID of the siyavulaassignment activity'),
            'score'     => new external_value(PARAM_FLOAT, 'Assignment score (0-100)'),
            'completed' => new external_value(PARAM_BOOL,  'Whether the assignment is completed'),
        ]);
    }

    /**
     * Write assignment score to the Moodle gradebook.
     *
     * Called from the browser via core/ajax when the learner completes an assignment.
     *
     * @param int   $cmid      Course module ID.
     * @param float $score     Assignment score (0-100).
     * @param bool  $completed Whether the assignment is completed.
     * @return array ['success' => bool]
     */
    public static function update_grade($cmid, $score, $completed) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(
            self::update_grade_parameters(),
            ['cmid' => $cmid, 'score' => $score, 'completed' => $completed]
        );

        $cm = get_coursemodule_from_id('siyavulaassignment', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $moduleinstance = $DB->get_record('siyavulaassignment', ['id' => $cm->instance], '*', MUST_EXIST);

        // Clamp score to valid range.
        $score = min(100.0, max(0.0, (float) $params['score']));

        if (!function_exists('grade_update')) {
            require_once($CFG->libdir . '/gradelib.php');
        }

        $grades = [
            $USER->id => (object) ['userid' => $USER->id, 'rawgrade' => $score],
        ];

        siyavulaassignment_grade_item_update($moduleinstance, $grades);

        // Update completion if the assignment is marked as completed.
        if ($params['completed']) {
            siyavulaassignment_set_completion($moduleinstance, $USER->id, $score);
        }

        return ['success' => true];
    }

    /**
     * Return definition for update_grade.
     */
    public static function update_grade_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the grade update succeeded'),
        ]);
    }
}
