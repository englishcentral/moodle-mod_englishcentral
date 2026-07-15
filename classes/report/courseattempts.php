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
 * Report showing attempt totals per user for a course.
 */
class courseattempts extends basereport {
    /** @var string The report identifier. */
    protected $report = "courseattempts";

    /** @var array The fields displayed in the report. */
    protected $fields = ['firstname', 'lastname', 'activities', 'total', 'watch', 'learn', 'speak', 'chat'];
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
        global $DB, $CFG, $OUTPUT;
        switch ($field) {
            case 'username':
                $user = $this->fetch_cache('user', $record->userid);
                $ret = fullname($user);
                if ($withlinks) {
                        $link = new \moodle_url(
                            constants::M_URL . '/reports.php',
                            ['format' => $this->formdata->format, 'report' => 'usercourseattempts',
                            'id' => $this->cm->id,
                            'userid' => $record->userid,
                            'dayslimit' => $this->formdata->dayslimit]
                        );
                        $ret = \html_writer::link($link, $ret);
                }
                break;

            case 'firstname':
            case 'lastname':
                $user = $this->fetch_cache('user', $record->userid);
                if ($withlinks) {
                    $link = new \moodle_url(
                        constants::M_URL . '/reports.php',
                        ['format' => $this->formdata->format, 'report' => 'usercourseattempts',
                        'id' => $this->cm->id,
                        'userid' => $record->userid,
                        'dayslimit' => $this->formdata->dayslimit]
                    );
                    $ret = \html_writer::link($link, $user->{$field});
                } else {
                    $ret = $user->{$field};
                }
                break;

            case 'chat':
                if (
                    get_config(constants::M_COMPONENT, 'chatmode') ||
                    intval($record->chat) > 0
                ) {
                    $ret = $record->chat;
                } else {
                    $ret = '-';
                }
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
        $record = $this->formdata;
        $ret = '';
        if (!$record) {
            return $ret;
        }
        $thecourse = $this->fetch_cache('course', $record->course);
        return get_string('courseattemptsheading', constants::M_COMPONENT, $thecourse->fullname);
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
        $watchseries = [];
        $usernames = [];
        foreach ($records as $record) {
            $watchseries[] = $record->watch;
            $user = $this->fetch_cache('user', $record->userid);
            $usernames[] = fullname($user) . " ($record->watch)";
        }

        // Display the chart.
        $chart = new \core\chart_bar();
        $chart->set_horizontal(true);
        $chart->add_series(new \core\chart_series(
            get_string('watch', constants::M_COMPONENT),
            $watchseries
        ));
        $chart->set_labels($usernames);
        $thechart = $renderer->render_chart($chart, $showdatasource);
        // We set a height of 40px per "bar.".
        $chartheight = max([count($usernames) * 40, 450]);
        return '<div class="mod_ec_chartcontainer chart_courseattempts" style="height: ' .
            $chartheight . 'px">' .
            $thechart . '</div>';
    }

    /**
     * Fetch and store the raw data for the report.
     *
     * @param \stdClass $formdata The submitted form data.
     * @return bool True on success.
     */
    public function process_raw_data($formdata) {
        global $DB, $USER;

        // Save form data for later.
        $this->formdata = $formdata;

        $emptydata = [];
        $allparams = [];

        // Now lets build our SQL.
        $selectsql = 'SELECT tu.userid , COUNT(DISTINCT(ec.id)) AS activities,' .
          'SUM(COALESCE(watchcomplete, 0)) + ' .
          'SUM(COALESCE(learncount, 0)) + ' .
          'SUM(COALESCE(speakcount, 0)) + ' .
          'SUM(COALESCE(chatcount, 0)) AS total,' .
          'SUM(COALESCE(watchcomplete, 0)) AS watch,' .
          'SUM(COALESCE(learncount, 0)) AS learn,' .
          'SUM(COALESCE(speakcount, 0)) AS speak,' .
          'SUM(COALESCE(chatcount, 0)) AS chat '  .
          'FROM {' . constants::M_ATTEMPTSTABLE . '} tu ' .
          'INNER JOIN {' . constants::M_TABLE . '} ec ' .
          'ON ec.id = tu.ecid ';

        // If we need to show  groups.
        if ($formdata->groupid > 0) {
            [$groupswhere, $allparams] = $DB->get_in_or_equal($formdata->groupid);

            $alldatasql = $selectsql .
                    " INNER JOIN {groups_members} gm ON tu.userid=gm.userid " .
                    " WHERE gm.groupid $groupswhere AND ec.course = ?";
            $allparams[] = $formdata->course;

            // If we don't need to show groups.
        } else {
            $alldatasql = $selectsql . " WHERE ec.course = ?";
            $allparams['course'] = $formdata->course;
        }

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
        $alldatasql .= " GROUP BY userid";

        // Use the SQL to fetch the data.
        $alldata = $DB->get_records_sql($alldatasql, $allparams);

        // Here we manually tweak the data, in this case to use points and goals to create percents.
        if ($alldata) {
            foreach ($alldata as $thedata) {
                $this->rawdata[] = $thedata;
            }
        } else {
            $this->rawdata = $emptydata;
        }
        return true;
    }
}
