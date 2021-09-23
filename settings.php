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
 * EdSembli Enrollment plugin settings
 *
 * @package    enrol_edsembli
 * @author     Tim Martinez <tim.martinez@ignitecentre.ca>
 * @copyright  2021 Tim Martinez <tim.martinez@ignitecentre.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $roles = get_default_enrol_roles(context_system::instance());
    
    //What is our school number?
    $title = get_string('config_schoolnum', 'enrol_edsembli');
    $desc = get_string('config_schoolnum_desc', 'enrol_edsembli');
    $settings->add(new admin_setting_configtext('enrol_edsembli/schoolnum', $title, $desc, 0, PARAM_INT));
    
    //What role to we assign to the primary teacher in courses
    $title = get_string('config_teacher_role', 'enrol_edsembli');
    $desc = get_string('config_teacher_role_desc', 'enrol_edsembli');
    $settings->add(new admin_setting_configselect('enrol_edsembli/teacher_role', $title, $desc, 0, $roles));
    
    //What role to we assign to the additional teacher in courses
    $title = get_string('config_additional_teacher_role', 'enrol_edsembli');
    $desc = get_string('config_additional_teacher_role_desc', 'enrol_edsembli');
    $settings->add(new admin_setting_configselect('enrol_edsembli/additional_teacher_role', $title, $desc, 0, $roles));
    
    //What role to we assign to the students in courses
    $title = get_string('config_student_role', 'enrol_edsembli');
    $desc = get_string('config_student_role_desc', 'enrol_edsembli');
    $settings->add(new admin_setting_configselect('enrol_edsembli/student_role', $title, $desc, 0, $roles));
}