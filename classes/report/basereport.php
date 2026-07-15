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
 * Report Classes.
 *
 * @package    mod_englishcentral
 * @copyright  Poodll
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_englishcentral\report;

use mod_englishcentral\constants;

/**
 * Classes for Reports
 *
 *    The important functions are:
 *  process_raw_data : turns log data for one thing (e.g question attempt) into one row
 * fetch_formatted_fields: uses data prepared in process_raw_data to make each field in fields full of formatted data
 * The allusers report is the simplest example
 *
 * @package    mod_englishcentral
 * @copyright  Poodll
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class basereport {
    /** @var string The report identifier. */
    protected $report = "";
    /** @var array The report heading fields. */
    protected $head = [];
    /** @var array|null The raw data for the report. */
    protected $rawdata = null;
    /** @var array The list of fields in the report. */
    protected $fields = [];
    /** @var array A cache of database records keyed by table and row id. */
    protected $dbcache = [];
    /** @var object The course module. */
    protected $cm;
    /** @var object The module instance. */
    protected $mod;
    /** @var \context The module context. */
    protected $context;

    /**
     * Process the raw log data into report rows.
     *
     * @param object $formdata The submitted form data.
     * @return bool True on success.
     */
    abstract public function process_raw_data($formdata);

    /**
     * Fetch the formatted heading for the report.
     *
     * @return string The formatted heading.
     */
    abstract public function fetch_formatted_heading();

    /**
     * Fetch the formatted description for the report.
     *
     * @return string The formatted description.
     */
    public function fetch_formatted_description() {

        return '';
    }

    /**
     * Constructor.
     *
     * @param object $cm The course module.
     */
    public function __construct($cm) {
        $this->cm = $cm;
        $this->context = \context_module::instance($cm->id);
    }

    /**
     * Fetch the list of fields in the report.
     *
     * @return array The list of fields.
     */
    public function fetch_fields() {
        return $this->fields;
    }

    /**
     * Fetch the formatted heading row for the report.
     *
     * @return array The list of heading strings.
     */
    public function fetch_head() {
        $head = [];
        foreach ($this->fields as $field) {
            $head[] = get_string($field, constants::M_COMPONENT);
        }
        return $head;
    }
    /**
     * Fetch the report name.
     *
     * @return string The report name.
     */
    public function fetch_name() {
        return $this->report;
    }

    /**
     * Fetch the total count of rows in the raw data.
     *
     * @return int The number of rows.
     */
    public function fetch_all_rows_count() {
        return $this->rawdata ? count($this->rawdata) : 0;
    }

    /**
     * Truncate a string to a maximum length.
     *
     * @param string $string The string to truncate.
     * @param int $maxlength The maximum length.
     * @return string The truncated string.
     */
    public function truncate($string, $maxlength) {
        if (strlen($string) > $maxlength) {
            $string = substr($string, 0, $maxlength - 2) . '..';
        }
        return $string;
    }

    /**
     * Fetch a database record, using a local cache.
     *
     * @param string $table The database table name.
     * @param int $rowid The id of the row to fetch.
     * @return object The database record.
     */
    public function fetch_cache($table, $rowid) {
        global $DB;
        if (!array_key_exists($table, $this->dbcache)) {
            $this->dbcache[$table] = [];
        }
        if (!array_key_exists($rowid, $this->dbcache[$table])) {
            $this->dbcache[$table][$rowid] = $DB->get_record($table, ['id' => $rowid]);
        }
        return $this->dbcache[$table][$rowid];
    }

    /**
     * Fetch a formatted duration for a number of seconds.
     *
     * @param int $seconds The number of seconds.
     * @return string The formatted time difference.
     */
    public function fetch_formatted_time($seconds) {

        // return empty string if the timestamps are not both present.
        if (!$seconds) {
            return '';
        }
        $time = time();
        return $this->fetch_time_difference($time, $time + $seconds);
    }

    /**
     * Fetch a formatted difference between two timestamps.
     *
     * @param int $starttimestamp The start timestamp.
     * @param int $endtimestamp The end timestamp.
     * @return string The formatted time difference.
     */
    public function fetch_time_difference($starttimestamp, $endtimestamp) {

        // return empty string if the timestamps are not both present.
        if (!$starttimestamp || !$endtimestamp) {
            return '';
        }

        $s = $date = new \DateTime();
        $s->setTimestamp($starttimestamp);

        $e = $date = new \DateTime();
        $e->setTimestamp($endtimestamp);

        $diff = $e->diff($s);
        $ret = $diff->format("%H:%I:%S");
        return $ret;
    }

    /**
     * Fetch the formatted rows for the report.
     *
     * @param bool $withlinks Whether to include links in the output.
     * @param object|bool $paging The paging information, or false for no paging.
     * @return array The formatted rows.
     */
    public function fetch_formatted_rows($withlinks = true, $paging = false) {
        $records = $this->rawdata;
        $fields = $this->fields;
        $returndata = [];
        if ($paging) {
            $startrecord = ($paging->perpage * $paging->pageno) + 1;
            $endrecord = $startrecord + $paging->perpage - 1;
        }
        $reccount = 0;
        foreach ($records as $record) {
            $reccount++;
            if ($paging && ($reccount < $startrecord || $reccount > $endrecord)) {
                continue;
            }

            $data = new \stdClass();
            foreach ($fields as $field) {
                $data->{$field} = $this->fetch_formatted_field($field, $record, $withlinks);
            }//end of for each field
            $returndata[] = $data;
        }//end of for each record
        return $returndata;
    }

    /**
     * Fetch a single formatted field value.
     *
     * @param string $field The field name.
     * @param object $record The data record.
     * @param bool $withlinks Whether to include links in the output.
     * @return string The formatted field value.
     */
    public function fetch_formatted_field($field, $record, $withlinks) {
        global $DB;
        switch ($field) {
            case 'timecreated':
                $ret = date("Y-m-d H:i:s", $record->timecreated);
                break;
            case 'userid':
                $u = $this->fetch_cache('user', $record->userid);
                $ret = fullname($u);
                break;
            default:
                if (property_exists($record, $field)) {
                    $ret = $record->{$field};
                } else {
                    $ret = '';
                }
        }
        return $ret;
    }
}
