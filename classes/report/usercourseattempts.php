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
 * Report showing a single user's attempts across a course's activities.
 */
class usercourseattempts extends basereport {
    /** @var string The report identifier. */
    protected $report = "usercourseattempts";

    /** @var array The fields displayed in the report. */
    protected $fields = ['activityname', 'total_p', 'watch', 'learn', 'speak', 'chat', 'firstattempt'];
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
            case 'activityname':
                return $this->format_activityname_field($record, $withlinks);

            // Not necessary here . Since Watch = the same details.
            case 'attempts':
                return $record->attemptcount;

            case 'watch':
            case 'learn':
            case 'speak':
                return $this->format_goal_field($record, $field);

            case 'chat':
                return $this->format_chat_field($record);

            case 'total_p':
                return $record->total_p . "% (" . $record->total . ")";

            case 'firstattempt':
                return date("Y-m-d H:i:s", $record->firstattempt);

            default:
                return property_exists($record, $field) ? $record->{$field} : '';
        }
    }

    /**
     * Format the activityname field, optionally linked to that user's report for the activity.
     *
     * @param \stdClass $record The data record.
     * @param bool $withlinks Whether to include links in the output.
     * @return string The formatted field value.
     */
    private function format_activityname_field($record, $withlinks) {
        $this->fetch_cache(constants::M_TABLE, $record->ecid);
        $ret = $record->name;
        if ($withlinks) {
            $link = new \moodle_url(
                constants::M_URL . '/reports.php',
                ['format' => $this->formdata->format, 'report' => 'userattempts',
                'id' => $this->cm->id,
                'userid' => $this->formdata->userid]
            );
            $ret = \html_writer::link($link, $ret);
        }
        return $ret;
    }

    /**
     * Format a watch/learn/speak field as "count/goal", or just "count" if no goal is set.
     *
     * @param \stdClass $record The data record.
     * @param string $field The goal type (watch, learn or speak).
     * @return string The formatted field value.
     */
    private function format_goal_field($record, $field) {
        $goal = intval($record->{$field . 'goal'});
        if ($goal > 0) {
            return $record->{$field} . '/' . $goal;
        }
        return $record->{$field};
    }

    /**
     * Format the chat field, taking chat mode availability into account.
     *
     * @param \stdClass $record The data record.
     * @return string The formatted field value.
     */
    private function format_chat_field($record) {
        if (!get_config(constants::M_COMPONENT, 'chatmode') && intval($record->chat) <= 0) {
            return '-';
        }
        return $this->format_goal_field($record, 'chat');
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
        $thecourse = $this->fetch_cache('course', $record->course);
        $theuser = $this->fetch_cache('user', $record->userid);
        $a = new \stdClass();
        $a->username = fullname($theuser);
        $a->coursename = $thecourse->fullname;
        return get_string('usercourseattemptsheading', constants::M_COMPONENT, $a);
    }

    /**
     * Build and return the chart markup for the report.
     *
     * @param \renderer_base $renderer The output renderer.
     * @param bool $showdatasource Whether to show the data source table.
     * @return string The rendered chart HTML.
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function fetch_chart($renderer, $showdatasource = true) {
        $records = $this->rawdata;
        // Build the series data.
        $watchseries = [];
        $learnseries = [];
        $speakseries = [];
        $chatseries = [];
        $activitynames = [];
        foreach ($records as $record) {
            $watchseries[] = $record->watch_p;
            $learnseries[] = $record->learn_p;
            $speakseries[] = $record->speak_p;
            $chatseries[] = $record->chat_p;
            $activitynames[] = $record->name;
        }

        // Display the chart.
        $chart = new \core\chart_bar();
        $chart->set_horizontal(false);
        $chart->set_stacked(false);
        $yaxis = $chart->get_yaxis(0, true);
        $yaxis->set_stepsize(10);
        $yaxis->set_min(0);
        $yaxis->set_max(100);

        $chart->add_series(new \core\chart_series(
            get_string('watch', constants::M_COMPONENT),
            $watchseries
        ));
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
        $chart->set_labels($activitynames);

        $thechart = $renderer->render_chart($chart, $showdatasource);
        return '<div class="mod_ec_chartcontainer chart_usercourseattempts">' .
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

        $emptydata = [];

        // Now lets build our SQL.
        $selectsql = 'SELECT tu.ecid , SUM(COALESCE(watchcomplete, 0)) + ' .
          'SUM(COALESCE(learncount, 0)) + ' .
          'SUM(COALESCE(speakcount, 0)) + ' .
          'SUM(COALESCE(chatcount, 0)) AS total,' .
          'SUM(COALESCE(watchcomplete, 0)) AS watch,' .
          'SUM(COALESCE(learncount, 0)) AS learn,' .
          'SUM(COALESCE(speakcount, 0)) AS speak,' .
          'SUM(COALESCE(chatcount, 0)) AS chat,' .
          'MIN(tu.timecreated) AS firstattempt, ' .
          'ec.name, ' .
          'ec.watchgoal, ' .
          'ec.learngoal, ' .
          'ec.speakgoal, ' .
          'ec.chatgoal ' .
          'FROM {' . constants::M_ATTEMPTSTABLE . '} tu ' .
          'INNER JOIN {' . constants::M_TABLE . '} ec ' .
          'ON ec.id = tu.ecid ';

        $alldatasql = $selectsql . " WHERE ec.course = ? AND tu.userid = ? ";
        $allparams = ['course' => $formdata->course, 'userid' => $formdata->userid];

        // Days limit WHERE condition.
        if ($formdata->dayslimit > 0) {
            // Calculate the unix timestamp X days ago.
            // 86400 = 24 hours * 60 minutes * 60 seconds.
            $dayslimit = time() - ($formdata->dayslimit * 86400);
            $dayslimitcondition = " AND tu.timecreated >= ?";
            $alldatasql .= $dayslimitcondition;
            $allparams['dayslimit'] = $dayslimit;
        }

        // Add a 'group by' clause to SQL.
        $alldatasql .= "GROUP BY tu.ecid";

        // Use the SQL to fetch the data.
        $alldata = $DB->get_records_sql($alldatasql, $allparams);

        // Here we manually tweak the data, in this case to use points and goals to create percents.
        if ($alldata) {
            foreach ($alldata as $thedata) {
                // Get the goals for each ec activity returned.
                $goals = ['watch' => 0, 'learn' => 0, 'speak' => 0, 'chat' => 0, 'total' => 0];
                if (
                    $thedata->watchgoal +
                    $thedata->learngoal +
                    $thedata->speakgoal +
                    $thedata->chatgoal
                ) {
                    $goals['watch'] = intval($thedata->watchgoal);
                    $goals['learn'] = intval($thedata->learngoal);
                    $goals['speak'] = intval($thedata->speakgoal);
                    $goals['chat'] = intval($thedata->chatgoal);
                }
                $goals['total'] = $goals['watch'] + $goals['learn'] + $goals['speak'] + $goals['chat'];

                // Add a percentage field for each pointfield and add the goal to the display
                // Eg learn = 6 becomes learn = 6/8  learn_p = 75%.
                $totalpoints = 0;
                foreach ($goals as $goalfield => $goalvalue) {
                    if ($goalfield == 'total') {
                        continue;
                    }
                    $pointsvalue = $thedata->{$goalfield};
                    // We need to adjust the pointvalue so its not higher than goalvalue (eg they spoke 6 lines, but goal was 2).
                    if ($pointsvalue > $goalvalue && $goalvalue > 0) {
                        $pointsvalue = $goalvalue;
                    }
                    $thedata->{$goalfield . '_p'} = $goalvalue > 0 ? round($pointsvalue / $goalvalue * 100, 0) : '-';
                    // We recalc the total, using the goal adjusted points value.
                    $totalpoints += $pointsvalue;
                }
                $thedata->total = $totalpoints;
                $thedata->total_p = $goals['total'] > 0 ? round($totalpoints / $goals['total'] * 100, 0) : '-';
                $this->rawdata[] = $thedata;
            }
        } else {
            $this->rawdata = $emptydata;
        }
        return true;
    }
}
