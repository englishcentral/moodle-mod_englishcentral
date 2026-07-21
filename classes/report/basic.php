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
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 20:52
 * @package    mod_englishcentral
 * @copyright  2014 onwards Justin Hunt; 2024 onwards EnglishCentral
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_englishcentral\report;

use mod_englishcentral\constants;

/**
 * Basic report showing raw activity records.
 */
class basic extends basereport {
    /** @var string The report identifier. */
    protected $report = "basic";
    /** @var array The fields displayed in the report. */
    protected $fields = ['id', 'name', 'timecreated'];
    /** @var \stdClass|null The heading data for the report. */
    protected $headingdata = null;
    /** @var array Cache of question records. */
    protected $qcache = [];
    /** @var array Cache of user records. */
    protected $ucache = [];

    /**
     * Return a formatted value for the given field of a record.
     *
     * @param string $field The field name to format.
     * @param \stdClass $record The data record.
     * @param bool $withlinks Whether to include links in the output.
     * @return string The formatted field value.
     */
    public function fetch_formatted_field($field, $record, $withlinks) {
        switch ($field) {
            case 'id':
                $ret = $record->id;
                break;

            case 'name':
                $ret = $record->name;
                break;

            case 'timecreated':
                $ret = date("Y-m-d H:i:s", $record->timecreated);
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

    /**
     * Return the formatted heading for the report.
     *
     * @return string The report heading.
     */
    public function fetch_formatted_heading() {
        $record = $this->headingdata;
        $ret = '';
        if (!$record) {
            return $ret;
        }
        return get_string('basicheading', constants::M_COMPONENT);
    }

    /**
     * Fetch and store the raw data for the report.
     *
     * @param \stdClass $formdata The submitted form data.
     * @return bool True on success.
     */
    public function process_raw_data($formdata) {
        global $DB;

        // Heading data.
        $this->headingdata = new \stdClass();

        $emptydata = [];
        $alldata = $DB->get_records(constants::M_TABLE, []);
        if ($alldata) {
            $this->rawdata = $alldata;
        } else {
            $this->rawdata = $emptydata;
        }
        return true;
    }
}
