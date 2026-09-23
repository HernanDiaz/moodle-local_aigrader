<?php
// This file is part of Moodle - https://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Upgrade steps for AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Run AI Grader Pro upgrade steps for the given starting version.
 *
 * @param int $oldversion Previous version of the plugin currently installed.
 * @return bool
 */
function xmldb_local_aigrader_upgrade($oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // v0.1.2 — introduce the initial DB schema (3 tables).
    if ($oldversion < 2026051403) {
        $tables = [
            'local_aigrader_assign',
            'local_aigrader_submission',
            'local_aigrader_log',
        ];
        foreach ($tables as $tablename) {
            if (!$dbman->table_exists($tablename)) {
                $dbman->install_one_table_from_xmldb_file(
                    __DIR__ . '/install.xml',
                    $tablename
                );
            }
        }
        upgrade_plugin_savepoint(true, 2026051403, 'local', 'aigrader');
    }

    // v1.0.28 — per-assignment "grade automatically when the student submits".
    if ($oldversion < 2026092401) {
        $table = new xmldb_table('local_aigrader_assign');
        $field = new xmldb_field('autograde', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'language_override');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026092401, 'local', 'aigrader');
    }

    return true;
}
