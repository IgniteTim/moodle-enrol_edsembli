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
 * <<description>>
 *
 * @package    enrol_edsembli
 * @author     Tim Martinez <tim.martinez@ignitecentre.ca>
 * @copyright  2021 Tim Martinez <tim.martinez@ignitecentre.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php'); // global moodle config file.
require_once($CFG->libdir . '/clilib.php');

/** @var enrol_ldap_plugin $enrol */
$enrol = enrol_get_plugin('edsembli');

$trace = new \text_progress_trace();

// Update enrolments -- these handlers should autocreate courses if required.
$enrol->sync_enrolments($trace);

exit(0);
