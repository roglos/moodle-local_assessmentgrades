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
 * A scheduled task for scripted database integrations.
 *
 * @package    local_assessmentgrades - template
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_assessmentgrades\task;
use stdClass;

defined('MOODLE_INTERNAL') || die;

/**
 * A scheduled task for scripted external database integrations.
 *
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assessmentgrades extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'local_assessmentgrades');
    }

    /**
     * Run sync.
     */
    public function execute() {

        global $CFG, $DB;
        require_once("$CFG->libdir/gradelib.php");

        // Get grade letters.
        $gradeletters = array();
        $gradeletters = $DB->get_records_menu('grade_letters', array(), 'letter', 'letter, lowerboundary');

        // Check connection and label Db/Table in cron output for debugging if required.
        if (!$this->get_config('dbtype')) {
            echo 'Database not defined.<br>';
            return 0;
        } else {
            echo 'Database: ' . $this->get_config('dbtype') . '<br>';
        }
        if (!$this->get_config('remotegradestable')) {
            echo 'Table not defined.<br>';
            return 0;
        } else {
            echo 'Table: ' . $this->get_config('remotegradestable') . '<br>';
        }
        echo 'Starting connection...<br>';

        // Report connection error if occurs.
        if (!$extdb = $this->db_init()) {
            echo 'Error while communicating with external database <br>';
            return 1;
        }

        // Functions and code to pass grades goes here
        $stuassess = array(); // Maintain copy as per Integrations Db for writing back.
        $stuassessinternal = array(); // Processing copy to be able to add additional fields.
        // Get external assessments table name.
        $tablestuassm = $this->get_config('remotegradestable');
        // Read assessment data from external table.
        /********************************************************
         * ARRAY                                                *
         *     id                                               *
         *     student_code                                     *
         *     assessment_idcode                                *
         *     student_ext_duedate                              *
         *     student_ext_duetime                              *
         *     student_fbdue_date                               *
         *     student_fbdue_time                               *
         *     received_date                                    *
         *     received_time                                    *
         *     received_flag                                    *
         *     actual_mark                                      *
         *     actual_grade                                     *
         *     process_flag                                     *
         *     student_fbset_date                               *
         *     student_fbset_time                               *
         ********************************************************/
         echo 'Fetching '.$tablestuassm.'<br>';
        $sql = $this->db_get_sql_like($tablestuassm, array('assessment_idcode' => '2018/19'), array(), true);
        if ($rs = $extdb->Execute($sql)) {
            if (!$rs->EOF) {
                while ($fields = $rs->FetchRow()) {
                    $fields = array_change_key_case($fields, CASE_LOWER);
                    $fields = $this->db_decode($fields);
                    $stuassess[] = $fields;
                }
            }
            $rs->Close();
        } else {
            // Report error if required.
            $extdb->Close();
            echo 'Error reading data from the external course table<br>';
            return 4;
        }

        /* Create keyed array of student data (grades etc) per student~assignment
         * ---------------------------------------------------------------------- */
         echo 'Creating keyed array<br>';
        foreach ($stuassess as $sa) {

            $idnumber = 's'.$sa['student_code'];
            $key = $idnumber.'~'.$sa['assessment_idcode'];
            echo '<br>'.$key;

            $stuassessinternal[$key]['key'] = $key;
            $stuassessinternal[$key]['username'] = 's'.$sa['student_code']; // Username.
            echo ': username = '.$stuassessinternal[$key]['username'];

            if ($DB->get_field('user', 'id',
                array('username' => $stuassessinternal[$key]['username']))) {
                    $stuassessinternal[$key]['uid'] = $DB->get_field('user', 'id',
                        array('username' => $stuassessinternal[$key]['username'])); // User id.
            } else {
                $stuassessinternal[$key]['uid'] = '';
            }
            echo ': idnumber = '.$stuassessinternal[$key]['uid'];

            $stuassessinternal[$key]['lc'] = $sa['assessment_idcode']; // Assessment linkcode.
            echo ': assessment link = '.$stuassessinternal[$key]['lc'];

            if ($DB->get_field('course_modules', 'course',
                array('idnumber' => $stuassessinternal[$key]['lc']))) {
                    $stuassessinternal[$key]['crs'] = $DB->get_field('course_modules', 'course',
                        array('idnumber' => $stuassessinternal[$key]['lc'])); // Course id.
            } else {
                $stuassessinternal[$key]['crs'] = '';
            }
            echo ': course id = '.$stuassessinternal[$key]['crs'];

            if ($DB->get_field('course_modules', 'instance',
                array('idnumber' => $stuassessinternal[$key]['lc']))) {
                    $stuassessinternal[$key]['aid'] = $DB->get_field('course_modules', 'instance',
                        array('idnumber' => $stuassessinternal[$key]['lc'])); // Assignment id.
            } else {
                $stuassessinternal[$key]['aid'] = '';
            }
            echo ': assignment id = '.$stuassessinternal[$key]['aid'];

            $stuassessinternal[$key]['mod'] = $DB->get_field('course_modules', 'module',
                array('idnumber' => $stuassessinternal[$key]['lc'])); // Module id.
            $stuassessinternal[$key]['modname'] = $DB->get_field('modules', 'name',
                array('id' => $stuassessinternal[$key]['mod'])); // Module name.
            echo ': module id = '.$stuassessinternal[$key]['mod'].':'.$stuassessinternal[$key]['modname'];

            $stuassessinternal[$key]['giid'] = $DB->get_field('grade_items', 'id',
                array('iteminstance' => $stuassessinternal[$key]['aid'], 'itemmodule' => $stuassessinternal[$key]['modname'])); // Grade item instance.
            echo ': grade item = '.$stuassessinternal[$key]['giid'];

            // Get submission received date & time.
            if ($DB->record_exists('assign_submission',
            array('assignment' => $stuassessinternal[$key]['aid'],
                'userid' => $stuassessinternal[$key]['uid'], 'status' => 'submitted'))) {
                $stuassessinternal[$key]['received'] = $DB->get_field('assign_submission', 'timemodified',
                    array('assignment' => $stuassessinternal[$key]['aid'], 'userid' => $stuassessinternal[$key]['uid']));
            } else if ($DB->record_exists('quiz_attempts', array('quiz' => $stuassessinternal[$key]['aid'],
                'userid' => $stuassessinternal[$key]['uid'], 'state' => 'finished'))) {
                $stuassessinternal[$key]['received'] = $DB->get_field('quiz_attempts', 'timefinish',
                    array('quiz' => $stuassessinternal[$key]['aid'], 'userid' => $stuassessinternal[$key]['uid']));
            } else {
                $stuassessinternal[$key]['received'] = '';
            }
            if (!is_null($stuassessinternal[$key]['received']) && $stuassessinternal[$key]['received'] !== '') {
                $stuassessinternal[$key]['received_date'] = date('Y-m-d', $stuassessinternal[$key]['received']);
                $stuassessinternal[$key]['received_time'] = date('H:i:s', $stuassessinternal[$key]['received']);
            } else {
                $stuassessinternal[$key]['received_date'] = '';
                $stuassessinternal[$key]['received_time'] = '';
            }
            echo '<br>Submission received: '.$stuassessinternal[$key]['received'].': '.
                $stuassessinternal[$key]['received_date'].': '.$stuassessinternal[$key]['received_time'];

            // Fetch alphanumeric grade.
            $fullscale = array(); // Clear any prior value.
            $graderaw = $grademax = null;
            if ($DB->record_exists('grade_grades',
                array('itemid' => $stuassessinternal[$key]['giid'], 'userid' => $stuassessinternal[$key]['uid']))
                && !is_null($DB->get_field('grade_grades', 'finalgrade',
                    array('itemid' => $stuassessinternal[$key]['giid'], 'userid' => $stuassessinternal[$key]['uid'])))) {
                // Get final grade and ensure %age.
                $graderaw = $DB->get_field('grade_grades', 'finalgrade',
                    array('itemid' => $stuassessinternal[$key]['giid'], 'userid' => $stuassessinternal[$key]['uid']));
                $grademax = $DB->get_field('grade_grades', 'rawgrademax',
                    array('itemid' => $stuassessinternal[$key]['giid'], 'userid' => $stuassessinternal[$key]['uid']));
                $stuassessinternal[$key]['gradenum'] = $graderaw / $grademax * 100;
                // Get which scale.
                $stuassessinternal[$key]['gradescale'] = $DB->get_field('grade_grades', 'rawscaleid',
                    array('itemid' => $stuassessinternal[$key]['giid'], 'userid' => $stuassessinternal[$key]['uid']));
                echo ': grade scale = '.$stuassessinternal[$key]['gradescale'];
                if (!is_null($stuassessinternal[$key]['gradescale']) && $stuassessinternal[$key]['gradescale'] !== 0) {
                    $fullscale = $DB->get_record('scale', array('id' => $stuassessinternal[$key]['gradescale']), 'scale');
                    $scale = explode(',', $fullscale->scale);
                    $stuassessinternal[$key]['gradeletter'] = $scale[$stuassessinternal[$key]['gradenum'] - 1];
                    $stuassessinternal[$key]['gradenum'] = null; // If a scale grade is set, remove numeric value.
                } else {
                    $stuassessinternal[$key]['gradeletter'] = '';
                    foreach ($gradeletters as $l => $g) {
                        echo $l.' = '.$gradeletters[$l].'  ';
                        if ($stuassessinternal[$key]['gradeletter'] == ''
                            && $stuassessinternal[$key]['gradenum'] >= $gradeletters[$l]) {
                            $stuassessinternal[$key]['gradeletter'] = $l;
                        }
                    }
                }
            } else {
                $stuassessinternal[$key]['gradenum'] = null;
                $stuassessinternal[$key]['gradeletter'] = null;
            }
            // Get assessment flags. eg. SB.
            $asflag = '';
            if (strlen($stuassessinternal[$key]['aid']) > 0 && strlen($stuassessinternal[$key]['uid']) > 0) {
                $afsql = "SELECT c.content FROM {comments} c
                        JOIN {assign_submission} sub ON sub.id = c.itemid
                        WHERE sub.assignment = ".$stuassessinternal[$key]['aid']." AND sub.userid = ".
                        $stuassessinternal[$key]['uid']."
                        AND c.commentarea = 'submission_assessmentflags'";
                $asflagresult = $DB->get_records_sql($afsql);
                foreach ($asflagresult as $af) {
                    $asflag = $af->content;
                }
                echo 'asflag: '. $asflag.' ';
                if ($asflag != '') {
                    $stuassessinternal[$key]['gradeletter'] = $asflag;
                }
            }

            // Get feedback given date.
            if ($DB->record_exists('grade_grades',
                array('itemid' => $stuassessinternal[$key]['giid'], 'userid' => $stuassessinternal[$key]['uid']))) {
                $stuassessinternal[$key]['fbgiven'] = $DB->get_field('grade_grades', 'timemodified',
                    array('itemid' => $stuassessinternal[$key]['giid'], 'userid' => $stuassessinternal[$key]['uid']));
            } else {
                $stuassessinternal[$key]['fbgiven'] = '';
            }
            if (!is_null($stuassessinternal[$key]['fbgiven']) && $stuassessinternal[$key]['fbgiven'] !== '') {
                $stuassessinternal[$key]['fbgiven_date'] = date('Y-m-d', $stuassessinternal[$key]['fbgiven']);
                $stuassessinternal[$key]['fbgiven_time'] = date('H:i:s', $stuassessinternal[$key]['fbgiven']);
            } else {
                $stuassessinternal[$key]['fbgiven_date'] = '';
                $stuassessinternal[$key]['fbgiven_time'] = '';
            }
            echo '<br>Grade: '.$stuassessinternal[$key]['gradeletter'].$stuassessinternal[$key]['gradenum'].': '.
                $stuassessinternal[$key]['fbgiven'].': '.$stuassessinternal[$key]['fbgiven_date'].
                ': '.$stuassessinternal[$key]['fbgiven_time'].'<br>';

            // Write values to external database - but only if they exist.
            // Need to add code to this to only write them if they have changed from what's already there.
            $studentcode = mb_substr($stuassessinternal[$key]['username'], 1);
            $sql = "UPDATE " . $tablestuassm . " SET student_code = " . $studentcode .", "; // Prevents error if nothing is set.
            $changeflag = 0;
            if ($stuassessinternal[$key]['received_date'] != '' &&
                $stuassessinternal[$key]['received_date'] !== $sa['received_date']) {
                $sql .= "received_date = '" . $stuassessinternal[$key]['received_date'] . "', ";
                $sql .= "received_flag = 1, ";
                $changeflag = 1;
            }
            if ($stuassessinternal[$key]['received_time'] != '' &&
                $stuassessinternal[$key]['received_time'] !== $sa['received_time']) {
                $sql .= "received_time = '" . $stuassessinternal[$key]['received_time'] . "', ";
                $changeflag = 1;
            }
            if ($stuassessinternal[$key]['gradenum'] != '' &&
                $stuassessinternal[$key]['gradenum'] !== $sa['actual_mark']) {
                $sql .= "actual_mark = '" . $stuassessinternal[$key]['gradenum'] . "', ";
                $changeflag = 1;
            }
            if ($stuassessinternal[$key]['gradeletter'] != '' &&
                $stuassessinternal[$key]['gradeletter'] !== $sa['actual_grade']) {
                $sql .= "actual_grade = '" . $stuassessinternal[$key]['gradeletter'] . "', ";
                $changeflag = 1;
            }
            if ($stuassessinternal[$key]['fbgiven_date'] != '' &&
                $stuassessinternal[$key]['fbgiven_date'] !== $sa['student_fbset_date']) {
                $sql .= "student_fbset_date = '" . $stuassessinternal[$key]['fbgiven_date'] . "', ";
                $changeflag = 1;
            }
            if ($stuassessinternal[$key]['fbgiven_time'] != '' &&
                $stuassessinternal[$key]['fbgiven_time'] !== $sa['student_fbset_time']) {
                $sql .= "student_fbset_time = '" . $stuassessinternal[$key]['fbgiven_time'] . "', ";
                $changeflag = 1;
            }
            $sql .= "assessment_changebymoodle = " . $changeflag ." WHERE ";
            $sql .= "assessment_idcode = '" . $stuassessinternal[$key]['lc'] . "' AND
                        student_code = '" . $studentcode . "';";
            if ($changeflag > 0) {
                echo $sql;
                $extdb->Execute($sql);
            }
        }

        // Free memory.
        $extdb->Close();
    }

    /* Db functions cloned from enrol/db plugin.
     * ========================================= */

    /**
     * Tries to make connection to the external database.
     *
     * @return null|ADONewConnection
     */
    public function db_init() {
        global $CFG;

        require_once($CFG->libdir.'/adodb/adodb.inc.php');

        // Connect to the external database (forcing new connection).
        $extdb = ADONewConnection($this->get_config('dbtype'));
        if ($this->get_config('debugdb')) {
            $extdb->debug = true;
            ob_start(); // Start output buffer to allow later use of the page headers.
        }

        // The dbtype my contain the new connection URL, so make sure we are not connected yet.
        if (!$extdb->IsConnected()) {
            $result = $extdb->Connect($this->get_config('dbhost'),
                $this->get_config('dbuser'),
                $this->get_config('dbpass'),
                $this->get_config('dbname'), true);
            if (!$result) {
                return null;
            }
        }

        $extdb->SetFetchMode(ADODB_FETCH_ASSOC);
        if ($this->get_config('dbsetupsql')) {
            $extdb->Execute($this->get_config('dbsetupsql'));
        }
        return $extdb;
    }

    public function db_addslashes($text) {
        // Use custom made function for now - it is better to not rely on adodb or php defaults.
        if ($this->get_config('dbsybasequoting')) {
            $text = str_replace('\\', '\\\\', $text);
            $text = str_replace(array('\'', '"', "\0"), array('\\\'', '\\"', '\\0'), $text);
        } else {
            $text = str_replace("'", "''", $text);
        }
        return $text;
    }

    public function db_encode($text) {
        $dbenc = $this->get_config('dbencoding');
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach ($text as $k => $value) {
                $text[$k] = $this->db_encode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, 'utf-8', $dbenc);
        }
    }

    public function db_decode($text) {
        $dbenc = $this->get_config('dbencoding');
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach ($text as $k => $value) {
                $text[$k] = $this->db_decode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, $dbenc, 'utf-8');
        }
    }

    public function db_get_sql($table, array $conditions, array $fields, $distinct = false, $sort = "") {
        $fields = $fields ? implode(',', $fields) : "*";
        $where = array();
        if ($conditions) {
            foreach ($conditions as $key => $value) {
                $value = $this->db_encode($this->db_addslashes($value));

                $where[] = "$key = '$value'";
            }
        }
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";
        $sort = $sort ? "ORDER BY $sort" : "";
        $distinct = $distinct ? "DISTINCT" : "";
        $sql = "SELECT $distinct $fields
                  FROM $table
                 $where
                  $sort";
        return $sql;
    }

    public function db_get_sql_like($table2, array $conditions, array $fields, $distinct = false, $sort = "") {
        $fields = $fields ? implode(',', $fields) : "*";
        $where = array();
        if ($conditions) {
            foreach ($conditions as $key => $value) {
                $value = $this->db_encode($this->db_addslashes($value));

                $where[] = "$key LIKE '%$value%'";
            }
        }
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";
        $sort = $sort ? "ORDER BY $sort" : "";
        $distinct = $distinct ? "DISTINCT" : "";
        $sql2 = "SELECT $distinct $fields
                  FROM $table2
                 $where
                  $sort";
        return $sql2;
    }


    /**
     * Returns plugin config value
     * @param  string $name
     * @param  string $default value if config does not exist yet
     * @return string value or default
     */
    public function get_config($name, $default = null) {
        $this->load_config();
        return isset($this->config->$name) ? $this->config->$name : $default;
    }

    /**
     * Sets plugin config value
     * @param  string $name name of config
     * @param  string $value string config value, null means delete
     * @return string value
     */
    public function set_config($name, $value) {
        $pluginname = $this->get_name();
        $this->load_config();
        if ($value === null) {
            unset($this->config->$name);
        } else {
            $this->config->$name = $value;
        }
        set_config($name, $value, "local_$pluginname");
    }

    /**
     * Makes sure config is loaded and cached.
     * @return void
     */
    public function load_config() {
        if (!isset($this->config)) {
            $name = $this->get_name();
            $this->config = get_config("local_$name");
        }
    }
}

