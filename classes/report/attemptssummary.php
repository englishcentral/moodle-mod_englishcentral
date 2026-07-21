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
 * Report showing a summary of attempts for each user.
 *
 * @package    mod_englishcentral
 * @copyright  2014 onwards Justin Hunt; 2024 onwards EnglishCentral
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attemptssummary extends basereport
{
    /** @var string The report identifier. */
    protected $report = "attemptssummary";
    /** @var array The list of fields in the report. */
    protected $fields = ['firstname', 'lastname', 'total_p', 'watch', 'learn', 'speak', 'chat'];
    /** @var object|null The submitted form data. */
    protected $formdata = null;
    /** @var array A cache of question records. */
    protected $qcache = [];
    /** @var array A cache of user records. */
    protected $ucache = [];
    /** @var object|null The study goals. */
    protected $goals = null;
    /** @var string The current sort field. */
    protected $sort = 'firstname';
    /** @var string The current sort order. */
    protected $order = 'ASC';

    /** @var object|null The EnglishCentral activity. */
    protected $ec = null;

    /**
     * Fetch a single formatted field value.
     *
     * @param string $field The field name.
     * @param object $record The data record.
     * @param bool $withlinks Whether to include links in the output.
     * @return string The formatted field value.
     */
    public function fetch_formatted_field($field, $record, $withlinks) {
        switch ($field) {
            case 'firstname':
            case 'lastname':
                return $this->format_name_field($field, $record, $withlinks);

            case 'learn':
            case 'speak':
            case 'watch':
                return $record->{$field} . '/' . $this->goals->{$field};

            case 'total_p':
                return $record->percent . "%";

            case 'chat':
                return $this->format_chat_field($record);

            default:
                return property_exists($record, $field) ? $record->{$field} : '';
        }
    }

    /**
     * Format the firstname/lastname field, optionally linked to that user's individual report.
     *
     * @param string $field The field name (firstname or lastname).
     * @param \stdClass $record The data record.
     * @param bool $withlinks Whether to include links in the output.
     * @return string The formatted field value.
     */
    private function format_name_field($field, $record, $withlinks) {
        if (!$withlinks) {
            return $record->{$field};
        }
        $link = new \moodle_url(
            constants::M_URL . '/reports.php',
            [
                'format' => $this->formdata->format,
                'report' => 'userattempts',
                'id' => $this->cm->id,
                'userid' => $record->userid,
                'dayslimit' => $this->formdata->dayslimit,
            ]
        );
        return \html_writer::link($link, $record->{$field});
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
        return $record->chat . '/' . $this->goals->chat;
    }

    /**
     * Fetch the formatted heading for the report.
     *
     * @return string The formatted heading.
     */
    public function fetch_formatted_heading() {
        $record = $this->formdata;
        $ret = '';
        if (!$record) {
            return $ret;
        }
        $ec = $this->fetch_cache(constants::M_TABLE, $record->ecid);
        return get_string('attemptssummaryheading', constants::M_COMPONENT, $ec->name);
    }

    /**
     * Fetch the chart output for the report.
     *
     * @param object $renderer Unused; this report renders its own bars rather than delegating
     *               to the chart renderer, but the parameter is required to satisfy the shared
     *               fetch_chart() interface implemented by all report classes.
     * @param bool $showdatasource Unused, for the same reason as $renderer.
     * @return string The chart HTML.
     */
    public function fetch_chart($renderer, $showdatasource = true) {
        global $PAGE;
        $PAGE->requires->js_call_amd($this->ec->plugin . "/report", 'init');
        $items = $this->rawdata;
        $output = '';
        $url = $PAGE->url;
        $type = 'firstname';
        $fullname = get_string($type, 'moodle');
        $fullname .= $this->get_sort_icon($url, $type);

        $fullname .= ' ';

        $type = 'lastname';
        $fullname .= get_string('lastname', 'moodle');
        $fullname .= $this->get_sort_icon($url, $type);
        $fullname = \html_writer::tag('span', $fullname, ['class' => 'fullname']);

        $type = 'percent';
        $percent = '%';
        $percent .= $this->get_sort_icon($url, $type);
        $percent = \html_writer::tag('span', $percent, ['class' => 'percent']);

        $output .= \html_writer::tag('dt', $fullname . $percent, ['class' => 'user title']);

        $title = '';
        $left = 0;
        foreach (['watch', 'learn', 'speak', 'chat'] as $type) {
            if ($this->goals->$type) {
                $text = $this->ec->get_string($type . 'goal');
                $sort = $this->get_sort_icon($url, $type);
                $percent = (100 * min(1, $this->goals->$type / $this->goals->total));
                $style = "margin-left: $left%; width: $percent%;";
                $params = ['class' => $type, 'style' => $style];
                $title .= \html_writer::tag('span', $text . ' ' . $sort, $params);
                $left += $percent;
            }
        }
        $output .= \html_writer::tag('dd', $title, ['class' => 'bars title']);

        if ($this->sort == 'percent') {
            uasort($items, [$this, 'uasort_percent']);
        }

        foreach ($items as $userid => $item) {
            $output .= $this->show_progress_report_item($item);
        }

        if (count($items)) {
            $output = \html_writer::tag('dl', $output, ['class' => 'userbars']);
        } else {
            $output = \html_writer::tag('p', $this->ec->get_string('noprogressreport'));
        }

         // We need it to be under page-mod-englishcentral-report for the css styles to apply.
         return \html_writer::div($output, 'page-mod-englishcentral-report', ['id' => 'page-mod-englishcentral-report']);
    }

    /**
     * Set the sort item/order
     */
    protected function setup_sort() {
        global $SESSION;

        // Initialize session info.
        if (empty($SESSION->englishcentral)) {
            $SESSION->englishcentral = new \stdClass();
            $SESSION->englishcentral->sort = '';
            $SESSION->englishcentral->order = '';
        }

        // Override sort item/order with incoming data.
        $sort = optional_param('sort', '', PARAM_ALPHA);
        switch (true) {
            case ($sort == ''):
                $sort = $SESSION->englishcentral->sort;
                $order = $SESSION->englishcentral->order;
                break;

            case ($sort == $SESSION->englishcentral->sort):
                $order = optional_param('order', '', PARAM_ALPHA);
                break;

            default:
                $order = '';
        }

        if ($sort == '') {
            $sort = 'lastname';
            $order = '';
        }

        if ($order == '') {
            if ($sort == 'firstname' || $sort == 'lastname') {
                $order = 'ASC';
            } else {
                $order = 'DESC';
            }
        }

        // Store new/updated sort item/order.
        $this->sort = $SESSION->englishcentral->sort = $sort;
        $this->order = $SESSION->englishcentral->order = $order;
    }

    /**
     * Fetch the sort icon link for a column.
     *
     * @param \moodle_url $url The base URL.
     * @param string $sort The sort field for this column.
     * @return string The sort icon HTML link.
     */
    protected function get_sort_icon($url, $sort) {
        global $OUTPUT;

        if ($sort == $this->sort) {
            $order = $this->order;
        } else {
            $order = ''; // Unsorted.
        }

        switch (true) {
            case ($order == 'ASC'):
                $text = 'sortdesc';
                $icon = 't/sort_asc';
                break;
            case ($order == 'DESC'):
                $text = 'sortasc';
                $icon = 't/sort_desc';
                break;
            case ($sort == 'firstname'):
            case ($sort == 'lastname'):
                $text = "sortby$sort";
                $icon = 't/sort';
                // Deliberate fall-through to the default case.
            default:
                $text = 'sort';
                $icon = 't/sort';
                break;
        }

        $params = [];
        if ($sort) {
            $params['sort'] = $sort;
        } else {
            $url->remove_params('sort');
        }
        if ($order) {
            $params['order'] = ($order == 'ASC' ? 'DESC' : 'ASC');
        } else {
            $url->remove_params('order');
        }
        if (count($params)) {
            $url->params($params);
        }

        $text = get_string($text, 'grades');
        $params = ['class' => 'sorticon'];
        $icon = $OUTPUT->pix_icon($icon, $text, 'moodle', $params);

        return \html_writer::link($url, $icon, ['title' => $text]);
    }

    /**
     * Comparison callback for sorting report items by percent complete.
     *
     * @param object $a The first item to compare.
     * @param object $b The second item to compare.
     * @return int Negative, zero or positive depending on the sort order.
     */
    protected function uasort_percent($a, $b) {
        $anum = intval($a->percent);
        $bnum = intval($b->percent);
        if ($anum > $bnum) {
            return ($this->order == 'ASC' ? 1 : -1);
        }
        if ($anum < $bnum) {
            return ($this->order == 'ASC' ? -1 : 1);
        }
        return 0;
    }

    /**
     * Render a single user's progress report item.
     *
     * @param object $item The user's progress data.
     * @return string The rendered HTML.
     */
    protected function show_progress_report_item($item) {
        $output = '';
        $output .= \html_writer::tag('dt', $this->show_progress_report_user($item), ['class' => 'user']);
        $output .= \html_writer::tag('dd', $this->show_progress_report_bars($item), ['class' => 'bars']);
        return $output;
    }

    /**
     * Render the user's name and overall percentage for a progress report item.
     *
     * @param object $item The user's progress data.
     * @return string The rendered HTML.
     */
    protected function show_progress_report_user($item) {
        $output = '';
        $output .= \html_writer::tag('span', fullname($item), ['class' => 'fullname']);
        $output .= \html_writer::tag('span', $item->percent . '%', ['class' => 'percent']);
        return $output;
    }

    /**
     * Render all progress bars (watch/learn/speak/chat) for a progress report item.
     *
     * @param object $item The user's progress data.
     * @return string The rendered HTML.
     */
    protected function show_progress_report_bars($item) {
        $output = '';
        $output .= $this->show_progress_report_bar($item, 'watch');
        $output .= $this->show_progress_report_bar($item, 'learn');
        $output .= $this->show_progress_report_bar($item, 'speak');
        $output .= $this->show_progress_report_bar($item, 'chat');
        return $output;
    }

    /**
     * Render a single progress bar for the given goal type.
     *
     * @param object $item The user's progress data.
     * @param string $type The goal type (watch/learn/speak/chat).
     * @return string The rendered HTML, or an empty string if the goal is not set.
     */
    protected function show_progress_report_bar($item, $type) {
        if (empty($this->goals->$type)) {
            return '';
        }

        $text = $item->$type . ' / ' . $this->goals->$type;
        switch ($type) {
            case 'watch':
                $title = $this->ec->get_string('watchvideos', $text);
                break;
            case 'learn':
                $title = $this->ec->get_string('learnwords', $text);
                break;
            case 'speak':
                $title = $this->ec->get_string('speaklines', $text);
                break;
            case 'chat':
                $title = $this->ec->get_string('chatquestions', $text);
                break;
        }
        $text = \html_writer::tag('span', $text, ['class' => 'text', 'title' => $title]);

        if (empty($item->$type)) {
            $bar = '';
        } else {
            $value = min($item->$type, $this->goals->$type);
            $width = (100 * min(1, $value / $this->goals->$type)) . '%;';
            $params = ['class' => 'bar', 'style' => 'width: ' . $width];
            $bar = \html_writer::tag('span', '', $params);
        }

        $width = (100 * min(1, $this->goals->$type / $this->goals->total)) . '%';
        $params = ['class' => $type, 'style' => 'width: ' . $width];

        return \html_writer::tag('span', $bar . $text, $params);
    }

    /**
     * Process the submitted form data into raw report data.
     *
     * @param object $formdata The submitted form data.
     * @return bool True on success.
     */
    public function process_raw_data($formdata) {
        global $CFG, $DB;

        // Save form data for later.
        $this->formdata = $formdata;

        // Set up sort.
        $this->setup_sort();

        // Init empty data.
        $emptydata = [];

        // Groups stuff.
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $formdata->ecid]);
        $course = $DB->get_record('course', ['id' => $moduleinstance->course], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance(constants::M_TABLE, $moduleinstance->id, $course->id, false, MUST_EXIST);
        $context = empty($cm) ? \context_course::instance($course->id) : \context_module::instance($cm->id);
        $ec = \mod_englishcentral\activity::create($moduleinstance, $cm, $course, $context);
        $this->ec = $ec;

        // Initialize study goals.
        $goals = (object) [
            'watch' => 0,
            'learn' => 0,
            'speak' => 0,
            'chat' => 0,
        ];

        // Create SQL to fetch aggregate items from the EC attempts table.
        $select = 'userid,' .
            'SUM(watchcomplete) + SUM(learncount) + SUM(speakcount) + SUM(chatcount) AS percent,' .
            'SUM(watchcomplete) AS watch,' .
            'SUM(learncount) AS learn,' .
            'SUM(speakcount) AS speak,' .
            'SUM(chatcount) AS chat';
        $from = '{englishcentral_attempts}';
        $where = 'ecid = ?';
        $params = [$formdata->ecid];

        // Days limit WHERE condition.
        if ($formdata->dayslimit > 0) {
            // Calculate the unix timestamp X days ago.
            // 86400 = 24 hours * 60 minutes * 60 seconds.
            $dayslimitinseconds = time() - ($formdata->dayslimit * 86400);
            $dayslimitcondition = " AND timecreated >= ?";
            $where .= $dayslimitcondition;
            $params['dayslimit'] = $dayslimitinseconds;
        }

        if ($formdata->groupid) {
            $where .= ' AND userid IN (SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid = ?)';
            $params[] = $formdata->groupid;
        }
        $where = "$where GROUP BY userid";

        $from = "(SELECT $select FROM $from WHERE $where) items," .
            '{user} u';
        $where = 'items.userid = u.id';

        // Get_all_user_name_fields deprecated in 3.11.
        if ($CFG->version < 2021051700) {
            $select = 'items.*,' . get_all_user_name_fields(true, 'u');
        } else {
            $userfields = \core_user\fields::for_name();
            $usersql = $userfields->get_sql('u');
            // Note no concatenating comma, thats how userfields -> selects works.
            $select = 'items.*' . $usersql->selects;
        }

        if ($this->sort == 'firstname' || $this->sort == 'lastname') {
            $order = 'u.' . $this->sort;
        } else {
            $order = 'items.' . $this->sort;
        }
        if ($this->order) {
            $order .= ' ' . $this->order;
        }

        // Set goals to maximum in these aggregate items.
        if ($items = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params)) {
            foreach ($items as $userid => $item) {
                $goals->watch = max($goals->watch, $item->watch);
                $goals->learn = max($goals->learn, $item->learn);
                $goals->speak = max($goals->speak, $item->speak);
                $goals->chat = max($goals->chat, $item->chat);
            }
        } else {
            $items = [];
        }

        // Override goals with teacher-specified goals, if available.
        if (
            $moduleinstance->watchgoal + $moduleinstance->learngoal +
            $moduleinstance->speakgoal + $moduleinstance->chatgoal
        ) {
            $goals->watch = intval($moduleinstance->watchgoal);
            $goals->learn = intval($moduleinstance->learngoal);
            $goals->speak = intval($moduleinstance->speakgoal);
            $goals->chat = intval($moduleinstance->chatgoal);
        }

        $goals->total = ($goals->watch +
            $goals->learn +
            $goals->speak +
            $goals->chat);
        $this->goals = $goals;

        // Here we can manually tweak the data,.
        if ($items) {
            foreach ($items as $userid => $item) {
                $item->total = (min($this->goals->watch, $item->watch) +
                    min($this->goals->learn, $item->learn) +
                    min($this->goals->speak, $item->speak) +
                    min($this->goals->chat, $item->chat));
                if ($this->goals->total == 0) {
                    $item->percent = '';
                } else {
                    $item->percent = round(100 * min(1, $item->total / $this->goals->total));
                }
                $this->rawdata[$userid] = $item;
            }
        } else {
            $this->rawdata = $emptydata;
        }
        return true;
    }
}
