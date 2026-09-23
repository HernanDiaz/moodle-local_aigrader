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
 * Plugin metadata for AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_aigrader';     // Frankenstyle name.
// v1.0.28-beta: PPTX/ODT/ODP, automatic grading on submission, copy criteria,
// student names kept out of the prompt. See CHANGELOG.md.
$plugin->version   = 2026092402;           // YYYYMMDDXX format.
$plugin->requires  = 2024100700;           // Moodle 4.5.0 minimum.
$plugin->maturity  = MATURITY_BETA;        // Pilot-ready.
$plugin->release   = 'v1.0.28-beta';
