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
use mod_englishcentral\utils;


/**
 * Report showing per-video performance statistics.
 */
class videoperformance extends basereport {
    /** @var string The report identifier. */
    protected $report = "videoperformance";
    /** @var array The fields displayed in the report. */
    protected $fields = ['videoid', 'videoname', 'difficulty', 'totalwatches', 'averagelearn', 'averagespeak', 'averagechat'];
    /** @var \stdClass|null The submitted form data. */
    protected $formdata = null;
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
            case 'videoid':
                return $record->videoid;

            case 'videoname':
                return $this->format_videoname_field($record);

            case 'difficulty':
                return $this->format_difficulty_field($record);

            case 'totalwatches':
                return $record->totalwatches;

            case 'averagelearn':
                return $record->averagelearn;

            case 'averagespeak':
                return $record->averagespeak;

            case 'averagechat':
                return $this->format_averagechat_field($record);

            default:
                return property_exists($record, $field) ? $record->{$field} : '';
        }
    }

    /**
     * Format the videoname field, appending a thumbnail if available.
     *
     * @param \stdClass $record The data record.
     * @return string The formatted field value.
     */
    private function format_videoname_field($record) {
        $ret = $record->videoname;
        if (!empty($record->detailsjson) && utils::is_json($record->detailsjson)) {
            $details = json_decode($record->detailsjson);
            if (isset($details->thumbnailURL)) {
                $ret .= '<br/>' . \html_writer::img($details->thumbnailURL, '$record->videoname');
            }
        }
        return $ret;
    }

    /**
     * Format the difficulty field, extracted from the video's cached details JSON.
     *
     * @param \stdClass $record The data record.
     * @return string The formatted field value.
     */
    private function format_difficulty_field($record) {
        if (!empty($record->detailsjson) && utils::is_json($record->detailsjson)) {
            $details = json_decode($record->detailsjson);
            if (isset($details->difficulty)) {
                return $details->difficulty;
            }
        }
        return '-';
    }

    /**
     * Format the averagechat field, taking chat mode availability into account.
     *
     * @param \stdClass $record The data record.
     * @return string The formatted field value.
     */
    private function format_averagechat_field($record) {
        if (!get_config(constants::M_COMPONENT, 'chatmode') && intval($record->averagechat) <= 0) {
            return '-';
        }
        return $record->averagechat;
    }

    /**
     * Return the formatted heading for the report.
     *
     * @return string The report heading.
     */
    public function fetch_formatted_heading() {
        $record = $this->formdata;
        $ret = '';
        if (!$record) {
            return $ret;
        }

        $ec = $this->fetch_cache(constants::M_TABLE, $record->ecid);
        return get_string('videoperformanceheading', constants::M_COMPONENT, $ec->name);
    }

    /**
     * Build and return the chart markup for the report.
     *
     * @param \renderer_base $renderer The output renderer.
     * @param bool $showdatasource Whether to show the data source table.
     * @return string The rendered chart HTML.
     */
    public function fetch_chart($renderer, $showdatasource = true) {
        $records = $this->rawdata;

        // Build the series data.
        $videoseries = [];
        $videonames = [];
        foreach ($records as $record) {
            $videoseries[] = $record->totalwatches;
            $videonames[] = $record->videoname;
        }

        // Display the chart.
        $chart = new \core\chart_pie();
        $chart->set_doughnut(true); // Calling set_doughnut(true) we display the chart as a doughnut.
        $chart->add_series(new \core\chart_series('My series title', $videoseries));
        $chart->set_labels($videonames);
        $thechart = $renderer->render_chart($chart, $showdatasource);
        return $thechart;
    }

    /**
     * Process the submitted form data into raw report data.
     *
     * @param object $formdata The submitted form data.
     * @return bool True on success.
     */
    public function process_raw_data($formdata) {
        global $DB;

        // Save form data for later.
        $this->formdata = $formdata;

        $this->rawdata = [];
        $emptydata = [];

        $selectsql = 'SELECT vid.videoid as videoid, vid.name as videoname, vid.detailsjson, ' .
        'COUNT(watchcomplete) as totalwatches,' .
        'ROUND(AVG(COALESCE(learncount, 0)),1) AS averagelearn,' .
        'ROUND(AVG(COALESCE(speakcount, 0)),1) AS averagespeak,' .
        'ROUND(AVG(COALESCE(chatcount, 0)),1) AS averagechat ' .
        ' FROM {' . constants::M_ATTEMPTSTABLE . '} tu ';

        $selectsql .= 'INNER JOIN {' . constants::M_VIDEOSTABLE . '} vid ';
        $selectsql .= 'ON (tu.ecid = vid.ecid) and (tu.videoid = vid.videoid) ';
        $selectsql .= 'WHERE tu.ecid = ? ';
        $allparams = ['ecid' => $formdata->ecid];

        // Days limit WHERE condition.
        if ($formdata->dayslimit > 0) {
            // Calculate the unix timestamp X days ago.
            // 86400 = 24 hours * 60 minutes * 60 seconds.
            $dayslimit = time() - ($formdata->dayslimit * 86400);
            $dayslimitcondition = " AND timecreated >= ?";
            $selectsql .= $dayslimitcondition;
            $allparams['dayslimit'] = $dayslimit;
        }

        // GROUP BY .
        $selectsql .= 'GROUP BY vid.id, vid.name ';

        // Run the SQL.
        $alldata = $DB->get_records_sql($selectsql, $allparams);

        if ($alldata) {
            foreach ($alldata as $thedata) {
                // Do any data massaging here.
                $this->rawdata[] = $thedata;
            }
        } else {
            $this->rawdata = $emptydata;
        }
        return true;
    }
}
