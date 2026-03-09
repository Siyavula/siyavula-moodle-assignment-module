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
 * The main mod_siyavulaassignment configuration form.
 *
 * @package     mod_siyavulaassignment
 * @copyright   2025 Siyavula
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_siyavulaassignment_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // General fieldset.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Activity name.
        $mform->addElement('text', 'name', get_string('siyavulaassignmentname', 'mod_siyavulaassignment'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'siyavulaassignmentname', 'mod_siyavulaassignment');

        // Standard intro.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        // Assignment ID.
        $mform->addElement('text', 'assignmentid', get_string('assignmentid', 'mod_siyavulaassignment'));
        $mform->setType('assignmentid', PARAM_INT);
        $mform->addRule('assignmentid', null, 'required', null, 'client');
        $mform->addHelpButton('assignmentid', 'assignmentid', 'mod_siyavulaassignment');

        // Grade pass.
        $mform->addElement('header', 'title_select_grade', get_string('grade', 'mod_siyavulaassignment'));
        $mform->addElement('text', 'gradepass', get_string('gradepass', 'grades'));
        $mform->addHelpButton('gradepass', 'gradepass', 'grades');
        $mform->setDefault('gradepass', '');
        $mform->setType('gradepass', PARAM_RAW);

        // Standard elements and buttons.
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['assignmentid']) || intval($data['assignmentid']) <= 0) {
            $errors['assignmentid'] = get_string('assignmentidnotvalid', 'mod_siyavulaassignment');
        }

        return $errors;
    }
}
