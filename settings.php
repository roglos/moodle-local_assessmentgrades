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
 * assessmentgrades local plugin settings and presets.
 *
 * @package    local_assessmentgrades
 * @copyright  2017 RMOelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This is designed as a template to enable creation of local plugins reading from
 * an external assessmentgrades table. The external assessmentgrades and table are set in a settings
 * page for each plugin create - ie can each point at different Db/tables etc if
 * required. By default this template reads all fields in the set table (SELECT * FROM).
 **/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_assessmentgrades',
        get_string('pluginname', 'local_assessmentgrades'));
    $ADMIN->add('localplugins', $settings);

        // Headings.
    $settings->add(new admin_setting_heading('local_assessmentgrades_settings', '',
        get_string('pluginname_desc', 'local_assessmentgrades')));
    $settings->add(new admin_setting_heading('local_assessmentgrades_exdbheader',
        get_string('settingsheaderdb', 'local_assessmentgrades'), ''));

    // Db Connection Settings.
    // -----------------------

    // Db type.
    $options = array('',
        "access",
        "ado_access",
        "ado",
        "ado_mssql",
        "borland_ibase",
        "csv",
        "db2",
        "fbsql",
        "firebird",
        "ibase",
        "informix72",
        "informix",
        "mssql",
        "mssql_n",
        "mssqlnative",
        "mysql",
        "mysqli",
        "mysqlt",
        "oci805",
        "oci8",
        "oci8po",
        "odbc",
        "odbc_mssql",
        "odbc_oracle",
        "oracle",
        "pdo",
        "postgres64",
        "postgres7",
        "postgres",
        "proxy",
        "sqlanywhere",
        "sybase",
        "vfp");
    $options = array_combine($options, $options);
    $settings->add(new admin_setting_configselect('local_assessmentgrades/dbtype',
        get_string('dbtype', 'local_assessmentgrades'),
        get_string('dbtype_desc', 'local_assessmentgrades'), '', $options));

    // Db host.
    $settings->add(new admin_setting_configtext('local_assessmentgrades/dbhost',
        get_string('dbhost', 'local_assessmentgrades'),
        get_string('dbhost_desc', 'local_assessmentgrades'), 'localhost'));

    // Db User.
    $settings->add(new admin_setting_configtext('local_assessmentgrades/dbuser',
        get_string('dbuser', 'local_assessmentgrades'), '', ''));

    // Db Password.
    $settings->add(new admin_setting_configpasswordunmask('local_assessmentgrades/dbpass',
        get_string('dbpass', 'local_assessmentgrades'), '', ''));

    // Db Name.
    $settings->add(new admin_setting_configtext('local_assessmentgrades/dbname',
        get_string('dbname', 'local_assessmentgrades'),
        get_string('dbname_desc', 'local_assessmentgrades'), ''));

    // Db Encoding.
    $settings->add(new admin_setting_configtext('local_assessmentgrades/dbencoding',
        get_string('dbencoding', 'local_assessmentgrades'), '', 'utf-8'));

    // Db Setup.
    $settings->add(new admin_setting_configtext('local_assessmentgrades/dbsetupsql',
        get_string('dbsetupsql', 'local_assessmentgrades'),
        get_string('dbsetupsql_desc', 'local_assessmentgrades'), ''));

    // Db Sybase.
    $settings->add(new admin_setting_configcheckbox('local_assessmentgrades/dbsybasequoting',
        get_string('dbsybasequoting', 'local_assessmentgrades'),
        get_string('dbsybasequoting_desc', 'local_assessmentgrades'), 0));

    // AODBC Debug.
    $settings->add(new admin_setting_configcheckbox('local_assessmentgrades/debugdb',
        get_string('debugdb', 'local_assessmentgrades'),
        get_string('debugdb_desc', 'local_assessmentgrades'), 0));

    // Table Settings.
    $settings->add(new admin_setting_heading('local_assessmentgrades_remoteheader',
        get_string('settingsheaderremote', 'local_assessmentgrades'), ''));

    // Table name - assignment defaults.
//    $settings->add(new admin_setting_configtext('local_assessmentgrades/remotetable',
//        get_string('remotetable', 'local_assessmentgrades'),
//        get_string('remotetable_desc', 'local_assessmentgrades'), ''));
    // Table name - individual settings: extensions, grades etc.
    $settings->add(new admin_setting_configtext('local_assessmentgrades/remotegradestable',
        get_string('remotegradestable', 'local_assessmentgrades'),
        get_string('remotegradestable_desc', 'local_assessmentgrades'), ''));

    // Fields to use.
//    $settings->add(new admin_setting_configtext('local_assessmentgrades/linkcode',
//        get_string('linkcode', 'local_assessmentgrades'),
//        get_string('linkcode', 'local_assessmentgrades'), ''));
//    $settings->add(new admin_setting_configtext('local_assessmentgrades/duedate',
//        get_string('duedate', 'local_assessmentgrades'),
//        get_string('duedate', 'local_assessmentgrades'), ''));
//    $settings->add(new admin_setting_configtext('local_assessmentgrades/feedbackdue',
//        get_string('feedbackdue', 'local_assessmentgrades'),
//        get_string('feedbackdue', 'local_assessmentgrades'), ''));

}
