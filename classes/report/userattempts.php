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
 * Report showing a single user's attempts across videos.
 */
class userattempts extends basereport {
    /** @var string The report identifier. */
    protected $report = "userattempts";
    /** @var array The fields displayed in the report. */
    protected $fields = ['videoid', 'videoname', 'difficulty', 'learn', 'speak', 'chat', 'timecreated'];
    /** @var \stdClass|null The submitted form data. */
    protected $formdata = null;
    /** @var array Cache of question records. */
    protected $qcache = [];
    /** @var array Cache of user records. */
    protected $ucache = [];

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
        $user = $this->fetch_cache('user', $record->userid);
        $ec = $this->fetch_cache(constants::M_TABLE, $record->ecid);
        $a = new \stdClass();
        $a->username = fullname($user);
        $a->activityname = $ec->name;

        return get_string('userattemptsheading', constants::M_COMPONENT, $a);
    }

    /**
     * Return a formatted value for the given field of a record.
     *
     * @param string $field The field name to format.
     * @param \stdClass $record The data record.
     * @param bool $withlinks Whether to include links in the output.
     * @return string The formatted field value.
     */
    public function fetch_formatted_field($field, $record, $withlinks) {
        global $DB, $CFG, $OUTPUT;

        switch ($field) {
            case 'videoid':
                $ret = $record->videoid;
                break;

            case 'videoname':
                if ($withlinks && !empty($record->videoname)) {
                    $link = new \moodle_url(
                        constants::M_URL . '/reports.php',
                        ['format' => $this->formdata->format, 'report' => 'videoperformance',
                        'id' => $this->cm->id,
                        'videoid' => $record->videoid,
                        'dayslimit' => $this->formdata->dayslimit]
                    );
                    $ret = \html_writer::link($link, $record->videoname);
                    if (!empty($record->detailsjson) && utils::is_json($record->detailsjson)) {
                        $details = json_decode($record->detailsjson);
                        if (isset($details->thumbnailURL)) {
                            $ret .= '<br/>' . \html_writer::img($details->thumbnailURL, '$record->videoname');
                        }
                    }
                } else {
                    if (empty($record->videoname)) {
                        $ret = get_string('deletedvideo', constants::M_COMPONENT);
                    } else {
                        $ret = $record->videoname;
                    }
                }
                break;

            case 'difficulty':
                    $ret = '-';
                if (!empty($record->detailsjson) && utils::is_json($record->detailsjson)) {
                    $details = json_decode($record->detailsjson);
                    if (isset($details->difficulty)) {
                        $ret = $details->difficulty;
                    }
                }
                break;

            case 'watch':
                $ret = $record->watchcount;
                break;

            case 'learn':
                $ret = $record->learncount;
                break;

            case 'speak':
                $ret = $record->speakcount;
                break;

            case 'chat':
                if (
                    get_config(constants::M_COMPONENT, 'chatmode') ||
                    intval($record->chatcount) > 0
                ) {
                    $ret = $record->chatcount;
                } else {
                    $ret = '-';
                }
                break;

            case 'activetime':
                $ret = $record->activetime;
                break;

            case 'totaltime':
                $ret = $record->totaltime;
                break;

            case 'timecreated':
                $ret = date("Y-m-d H:i:s", $record->timecreated);
                break;

            case 'timecompleted':
                $ret = date("Y-m-d H:i:s", $record->timecompleted);
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
     * Build and return the chart markup for the report.
     *
     * @param \renderer_base $renderer The output renderer.
     * @param bool $showdatasource Whether to show the data source table.
     * @return string The rendered chart HTML.
     */
    public function fetch_chart($renderer, $showdatasource = true) {
        global $CFG;
        $records = $this->rawdata;
        // Build the series data.
        $learnseries = [];
        $speakseries = [];
        $chatseries = [];
        $videonames = [];
        foreach ($records as $record) {
            $learnseries[] = $record->learncount;
            $speakseries[] = $record->speakcount;
            $chatseries[] = $record->chatcount;
            if (empty($record->videoname)) {
                $videonames[] = get_string('deletedvideo', constants::M_COMPONENT);
            } else {
                $videonames[] = $record->videoname;
            }
        }

        // Display the chart.
        $chart = new \core\chart_bar();
        $chart->set_horizontal(false);
        $chart->set_stacked(false);
        $chart->add_series(new \core\chart_series(
            get_string('learn', constants::M_COMPONENT),
            $learnseries
        ));
        $chart->add_series(new \core\chart_series(
            get_string('speak', constants::M_COMPONENT),
            $speakseries
        ));
        if (get_config(constants::M_COMPONENT, 'chatmode')) {
            $chart->add_series(new \core\chart_series(
                get_string('chat', constants::M_COMPONENT),
                $chatseries
            ));
        }
        $chart->set_labels($videonames);
        $thechart = $renderer->render_chart($chart, $showdatasource);
        return '<div class="mod_ec_chartcontainer chart_userattempts">' .
            $thechart . '</div>';
    }

    /**
     * Fetch and store the raw data for the report.
     *
     * @param \stdClass $formdata The submitted form data.
     * @return bool True on success.
     */
    public function process_raw_data($formdata) {
        global $DB;

        // Save form data for later.
        $this->formdata = $formdata;

        $this->rawdata = [];
        $emptydata = [];

        $selectsql = 'SELECT tu.*, vid.name as videoname, vid.detailsjson FROM {' . constants::M_ATTEMPTSTABLE . '} tu ';
        $selectsql .= 'LEFT OUTER JOIN {' . constants::M_VIDEOSTABLE . '} vid ';
        $selectsql .= 'ON (tu.ecid = vid.ecid) AND (tu.videoid = vid.videoid) ';
        $selectsql .= 'WHERE tu.ecid =? AND tu.userid = ?';
        $allparams = ['ecid' => $formdata->ecid, 'userid' => $formdata->userid];

        // Days limit WHERE condition.
        if ($formdata->dayslimit > 0) {
            // Calculate the unix timestamp X days ago.
            // 86400 = 24 hours * 60 minutes * 60 seconds.
            $dayslimit = time() - ($formdata->dayslimit * 86400);
            $dayslimitcondition = " AND timecreated >= ?";
            $selectsql .= $dayslimitcondition;
            $allparams['dayslimit'] = $dayslimit;
        }

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
