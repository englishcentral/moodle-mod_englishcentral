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

use mod_englishcentral\constants;
use mod_englishcentral\utils;


/**
 * A custom renderer class that extends the plugin_renderer_base.
 *
 * @package    mod_englishcentral
 * @copyright COPYRIGHTNOTICE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_englishcentral_renderer extends plugin_renderer_base {
    /** @var object The englishcentral activity object. */
    protected $ec = null;
    /** @var object The authentication/auth helper object. */
    protected $auth = null;

    /** @var string The current sort field for reports. */
    protected $sort = null;
    /** @var string The current sort order (ASC or DESC) for reports. */
    protected $order = null;

    /** @var int Signup type indicating no signup. */
    const SIGNUP_NONE = 0;
    /** @var int Signup type for standard school signup. */
    const SIGNUP_STANDARD = 1;
    /** @var int Signup type for corporate signup. */
    const SIGNUP_CORPORATE = 2;
    /** @var int Signup type for solutions signup. */
    const SIGNUP_SOLUTIONS = 3;

    /**
     * attach the $ec & $auth objects so they are accessible throughout this class
     *
     * @param object $ec a \mod_englishcentral/activity Object.
     * @param object $auth a \mod_englishcentral/auth Object.
     * @return void
     */
    public function attach_activity_and_auth($ec = null, $auth = null) {
        $this->ec = $ec;
        $this->auth = $auth;
    }

    /**
     * Returns the header for the englishcentral module
     *
     * @param string $extrapagetitle String to append to the page title.
     * @param bool $hidetabs Whether to hide the activity tabs.
     * @return string
     */
    public function header($extrapagetitle = null, $hidetabs = false) {
        global $CFG;

        $activityname = $this->set_page_title_and_heading($extrapagetitle);

        $output = $this->output->header();

        if (isset($this->ec->ecinstance)) {
            if (!$hidetabs && $this->can_view_tabs()) {
                $output .= $this->show_tabs_and_heading($activityname);
            } else if (!$this->ec->foriframe && $CFG->version < 4.0) {
                $output .= $this->output->heading($activityname);
            }
        }
        return $output;
    }

    /**
     * Set the page title and heading for the englishcentral activity, if one is attached.
     *
     * @param string $extrapagetitle String to append to the page title.
     * @return string|null The formatted activity name, or null if no activity is attached.
     */
    private function set_page_title_and_heading($extrapagetitle) {
        if (!isset($this->ec->id)) {
            return null;
        }
        $activityname = format_string($this->ec->name, true, $this->ec->course->id);
        $title = $this->ec->course->shortname . ': ' . $activityname;
        if ($extrapagetitle) {
            $title .= ': ' . $extrapagetitle;
        }
        $this->page->set_title($title);
        $this->page->set_heading($this->ec->course->fullname);
        return $activityname;
    }

    /**
     * Whether the current user can see the view/reports/developer-tools tabs.
     *
     * @return bool
     */
    private function can_view_tabs() {
        return has_capability('mod/englishcentral:manage', $this->ec->context)
            || has_capability('mod/englishcentral:viewreports', $this->ec->context)
            || has_capability('mod/englishcentral:viewdevelopertools', $this->ec->context);
    }

    /**
     * Determine the current tab (view/reports/developer) from the page URL, and the
     * icon linking to it, for use by the included tabs.php.
     *
     * @return array [$currenttab, $icon]
     */
    private function determine_current_tab() {
        if ($this->page->url == $this->ec->get_view_url()) {
            $icon = $this->pix_icon('i/preview', 'view', 'moodle', ['class' => 'icon']);
            return ['view', html_writer::link($this->ec->get_view_url(), $icon)];
        }
        if (strpos($this->page->url, $this->ec->get_report_url(false)) === 0) {
            $icon = $this->pix_icon('i/report', 'reports', 'moodle', ['class' => 'icon']);
            return ['reports', html_writer::link($this->ec->get_report_url(), $icon)];
        }
        if (strpos($this->page->url, $this->ec->get_developertools_url(false)) === 0) {
            $icon = $this->pix_icon('i/settings', 'developertools', constants::M_COMPONENT, ['class' => 'icon']);
            return ['developer', html_writer::link($this->ec->get_developertools_url(), $icon)];
        }
        return [null, ''];
    }

    /**
     * Render the view/reports/developer-tools tabs, plus the activity heading (unless
     * in an iframe on a pre-4.0 Moodle site, where the heading is shown outside).
     *
     * @param string $activityname The formatted activity name.
     * @return string The rendered HTML.
     */
    private function show_tabs_and_heading($activityname) {
        global $CFG;

        [$currenttab, $icon] = $this->determine_current_tab();

        // Set up tabs.
        $moduleinstance = $this->ec;
        ob_start();
        include($CFG->dirroot . '/mod/englishcentral/tabs.php');
        $output = ob_get_contents();
        ob_end_clean();

        // Dont show the heading in an iframe, it will be outside this anyway.
        if (!$this->ec->foriframe && $CFG->version < 4.0) {
            $help = $this->help_icon('overview', $this->ec->plugin);
            $output .= $this->heading($activityname . $help . $icon);
        }

        return $output;
    }


    /**
     * Return HTML to display limited header
     */
    public function notabsheader() {
        return $this->output->header();
    }

    /**
     * Return HTML to display message about missing config settings
     *
     * @param array|string $msg the message(s) to display
     * @return string
     */
    public function show_missingconfig($msg) {
        $output = '';
        $output .= $this->output->box_start('englishcentral_missingconfig');
        $output .= html_writer::tag('p', $this->ec->get_string('missingconfig'));
        $output .= $this->notification(html_writer::alist($msg), 'warning');
        $output .= $this->link_to_config_settings();
        $output .= $this->output->box_end();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Return HTML to display message about invalid config settings
     *
     * @param string $msg the message to display
     * @return string
     */
    public function show_invalidconfig($msg) {
        $output = '';
        $output .= $this->output->box_start('englishcentral_invalidconfig');
        $output .= html_writer::tag('p', $this->ec->get_string('invalidconfig'));
        $output .= $this->notification($msg, 'warning');
        $output .= $this->link_to_config_settings();
        $output .= $this->output->box_end();
        $output .= $this->footer();
        return $output;
    }

    /**
     * generate link to config settings page
     */
    public function link_to_config_settings() {
        // Moodle/site:config, moodle/category:manage.
        if (has_capability('moodle/site:config', context_system::instance())) {
            $link = ['section' => 'modsetting' . $this->ec->pluginname];
            $link = new moodle_url('/admin/settings.php', $link);
            $link = html_writer::link($link, get_string('settings'));
            return $this->ec->get_string('updatesettings', $link);
        } else {
            return $this->ec->get_string('consultadmin');
        }
    }

    /**
     * generate link to config settings page
     */
    public function show_support_form() {
        global $CFG, $DB, $USER;

        $signup = self::SIGNUP_SOLUTIONS;

        $fullname = fullname($USER);
        $subject = $this->ec->get_string('supportsubject');
        $description = $this->ec->get_string('supportmessage');
        $institution = $DB->get_field('course', 'fullname', ['id' => SITEID]);

        $output = '';
        $output .= html_writer::tag('h3', $this->ec->get_string('supporttitle'));
        $output .= html_writer::tag('p', $this->ec->get_string('supportconfirm'));
        $output .= html_writer::start_tag('table', ['class' => 'supportconfirm', 'cellpadding' => 4, 'cellspacing' => 4]);
        $output .= html_writer::tag('tr', html_writer::tag('th', get_string('name')) . html_writer::tag('td', $fullname));
        $output .= html_writer::tag('tr', html_writer::tag('th', get_string('email')) . html_writer::tag('td', $USER->email));

        $url = '';
        $anchor = '';
        $params = [];

        if ($USER->phone1) {
            $output .= html_writer::tag('tr', html_writer::tag('th', get_string('phone1')) . html_writer::tag('td', $USER->phone1));
        }
        if ($institution) {
            $output .= html_writer::tag(
                'tr',
                html_writer::tag('th', get_string('institution')) . html_writer::tag('td', $institution)
            );
        }

        if ($signup == self::SIGNUP_STANDARD) {
            $output .= html_writer::tag(
                'tr',
                html_writer::tag('th', get_string('subject', 'forum')) . html_writer::tag('td', $subject)
            );
            $output .= html_writer::tag(
                'tr',
                html_writer::tag('th', get_string('description')) . html_writer::tag('td', $description)
            );

            $url = 'https://www.englishcentral.com/support/contact-school-support';
            $params = ['name' => $fullname,
                            'email' => $USER->email,
                            'phone' => $USER->phone1,
                            'subject' => $subject,
                            'institution' => $institution,
                            'description' => $description,
                            'type' => 'access_code_coupon'];
        } else {
            if ($signup == self::SIGNUP_CORPORATE) {
                $url = 'https://corporate.englishcentral.com/moodle-signup-gordon';
            } else { // Default to the solutions signup URL.
                $url = 'https://solutions.englishcentral.com/moodle-signup-gordon';
            }
            $anchor = 'moodle-cta';
            $formid = '11252';
            $postid = '11207';
            $tag = 'wpcf7-f' . $formid . '-p' . $postid . '-o6';
            $params = ['_wpcf7' => $formid,
                            '_wpcf7_unit_tag' => $tag,
                            '_wpcf7_locale' => 'en_US',
                            '_wpcf7_version' => '5.0.3',
                            '_wpcf7_container_post' => $postid,
                            'your-name' => $fullname,
                            'your-email' => $USER->email,
                            'school-name' => $institution,
                            'number-student' => 100,
                            'contact-number' => (empty($USER->phone1) ? '0123456789' : $USER->phone1)];
        }

        $button = $this->single_button(new moodle_url($url, $params), get_string('continue'), 'post');

        // Remove sesskey from $button; it's not necessary and could be a security risk.
        $button = preg_replace('/<input[^>]*name="sesskey"[^>]*>/', '', $button);

        if ($anchor) {
            // Single_button with "post" does not allow #anchor, so we add it manually.
            $button = str_replace($url, "$url/#$anchor", $button);
        }

        $output .= html_writer::tag('tr', html_writer::tag('th', '') . html_writer::tag('td', $button));
        $output .= html_writer::end_tag('table');
        return $output;
    }

    /**
     * Show the  some text in a box
     *
     * @param string $boxtext the text to display in the box
     * @return string
     */
    public function show_box_text($boxtext) {
        $output = '';
        if (trim(strip_tags($boxtext))) {
            $output .= $this->output->box_start('mod_introbox');
            $output .= $boxtext;
            $output .= $this->output->box_end();
        }
        return $output;
    }

    /**
     * Show the introduction as entered on edit page
     */
    public function show_intro() {
        $output = '';
        if (trim(strip_tags($this->ec->intro))) {
            $output .= $this->output->box_start('mod_introbox');
            $output .= format_module_intro('englishcentral', $this->ec, $this->ec->cm->id);
            $output .= $this->output->box_end();
        }
        return $output;
    }

    /**
     * Show the message shown when the activity is not available.
     *
     * @return string
     */
    public function show_notavailable() {
        $output = $this->notification($this->ec->get_string('notavailable'), 'warning');
        $output .= $this->show_dates_available();
        $output .= $this->course_continue_button();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Show the message shown when the activity is not viewable.
     *
     * @return string
     */
    public function show_notviewable() {
        $output = $this->notification($this->ec->get_string('notviewable'), 'warning');
        $output .= $this->show_dates_viewable();
        $output .= $this->course_continue_button();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Return a continue button that links back to the course page.
     *
     * @return string
     */
    public function course_continue_button() {
        $url = new moodle_url('/course/view.php', ['id' => $this->ec->course->id]);
        return $this->output->continue_button($url);
    }

    /**
     * Show a list of availability time restrictions
     */
    public function show_dates_available() {
        return $this->show_dates('activity', ['open', 'close']);
    }

    /**
     * Show a list of viewable time restrictions
     */
    public function show_dates_viewable() {
        return $this->show_dates('viewable', ['open', 'close']);
    }

    /**
     * Show a list of timing restrictions
     *
     * @param string $type the type of dates (e.g. 'activity', 'viewable')
     * @param array $suffixes the date field suffixes to show (e.g. 'open', 'close')
     * @return string
     */
    public function show_dates($type, $suffixes) {
        $output = [];

        $fmt = 'timeondate';
        $fmt = $this->ec->get_string($fmt);

        foreach ($suffixes as $suffix) {
            $name = $type . $suffix;
            if (empty($this->ec->$name)) {
                continue;
            }
            $date = userdate($this->ec->$name, $fmt);
            $date = html_writer::tag('b', $date);
            if ($this->ec->$name < $this->ec->time) {
                $prefix = 'past';
            } else {
                $prefix = 'future';
            }
            $output[] = $this->ec->get_string($prefix . $name, $date);
        }

        if (empty($output)) {
            return '';
        } else {
            $output = html_writer::alist($output);
            return $this->output->box($output, 'englishcentral_timing');
        }
    }

      /**
       * Show the EC progress element
       */
    public function show_progress() {
        $progress = $this->ec->get_progress();
        $percent = $this->compute_overall_progress_percent($progress);

        $output = '';
        $output .= $this->output->box_start('englishcentral_progress', 'id_progresscontainer');
        $output .= $this->show_progress_timing();
        $output .= $this->show_progress_titlecharts($percent, $progress);
        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Compute the overall progress percentage across all goals that have been set.
     *
     * @param object $progress The progress data object.
     * @return int The overall progress percentage.
     */
    private function compute_overall_progress_percent($progress) {
        $percent = 0;
        $divisor = 0;
        if ($this->ec->watchgoal_set()) {
            $percent += max(0, min($progress->watch, $this->ec->watchgoal));
            $divisor += $this->ec->watchgoal;
        }
        if ($this->ec->learngoal_set()) {
            $percent += max(0, min($progress->learn, $this->ec->learngoal));
            $divisor += $this->ec->learngoal;
        }
        if ($this->ec->speakgoal_set()) {
            $percent += max(0, min($progress->speak, $this->ec->speakgoal));
            $divisor += $this->ec->speakgoal;
        }
        if ($this->ec->chatgoal_set()) {
            $percent += max(0, min($progress->chat, $this->ec->chatgoal));
            $divisor += $this->ec->chatgoal;
        }
        if ($percent) {
            $percent = round(100 * $percent / $divisor, 0);
        }
        return $percent;
    }

    /**
     * Show the "your progress" heading and the activity/video open/close timing.
     *
     * @return string
     */
    private function show_progress_timing() {
        $timing = '';
        if ($open = ($this->ec->videoopen ? $this->ec->videoopen : $this->ec->activityopen)) {
            $timing .= html_writer::tag('dt', $this->ec->get_string('from'));
            $timing .= html_writer::tag('dd', userdate($open));
        }
        if ($close = ($this->ec->videoclose ? $this->ec->videoclose : $this->ec->activityclose)) {
            $timing .= html_writer::tag('dt', $this->ec->get_string('until'));
            $timing .= html_writer::tag('dd', userdate($close));
        }
        if ($timing) {
            $timing = html_writer::tag('dl', $timing);
        }
        $timing = html_writer::tag('h4', $this->ec->get_string('yourprogress'), ['class' => 'title']) . $timing;
        return html_writer::tag('div', $timing, ['class' => 'timing']);
    }

    /**
     * Show the overall-progress titlechart and one per goal that has been set.
     *
     * @param int $percent The overall progress percentage.
     * @param object $progress The progress data object.
     * @return string
     */
    private function show_progress_titlecharts($percent, $progress) {
        $output = html_writer::start_tag('div', ['class' => 'titlechart-container']);
        $output .= $this->show_titlechart('total', $percent, '%', 'achieved', $percent);
        if ($this->ec->watchgoal_set()) {
            $output .= $this->show_titlechart_type('watch', $progress);
        }
        if ($this->ec->learngoal_set()) {
            $output .= $this->show_titlechart_type('learn', $progress);
        }
        if ($this->ec->speakgoal_set()) {
            $output .= $this->show_titlechart_type('speak', $progress);
        }
        if ($this->ec->chatgoal_set() && $this->ec->chatmode_enabled()) {
            $output .= $this->show_titlechart_type('chat', $progress);
        }
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Show a title chart for a given goal type using progress data.
     *
     * @param string $type The goal type (watch, learn, speak or chat).
     * @param object $progress The progress data object.
     * @return string
     */
    public function show_titlechart_type($type, $progress) {
        $num = intval($progress->$type);
        $div = intval($this->ec->{$type . 'goal'});
        if ($div == 0) {
            $percent = 0;
        } else {
            $percent = round(100 * $num / $div);
        }
        return $this->show_titlechart($type, $num, " / $div", $type . 'goalunits', $percent);
    }

    /**
     * Show a titled chart with a heading and the chart itself.
     *
     * @param string $type The goal type.
     * @param string $text1 The primary text displayed in the chart.
     * @param string $text2 The secondary text displayed in the chart.
     * @param string $string The language string key for the chart label.
     * @param int $percent The percentage value for the chart.
     * @return string
     */
    public function show_titlechart($type, $text1, $text2, $string, $percent) {
        $title = $this->ec->get_string($type . 'goal');
        $help = $this->help_icon($type . 'goal', $this->ec->plugin);
        $title = html_writer::tag('h4', $title . $help, ['class' => 'title']);
        $chart = $this->show_chart($type, $text1, $text2, $string, $percent);
        return html_writer::tag('div', $title . $chart, ['class' => 'titlechart']);
    }

    /**
     * Show a circular progress chart.
     *
     * @param string $type The goal type.
     * @param string $text1 The primary text displayed in the chart.
     * @param string $text2 The secondary text displayed in the chart.
     * @param string $string The language string key for the chart label.
     * @param int $percent The percentage value for the chart.
     * @return string
     */
    public function show_chart($type, $text1, $text2, $string, $percent) {
        $output = '';

        // Outer ring.
        $params = ['class' => 'outerring',
                        'style' => $this->get_chart_transform($percent)];
        $output .= html_writer::tag('div', '', $params);

        // Start innertext.
        $output .= html_writer::start_tag('div', ['class' => 'innertext']);

        // Line1.
        $output .= html_writer::start_tag('div', ['class' => 'line1']);
        $output .= html_writer::tag('span', $text1, ['class' => 'text1']);
        $output .= html_writer::tag('span', $text2, ['class' => 'text2']);
        $output .= html_writer::end_tag('div');

        // Line2.
        $output .= html_writer::tag('div', $this->ec->get_string($string), ['class' => 'line2']);

        // End innertext.
        $output .= html_writer::end_tag('div');

        $params = ['class' => "chart $type " . $this->get_chart_class($percent)];
        return html_writer::tag('div', $output, $params);
    }

    /**
     * Return the CSS transform style for the chart ring based on a percentage.
     *
     * @param int $percent The percentage value.
     * @return string
     */
    public function get_chart_transform($percent) {
        switch (true) {
            case ($percent < 0):
                $percent = 0;
                break;
            case ($percent > 100):
                $percent = 100;
                break;
        }
        $degrees = round(360 * $percent / 100);
        if ($percent >= 50) {
            $degrees -= 180;
        }
        return 'transform: rotate(' . $degrees . 'deg);';
    }

    /**
     * Return the CSS class for the chart based on a percentage.
     *
     * @param int $percent The percentage value.
     * @return string
     */
    public function get_chart_class($percent) {
        if ($percent >= 50) {
            return 'over50';
        } else {
            return 'under50';
        }
    }

    /**
     * Show the EC videos element
     */
    public function show_videos() {
        $output = '';
        $output .= $this->output->box_start('englishcentral_videos');

        $attempts = $this->ec->get_attempts();

        // Get video ids in this EC activity.
        $connectionavailable = true;
        if ($videoids = $this->ec->get_videoids()) {
            // Fetch video info from EC server.
            if ($videos = $this->auth->fetch_dialog_list($videoids)) {
                // Build index to map videoid onto $videos item.
                $index = [];
                foreach ($videos as $i => $video) {
                    if (isset($video->dialogID)) {
                        $index[$video->dialogID] = $i;
                    }
                }

                // Extract names of count/complete $fields.
                $fields = $this->ec->get_attempts_fields(false);
                $fields = explode(',', $fields);

                // Create video thumbnails in required order.
                foreach ($videoids as $videoid) {
                    if (array_key_exists($videoid, $index)) {
                        $video = $videos[$index[$videoid]];
                        $empty = empty($attempts[$videoid]);
                        foreach ($fields as $field) {
                            $video->$field = ($empty ? 0 : $attempts[$videoid]->$field);
                        }
                        $output .= $this->show_video($video);
                    }
                }
            } else {
                $connectionavailable = false;
            }
        } else {
            $output .= html_writer::tag('p', $this->ec->get_string('novideos'), ['class' => 'ec-novideos-label']);
        }

        if (has_capability('mod/englishcentral:manage', $this->ec->context)) {
            $initiallyvisible = $videoids;
            $output .= $this->show_removevideo_icon($initiallyvisible);
        }

        if ($connectionavailable == false) {
            $output .= html_writer::tag('p', $this->ec->get_string('noconnection'));
        }

        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Show a single video thumbnail element.
     *
     * @param object $video The video data object.
     * @return string
     */
    public function show_video($video) {
        $output = '';
        $difficulty = $this->determine_video_difficulty($video);

        // Remove leading 00: from duration.
        if (substr($video->duration, 0, 3) == '00:') {
            $video->duration = substr($video->duration, 3);
        }

        $output .= html_writer::start_tag('div', ['class' => 'activity-thumbnail']);

        $output .= html_writer::start_tag('div', ['class' => 'thumb-outline']);

        $params = ['class' => 'activity-title', 'data-url' => $video->dialogURL];
        if ($this->should_show_video_details() && isset($video->videoDetailsURL)) {
            $params['data-video-details-url'] = $video->videoDetailsURL;
        }
        $output .= html_writer::tag('span', $video->title, $params);

        $params = ['class' => 'thumb-frame',
                        'data-url' => $video->dialogURL,
                        'data-demopicurl' => $video->demoPictureURL,
                        'style' => 'background-image: url("' . $video->thumbnailURL . '");',
                        'description' => $video->description,
                        'topics' => $this->extract_first_video_topic($video),
                    ];

        $output .= html_writer::start_tag('span', $params);

        $params = ['class' => 'play-icon'];
        $output .= html_writer::tag('span', '', $params);

        $output .= $this->show_video_status($video);

        $output .= html_writer::end_tag('span');

        if ($this->ec->showlevelnumber || $this->ec->showleveltext) {
            $params = ['class' => 'difficulty-level-indicator ' . $difficulty];
            $output .= html_writer::start_tag('span', $params);

            if ($this->ec->showlevelnumber) {
                $label = $this->ec->get_string('levelx', $video->difficulty);
                $params = ['class' => 'difficulty-level text-center'];
                $output .= html_writer::tag('span', $label, $params);
            }
            if ($this->ec->showleveltext) {
                $label = $this->ec->get_string($difficulty);
                $params = ['class' => 'difficulty-label'];
                $output .= html_writer::tag('span', $label, $params);
            }
            $output .= html_writer::end_tag('span');
        }

        if ($this->ec->showduration) {
            $label = $video->duration;
            $params = ['class' => 'duration'];
            $output .= html_writer::tag('span', $label, $params);
        }

        $output .= html_writer::end_tag('div'); // Activity-outline.

        $output .= html_writer::end_tag('div'); // Activity-thumbnail.

        return $output;
    }

    /**
     * Determine the difficulty band ('beginner'/'intermediate'/'advanced') for a video.
     *
     * @param object $video The video data object.
     * @return string
     */
    private function determine_video_difficulty($video) {
        switch (true) {
            case ($video->difficulty <= 2):
                return 'beginner';
            case ($video->difficulty <= 4):
                return 'intermediate';
            case ($video->difficulty >= 5):
                return 'advanced';
            default:
                return '';
        }
    }

    /**
     * Whether video details should be shown to the current user, based on the
     * activity's "showdetails" setting (nobody/students only/teachers only/both)
     * and the current user's role.
     *
     * @return bool
     */
    private function should_show_video_details() {
        if (!$this->ec->showdetails) {
            return false;
        }
        $isstudent = has_capability('mod/englishcentral:view', $this->ec->context);
        $isteacher = has_capability('mod/englishcentral:addinstance', $this->ec->context);
        switch ($this->ec->showdetails) {
            case 1:
                return $isstudent && !$isteacher;
            case 2:
                return !$isstudent && $isteacher;
            case 3:
                return $isstudent || $isteacher;
            default:
                return false;
        }
    }

    /**
     * Extract the first topic name from a video's topics data, which may be a
     * plain array of topic objects or (in some API responses) a nested array.
     *
     * @param object $video The video data object.
     * @return string The first topic name, or an empty string if there are none.
     */
    private function extract_first_video_topic($video) {
        $topics = $video->topics;
        if (empty($topics)) {
            return '';
        }
        if (is_array($topics[0])) {
            return reset($topics[0]);
        }
        return $topics[0]->name;
    }

    /**
     * Show the status indicators for a video.
     *
     * @param object $video The video data object.
     * @return string
     */
    public function show_video_status($video) {
        $output = '';
        if (isset($video->watchcomplete) && $video->watchcomplete) {
            $output .= html_writer::tag('span', $video->watchcomplete, ['class' => 'watch-status completed']);
            $output .= html_writer::tag('span', $video->learncount, ['class' => 'learn-status']);
            $output .= html_writer::tag('span', $video->speakcount, ['class' => 'speak-status']);
            $output .= html_writer::tag('span', $video->chatcount, ['class' => 'chat-status']);
        } else if (isset($video->watchcount) && $video->watchcount) {
            // We could try a fancy unicode char, core_text::code2utf8(0x27eb).
            $output .= html_writer::tag('span', '~', ['class' => 'watch-status inprogress']);
        }
        return $output;
    }

    // This method is not used,.
    // Nor is the addvideo icon.

    /**
     * Show the add video icon.
     *
     * @return string
     */
    protected function show_addvideo_icon() {
        return $this->show_videos_icon('add');
    }

    /**
     * Show the remove video icon.
     *
     * @param bool $initiallyvisible Whether the icon is initially visible.
     * @return string
     */
    protected function show_removevideo_icon($initiallyvisible = true) {
        return $this->show_videos_icon('remove', $initiallyvisible);
    }

    /**
     * Show a video action icon of the given type.
     *
     * @param string $type The icon type (add or remove).
     * @param bool $initiallyvisible Whether the icon is initially visible.
     * @return string
     */
    protected function show_videos_icon($type, $initiallyvisible = true) {
        $text = $this->ec->get_string($type . 'video');
        if (method_exists($this, 'image_url')) {
            $imageurl = 'image_url'; // Moodle >= 3.3.
        } else {
            $imageurl = 'pix_url'; // Moodle <= 3.2.
        }
        $imageurl = $this->$imageurl($type . 'video', $this->ec->plugin);
        $image = html_writer::empty_tag('img', ['src' => $imageurl, 'title' => $text]);
        $removetext = html_writer::tag('span', $this->ec->get_string('removevideo'), ['class' => 'remove-text']);
        $removeicon = html_writer::tag('div', '', ['class' => 'remove-icon']);
        $help = $this->ec->get_string($type . 'videohelp');
        $help = html_writer::tag('span', $help, ['class' => 'videohelp']);
        $hidden = $initiallyvisible ? '' : ' page-mod-englishcentral-hide';
        return html_writer::tag(
            'div',
            $image . $removeicon . $removetext . $help,
            ['class' => 'videoicon ' . $type . 'video' . $hidden]
        );
    }

    /**
     * Show the progress report for all users in the activity.
     *
     * @param int $dayslimit Limit results to attempts within this many days.
     * @return string
     */
    public function show_progress_report($dayslimit = 0) {
        $this->setup_sort();
        $url = $this->ec->get_report_url();

        [$groupmenu, $groupid] = $this->fetch_progress_report_groupinfo($url);
        $items = $this->fetch_progress_report_items($dayslimit, $groupid);
        $goals = $this->compute_progress_report_goals($items);
        $items = $this->finalize_progress_report_items($items, $goals);

        $output = $this->show_progress_report_heading($url, $goals);
        foreach ($items as $item) {
            $output .= $this->show_progress_report_item($item, $goals);
        }

        if (count($items)) {
            $output = html_writer::tag('dl', $output, ['class' => 'userbars']);
        } else {
            $output = html_writer::tag('p', $this->ec->get_string('noprogressreport'));
        }

        if ($groupmenu) {
            $output = $groupmenu . $output;
        }

        return $output;
    }

    /**
     * Fetch the group menu HTML and currently-selected group id for the progress report.
     *
     * @param \moodle_url $url The report page URL.
     * @return array [$groupmenu, $groupid]
     */
    private function fetch_progress_report_groupinfo($url) {
        if (!groups_get_activity_groupmode($this->ec->cm)) {
            return ['', 0];
        }
        $groupmenu = groups_print_activity_menu($this->ec->cm, $url, true);
        $groupid = groups_get_activity_group($this->ec->cm);
        return [$groupmenu, $groupid];
    }

    /**
     * Fetch the per-user aggregate attempt totals for the progress report.
     *
     * @param int $dayslimit Limit results to attempts within this many days.
     * @param int $groupid Limit results to this group, or 0 for no group restriction.
     * @return array Aggregate items keyed by userid.
     */
    private function fetch_progress_report_items($dayslimit, $groupid) {
        global $DB, $CFG;

        // Create SQL to fetch aggregate items from the EC attempts table.
        $select = 'userid,' .
                  'SUM(watchcomplete) + SUM(learncount) + SUM(speakcount) + SUM(chatcount) AS percent,' .
                  'SUM(watchcomplete) AS watch,' .
                  'SUM(learncount) AS learn,' .
                  'SUM(speakcount) AS speak,' .
                  'SUM(chatcount) AS chat';
        $from   = '{englishcentral_attempts}';
        $where  = 'ecid = ?';
        $params = [$this->ec->id];

        // Days limit WHERE condition.
        if ($dayslimit > 0) {
            // Calculate the unix timestamp X days ago.
            // 86400 = 24 hours * 60 minutes * 60 seconds.
            $dayslimitinseconds = time() - ($dayslimit * 86400);
            $dayslimitcondition = " AND timecreated >= ?";
            $where .= $dayslimitcondition;
            $params['dayslimit'] = $dayslimitinseconds;
        }

        if ($groupid) {
            $where .= ' AND userid IN (SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid = ?)';
            $params[] = $groupid;
        }
        $where = "$where GROUP BY userid";

        $from   = "(SELECT $select FROM $from WHERE $where) items," .
                  '{user} u';
        $where  = 'items.userid = u.id';

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

        return $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params) ?: [];
    }

    /**
     * Compute the study goals for the progress report: the maximum earned in the
     * given items, overridden by the teacher-specified goals if any are set.
     *
     * @param array $items Aggregate items keyed by userid, as fetched by fetch_progress_report_items().
     * @return object The goals object, with watch/learn/speak/chat/total properties.
     */
    private function compute_progress_report_goals($items) {
        $goals = (object)['watch' => 0, 'learn' => 0, 'speak' => 0, 'chat' => 0];

        // Set goals to maximum in these aggregate items.
        foreach ($items as $item) {
            $goals->watch = max($goals->watch, $item->watch);
            $goals->learn = max($goals->learn, $item->learn);
            $goals->speak = max($goals->speak, $item->speak);
            $goals->chat = max($goals->chat, $item->chat);
        }

        // Override goals with teacher-specified goals, if available.
        if ($this->ec->watchgoal + $this->ec->learngoal + $this->ec->speakgoal + $this->ec->chatgoal) {
            $goals->watch = intval($this->ec->watchgoal);
            $goals->learn = intval($this->ec->learngoal);
            $goals->speak = intval($this->ec->speakgoal);
            $goals->chat = intval($this->ec->chatgoal);
        }

        $goals->total = ($goals->watch + $goals->learn + $goals->speak + $goals->chat);
        return $goals;
    }

    /**
     * Show the progress report's heading row: sortable fullname/percent columns and
     * the proportional watch/learn/speak/chat goal bars.
     *
     * @param \moodle_url $url The report page URL.
     * @param object $goals The goals object, as returned by compute_progress_report_goals().
     * @return string
     */
    private function show_progress_report_heading($url, $goals) {
        $type = 'firstname';
        $fullname = get_string($type, 'moodle');
        $fullname .= $this->get_sort_icon($url, $type);

        $fullname .= ' ';

        $type = 'lastname';
        $fullname .= get_string('lastname', 'moodle');
        $fullname .= $this->get_sort_icon($url, $type);
        $fullname = html_writer::tag('span', $fullname, ['class' => 'fullname']);

        $type = 'percent';
        $percent = '%';
        $percent .= $this->get_sort_icon($url, $type);
        $percent = html_writer::tag('span', $percent, ['class' => 'percent']);

        $output = html_writer::tag('dt', $fullname . $percent, ['class' => 'user title']);

        $title = '';
        $left = 0;
        foreach (['watch', 'learn', 'speak', 'chat'] as $type) {
            if ($goals->$type) {
                $text = $this->ec->get_string($type . 'goal');
                $sort = $this->get_sort_icon($url, $type);
                $percent = (100 * min(1, $goals->$type / $goals->total));
                $style = "margin-left: $left%; width: $percent%;";
                $params = ['class' => $type, 'style' => $style];
                $title .= html_writer::tag('span', $text . ' ' . $sort, $params);
                $left += $percent;
            }
        }
        $output .= html_writer::tag('dd', $title, ['class' => 'bars title']);
        return $output;
    }

    /**
     * Compute each item's total/percent against the goals, and sort by percent if
     * that is the currently selected sort field.
     *
     * @param array $items Aggregate items keyed by userid.
     * @param object $goals The goals object, as returned by compute_progress_report_goals().
     * @return array The updated (and possibly re-sorted) items.
     */
    private function finalize_progress_report_items($items, $goals) {
        foreach ($items as $userid => $item) {
            $item->total = (min($goals->watch, $item->watch) +
                            min($goals->learn, $item->learn) +
                            min($goals->speak, $item->speak) +
                            min($goals->chat, $item->chat));
            if ($goals->total == 0) {
                $item->percent = '';
            } else {
                $item->percent = round(100 * min(1, $item->total / $goals->total)) . '%';
            }
            $items[$userid] = $item;
        }

        if ($this->sort == 'percent') {
            uasort($items, [$this, 'uasort_percent']);
        }

        return $items;
    }


    /**
     * Comparison callback used to sort report items by percentage.
     *
     * @param object $a The first item to compare.
     * @param object $b The second item to compare.
     * @return int
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
     * Show a single row in the progress report.
     *
     * @param object $item The user item data.
     * @param object $goals The goals data object.
     * @return string
     */
    protected function show_progress_report_item($item, $goals) {
        $output = '';
        $output .= html_writer::tag('dt', $this->show_progress_report_user($item), ['class' => 'user']);
        $output .= html_writer::tag('dd', $this->show_progress_report_bars($item, $goals), ['class' => 'bars']);
        return $output;
    }

    /**
     * Show the user name and percentage for a progress report row.
     *
     * @param object $item The user item data.
     * @return string
     */
    protected function show_progress_report_user($item) {
        $output = '';
        $output .= html_writer::tag('span', fullname($item), ['class' => 'fullname']);
        $output .= html_writer::tag('span', $item->percent, ['class' => 'percent']);
        return $output;
    }

    /**
     * Show the set of progress bars for a progress report row.
     *
     * @param object $item The user item data.
     * @param object $goals The goals data object.
     * @return string
     */
    protected function show_progress_report_bars($item, $goals) {
        $output = '';
        $output .= $this->show_progress_report_bar($item, $goals, 'watch');
        $output .= $this->show_progress_report_bar($item, $goals, 'learn');
        $output .= $this->show_progress_report_bar($item, $goals, 'speak');
        $output .= $this->show_progress_report_bar($item, $goals, 'chat');
        return $output;
    }

    /**
     * Show a single progress bar of a given type for a progress report row.
     *
     * @param object $item The user item data.
     * @param object $goals The goals data object.
     * @param string $type The goal type (watch, learn, speak or chat).
     * @return string
     */
    protected function show_progress_report_bar($item, $goals, $type) {
        if (empty($goals->$type)) {
            return '';
        }

        $text = $item->$type . ' / ' . $goals->$type;
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
        $text = html_writer::tag('span', $text, ['class' => 'text', 'title' => $title]);

        if (empty($item->$type)) {
            $bar = '';
        } else {
            $value = min($item->$type, $goals->$type);
            $width = (100 * min(1, $value / $goals->$type)) . '%;';
            $params = ['class' => 'bar', 'style' => 'width: ' . $width];
            $bar = html_writer::tag('span', '', $params);
        }

        $width = (100 * min(1, $goals->$type / $goals->total)) . '%';
        $params = ['class' => $type, 'style' => 'width: ' . $width];

        return html_writer::tag('span', $bar . $text, $params);
    }

    /**
     * Set the sort item/order
     */
    protected function setup_sort() {
        global $SESSION;

        // Initialize session info.
        if (empty($SESSION->englishcentral)) {
            $SESSION->englishcentral = new stdClass();
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
     * Return a sort icon link for a report column.
     *
     * @param moodle_url $url The base URL for the sort link.
     * @param string $sort The sort field this icon represents.
     * @return string
     */
    protected function get_sort_icon($url, $sort) {
        $order = ($sort == $this->sort) ? $this->order : ''; // Empty string means unsorted.

        [$text, $icon] = $this->determine_sort_icon_details($sort, $order);
        $this->apply_sort_link_params($url, $sort, $order);

        $text = get_string($text, 'grades');
        $icon = $this->output->pix_icon($icon, $text, 'moodle', ['class' => 'sorticon']);

        return html_writer::link($url, $icon, ['title' => $text]);
    }

    /**
     * Determine the language string key and icon to show for a report column's sort
     * indicator, given the column and its current sort order (if any).
     *
     * @param string $sort The sort field this icon represents.
     * @param string $order The current sort order for this field ('ASC', 'DESC' or '').
     * @return array [$text, $icon]
     */
    private function determine_sort_icon_details($sort, $order) {
        switch (true) {
            case ($order == 'ASC'):
                return ['sortdesc', 't/sort_asc'];
            case ($order == 'DESC'):
                return ['sortasc', 't/sort_desc'];
            case ($sort == 'firstname'):
            case ($sort == 'lastname'):
                return ["sortby$sort", 't/sort'];
            default:
                return ['sort', 't/sort'];
        }
    }

    /**
     * Apply the sort/order params to the given URL for a report column's sort link.
     *
     * @param \moodle_url $url The base URL for the sort link, updated in place.
     * @param string $sort The sort field this icon represents.
     * @param string $order The current sort order for this field ('ASC', 'DESC' or '').
     * @return void
     */
    private function apply_sort_link_params($url, $sort, $order) {
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
    }

    /**
     * Show the EC videos element
     */
    public function show_search() {
        $output = '';
        if (has_capability('mod/englishcentral:manage', $this->ec->context)) {
            // Start the settings form.
            $output .= html_writer::start_tag('form', ['class' => 'search-form']);
            $output .= html_writer::tag('dt', $this->ec->get_string('videosearch'), ['class' => 'visible', 'id' => 'search-label']);
            $output .= html_writer::start_tag('dl', ['class' => 'search-fields']);

            // Text box size.
            $size = ''; // 30
            $output .= html_writer::start_tag('div', ['id' => 'search-fields-main']);
            $output .= $this->show_search_term('searchterm', $size);
            $output .= $this->show_search_button('searchbutton');
            $output .= html_writer::end_tag('div');
            $output .= html_writer::start_tag('div', ['id' => 'search-fields-advanced']);
            $output .= $this->show_search_level('level'); // Level maps to difficulty.
            $output .= html_writer::end_tag('div');

            // End settings/form.
            $output .= html_writer::end_tag('dl');
            $output .= html_writer::end_tag('form');

            // Enclose settings in search-box.
            $output = html_writer::tag('div', $output, ['class' => 'search-box']);

            // Append element to display search-results.
            $output .= html_writer::tag('div', '', ['class' => 'search-results']);

            // Enclose search-box and search-results in container.
            $output = html_writer::tag('div', $output, ['id' => 'search-inner-container']);

            $output .= html_writer::tag('div', '', ['id' => 'close-search-button']);
            // Append element to display button-alike-behavior.

            $output .= html_writer::start_tag('div', ['id' => 'faux-search-button']);
            $output .= html_writer::start_tag('div', ['class' => 'faux-search-button-icon']);
            $output .= html_writer::end_tag('div');
            $output .= html_writer::tag('span', $this->ec->get_string('addvideo'), ['class' => 'faux-search-button-text']);
            $output .= html_writer::end_tag('div');

            // Enclose search-box and search-results in container.
            $output = html_writer::tag('div', $output, ['id' => 'id_searchcontainer']);

            // Append element to display search-results.
            $output .= html_writer::tag('div', '', ['class' => 'add-video-box']);
        }
        return $output;
    }

    /**
     * Show the search term input field.
     *
     * @param string $name The input field name.
     * @param string $size The input field size.
     * @return string
     */
    public function show_search_term($name, $size = '') {
        $output = '';
        $params = ['type' => 'text',
                        'name' => $name,
                        'id' => 'id_' . $name,
                        'placeholder' => $this->ec->get_string('videosearchprompt')];
        if ($size) {
            $params['size'] = $size;
        }
        $output .= html_writer::tag('dd', html_writer::empty_tag('input', $params), ['class' => 'visible']);
        return $output;
    }

    /**
     * Show the search topics input field.
     *
     * @param string $name The input field name.
     * @param string $size The input field size.
     * @return string
     */
    public function show_search_topics($name, $size = '') {
        $output = '';
        $params = ['type' => 'text',
                        'name' => $name,
                        'id' => 'id_' . $name];
        if ($size) {
            $params['size'] = $size;
        }
        $output .= html_writer::tag('dt', $this->ec->get_string('topics'));
        $output .= html_writer::tag('dd', html_writer::empty_tag('input', $params));
        return $output;
    }

    /**
     * Show the search level checkbox group.
     *
     * @param string $name The input field name.
     * @return string
     */
    public function show_search_level($name) {
        $output = '';
        $output .= html_writer::tag('dt', $this->ec->get_string($name));
        $output .= html_writer::start_tag('dd');
        $output .= html_writer::start_tag('div', ['class' => "checkboxgroup $name"]);
        for ($i = 1; $i <= 7; $i++) {
            $output .= html_writer::start_tag('div', ['class' => "checkboxitem $name-$i"]);
            $id = 'id_' . $name . '_' . $i;
            $params = ['type'  => 'checkbox',
                            'name'  => $name . '[]',
                            'value' => $i,
                            'id'    => $id];
            $output .= html_writer::empty_tag('input', $params);
            $output .= html_writer::tag('label', $i, ['for' => $id]);
            $output .= html_writer::end_tag('div');
        }
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('dd');
        return $output;
    }

    /**
     * Show the search duration checkbox group.
     *
     * @param string $name The input field name.
     * @return string
     */
    public function show_search_duration($name) {
        $output = '';
        $output .= html_writer::tag('dt', get_string('duration', 'search'));
        $output .= html_writer::start_tag('dd');
        $output .= html_writer::start_tag('div', ['class' => "checkboxgroup $name"]);
        for ($i = 1; $i <= 3; $i++) {
            $output .= html_writer::start_tag('div', ['class' => "checkboxitem $name-$i"]);
            $id = 'id_' . $name . '_' . $i;
            $params = ['type'  => 'checkbox',
                            'name'  => $name . '[]',
                            'value' => $i,
                            'id'    => $id];
            $output .= html_writer::empty_tag('input', $params);
            $output .= html_writer::tag('label', $this->ec->get_string("duration$i"), ['for' => $id]);
            $output .= html_writer::end_tag('div');
        }
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('dd');
        return $output;
    }

    /**
     * Show the search copyright input field.
     *
     * @param string $name The input field name.
     * @param string $size The input field size.
     * @return string
     */
    public function show_search_copyright($name, $size) {
        $output = '';
        $params = ['type' => 'text',
                        'name' => $name,
                        'size' => $size,
                        'id' => 'id_' . $name];
        $output .= html_writer::tag('dt', $this->ec->get_string($name));
        $output .= html_writer::tag('dd', html_writer::empty_tag('input', $params));
        return $output;
    }

    /**
     * Show the search submit button.
     *
     * @param string $name The button name.
     * @return string
     */
    public function show_search_button($name) {
        $output = '';
        $output .= html_writer::start_tag('dd', ['class' => 'visible']);
        $params = ['type' => 'submit',
                        'name' => $name,
                        'id' => 'id_' . $name,
                        'class' => 'btn btn-primary',
                        'value' => get_string('search')];
        $output .= html_writer::empty_tag('input', $params);
        $output .= html_writer::tag('a', get_string('showadvanced', 'form'), ['class' => 'search-advanced']);
        $output .= html_writer::end_tag('dd');
        return $output;
    }


    /**
     * Create a container for the EC player.
     *
     * @param bool $hidden whether the player container starts hidden
     * @param bool $mimichat whether mimichat mode is enabled
     * @return string
     */
    public function show_player($hidden = false, $mimichat = false) {
        $data = [];
        $data['mimichat'] = $mimichat;
        if ($hidden) {
            $data['display'] = 'page-mod-englishcentral-hide';
        } else {
            $data['display'] = '';
        }
        return $this->render_from_template('mod_englishcentral/showplayer', $data);
    }

    /*
    * Developer tools for generating random data etc
    */
    /**
     * Build the developer tools page items for generating random data etc.
     *
     * @param int $cmid The course module id.
     * @param int $moduleid The module instance id.
     * @return array
     */
    public function developerpage($cmid, $moduleid) {
        $items = [];
        // Update gradebook.
        $items[] = get_string('updateallgrades_details', constants::M_COMPONENT);
        $gradesbtn = new \single_button(
            new \moodle_url(constants::M_URL . '/developer.php', ['action' => 'updategrades', 'id' => $cmid, 'n' => $moduleid]),
            get_string('updateallgrades', constants::M_COMPONENT),
            'get'
        );
        $gradesbtn->add_confirm_action(get_string('updategradesconfirm', constants::M_COMPONENT));
        $items[] = $this->render($gradesbtn);
        $items[] = '<br/><br/>';
        $items[] = get_string('generateattemptdata_details', constants::M_COMPONENT);
        $gendatabtn = new \single_button(
            new \moodle_url(constants::M_URL . '/developer.php', ['action' => 'generatedata', 'id' => $cmid, 'n' => $moduleid]),
            get_string('generateattemptdata', constants::M_COMPONENT),
            'get'
        );
        $gendatabtn->add_confirm_action(get_string('generateattemptsconfirm', constants::M_COMPONENT));
        $items[] = $this->render($gendatabtn);
        return $items;
    }
}
