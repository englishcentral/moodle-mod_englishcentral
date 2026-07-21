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
 * Internal library of functions for module English Central
 *
 * All the englishcentral specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_englishcentral
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_englishcentral;

/**
 * Represents a single EnglishCentral activity instance: its availability,
 * URLs, goals, attempts and progress data.
 *
 * @package    mod_englishcentral
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @SuppressWarnings(PHPMD.TooManyPublicMethods) A facade over the activity
 *   instance/course/context/config, exposing many small single-purpose
 *   accessors; splitting it would just relocate the same public surface.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class activity {
    /**
     * @var string The type of the plugin.
     */
    public $plugintype;

    /**
     * @var string The name of the plugin.
     */
    public $pluginname;

    /**
     * @var object The plugin instance.
     */
    public $plugin;

    /**
     * @var object The course module.
     */
    public $cm;

    /**
     * @var object The course instance.
     */
    public $course;

    /**
     * @var object The context instance.
     */
    public $context;

    /**
     * @var int The timestamp.
     */
    public $time;

    /**
     * @var bool Whether the activity is available.
     */
    public $available;

    /**
     * @var bool Whether the activity is viewable.
     */
    public $viewable;

    /**
     * @var array The configuration settings.
     */
    public $config;

    /**
     * @var object The English Central instance.
     */
    public $ecinstance;


    /**
     * construct English Central activity instance
     *
     * @param stdclass $instance a row from the englishcentral table
     * @param stdclass $cm a row from the course_modules table
     * @param stdclass $course a row from the course table
     * @param \context $context the activity context
     */
    public function __construct($instance = null, $cm = null, $course = null, $context = null) {
        global $COURSE;

        $this->plugintype = 'mod';
        $this->pluginname = 'englishcentral';
        $this->plugin = $this->plugintype . '_' . $this->pluginname;

        if ($instance) {
            $this->ecinstance = $instance;
        }

        if ($cm) {
            $this->cm = $cm;
        }

        $this->course = $course ?: $COURSE;
        $this->context = $this->resolve_context($context, $cm, $course);
        $this->time = time();

        $this->available = $this->compute_time_based_availability($this->activityopen, $this->activityclose);
        $this->viewable = $this->compute_time_based_availability($this->videoopen, $this->videoclose);

        $this->config = get_config($this->plugin);
    }

    /**
     * Resolve the activity's context from the given context, course module or course,
     * falling back to the system context if none of those are available.
     *
     * @param \context|null $context The context, if already known.
     * @param stdclass|null $cm A row from the course_modules table.
     * @param stdclass|null $course A row from the course table.
     * @return \context The resolved context.
     */
    private function resolve_context($context, $cm, $course) {
        if ($context) {
            return $context;
        }
        if ($cm) {
            return \context_module::instance($cm->id);
        }
        if ($course) {
            return \context_course::instance($course->id);
        }
        return \context_system::instance();
    }

    /**
     * Determine whether the activity is currently available/viewable, given an open
     * and close timestamp: always true for users who can manage the activity, otherwise
     * true only between the open and close times (when set).
     *
     * @param int|null $opentime The open timestamp, or empty for no open restriction.
     * @param int|null $closetime The close timestamp, or empty for no close restriction.
     * @return bool
     */
    private function compute_time_based_availability($opentime, $closetime) {
        if (has_capability('mod/englishcentral:manage', $this->context)) {
            return true;
        }
        if ($opentime && $opentime > $this->time) {
            return false;
        }
        if ($closetime && $closetime < $this->time) {
            return false;
        }
        return true;
    }

    /**
     * Magic method to get properties.
     *
     * @param string $name The name of the property.
     * @return mixed The value of the property or null if not found.
     */
    public function __get($name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        } else if (property_exists($this->ecinstance, $name)) {
            return $this->ecinstance->$name;
        } else {
            return null;
        }
    }

    /**
     * Creates a new EnglishCentral activity
     *
     * @param stdclass $instance a row from the reader table
     * @param stdclass $cm a row from the course_modules table
     * @param stdclass $course a row from the course table
     * @param \context $context the activity context
     * @return activity the new activity object
     */
    public static function create($instance = null, $cm = null, $course = null, $context = null) {
        return new activity($instance, $cm, $course, $context);
    }

    // Availability API.

    /**
     * Detect if this activity is not available.
     *
     * @return bool TRUE if not available; otherwise FALSE.
     */
    public function not_available() {
        return ($this->available ? false : true);
    }

    /**
     * Detect if this activity is not viewable.
     *
     * @return bool TRUE if not viewable; otherwise FALSE.
     */
    public function not_viewable() {
        return ($this->viewable ? false : true);
    }

    /**
     * Detect if watch goal is set.
     *
     * @return boolean TRUE if watch goal is > 0; otherwise FALSE.
     */
    public function watchgoal_set() {
        return ($this->watchgoal ? true : false);
    }

    /**
     * Detect if learn goal is set.
     *
     * @return boolean TRUE if learn goal is > 0; otherwise FALSE.
     */
    public function learngoal_set() {
        return ($this->learngoal ? true : false);
    }

    /**
     * Detect if speak goal is set.
     *
     * @return boolean TRUE if speak goal is > 0; otherwise FALSE.
     */
    public function speakgoal_set() {
        return ($this->speakgoal ? true : false);
    }

    /**
     * Detect if chat goal is set.
     *
     * @return boolean TRUE if chat goal is > 0; otherwise FALSE.
     */
    public function chatgoal_set() {
        return ($this->chatgoal ? true : false);
    }

    /**
     * Detect if chat mode is enabled for this Moodle site.
     *
     * @return boolean TRUE if chat mode is enabled; otherwise FALSE.
     */
    public function chatmode_enabled() {
        return ($this->config->chatmode ? true : false);
    }

    // URLs API.

    /**
     * Get the URL of the reports page for this activity.
     *
     * @param bool|null $escaped Whether to output the URL escaped for HTML.
     * @param array $params Additional URL parameters.
     * @return \moodle_url|string The reports page URL.
     */
    public function get_report_url($escaped = null, $params = []) {
        return $this->url('reports.php', $escaped, $params);
    }

    /**
     * Get the URL of the developer tools page for this activity.
     *
     * @param bool|null $escaped Whether to output the URL escaped for HTML.
     * @param array $params Additional URL parameters.
     * @return \moodle_url|string The developer tools page URL.
     */
    public function get_developertools_url($escaped = null, $params = []) {
        return $this->url('developer.php', $escaped, $params);
    }

    /**
     * Get the URL of the view page for this activity.
     *
     * @param bool|null $escaped Whether to output the URL escaped for HTML.
     * @param array $params Additional URL parameters.
     * @return \moodle_url|string The view page URL.
     */
    public function get_view_url($escaped = null, $params = []) {
        return $this->url('view.php', $escaped, $params);
    }

    /**
     * Get the URL of the view AJAX endpoint for this activity.
     *
     * @param bool|null $escaped Whether to output the URL escaped for HTML.
     * @param array $params Additional URL parameters.
     * @return \moodle_url|string The view AJAX endpoint URL.
     */
    public function get_viewajax_url($escaped = null, $params = []) {
        return $this->url('view.ajax.php', $escaped, $params);
    }

    /**
     * Get the EnglishCentral video details URL for the current language.
     *
     * This always returns a plain external URL string, not a moodle_url, so
     * (unlike the other *_url() methods) there is no escaped/unescaped form.
     *
     * @return string The video details URL.
     */
    public function get_videoinfo_url() {
        $lang = substr(current_language(), 0, 2);

        // Arabic, Spanish, Hebrew, Japanese, Portuguese, Russian, Thai, Turkish, Vietnamese.
        $localized = ['ar', 'es', 'he', 'ja', 'pt', 'ru', 'th', 'tr', 'vi'];
        if (in_array($lang, $localized)) {
            return "https://$lang.englishcentral.com/videodetails";
        }

        if ($lang === 'zh') { // Chinese.
            return 'https://www.englishcentralchina.com/videodetails';
        }

        return 'https://www.englishcentral.com/videodetails'; // English, and everything else.
    }

    /**
     * Build a URL to a file within this plugin.
     *
     * @param string $filepath The path to the file, relative to the plugin folder.
     * @param bool|null $escaped Whether to output the URL escaped for HTML.
     * @param array $params Additional URL parameters.
     * @return \moodle_url|string The built URL.
     */
    public function url($filepath, $escaped = null, $params = []) {
        if (isset($this->cm)) {
            $params['id'] = $this->cm->id;
        }
        $url = '/' . $this->plugintype . '/' . $this->pluginname . '/' . $filepath;
        $url = new \moodle_url($url, $params);
        if (is_bool($escaped)) {
            $url = $url->out($escaped);
        }
        return $url;
    }

    // Strings API.

    /**
     * Get a language string for this plugin.
     *
     * @param string $name The string identifier.
     * @param mixed $a Additional data for the string.
     * @return string The language string.
     */
    public function get_string($name, $a = null) {
        return get_string($name, $this->plugin, $a);
    }

    // Database API.

    /**
     * Get the video ids configured for this activity.
     *
     * @return array Video ids keyed by record id, ordered by sortorder.
     */
    public function get_videoids() {
        global $DB;
        return $DB->get_records_menu('englishcentral_videos', ['ecid' => $this->id], 'sortorder', 'id,videoid');
    }

    /**
     * Get the EnglishCentral account id of the current user.
     *
     * @return string|false The account id, or false if not found.
     */
    public function get_accountid() {
        global $DB, $USER;
        return $DB->get_field('englishcentral_accountids', 'accountid', ['userid' => $USER->id]);
    }

    /**
     * Get the EnglishCentral account ids of users enrolled in this activity.
     *
     * @param int $groupid Optional group id to restrict the users to.
     * @return array|false Account ids keyed by user id, or false if none found.
     */
    public function get_accountids($groupid = 0) {
        global $DB;
        if ($userids = $this->get_userids($groupid)) {
            [$select, $params] = $DB->get_in_or_equal($userids);
            return $DB->get_records_select_menu('englishcentral_accountids', "userid $select", $params, 'userid, accountid');
        }
        return false;
    }

    /**
     * Get the user ids enrolled in this activity, respecting group mode.
     *
     * @param int $groupid Optional group id to restrict the users to.
     * @return array|false User ids, or false if none found.
     */
    public function get_userids($groupid = 0) {
        global $DB;
        $mode = $this->get_groupmode();
        if ($mode == NOGROUPS || $mode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $this->context)) {
            $users = get_enrolled_users($this->context, 'mod/englishcentral:view', $groupid, 'u.id', 'id');
            if (empty($users)) {
                return false;
            }
            return array_keys($users);
        } else {
            if ($groupid) {
                $select = 'groupid = ?';
                $params = [$groupid];
            } else {
                $groups = groups_get_user_groups($this->course->id);
                if (empty($groups)) {
                    return false;
                }
                [$select, $params] = $DB->get_in_or_equal($groups['0']);
            }
            $users = $DB->get_records_select_menu('group_members', 'groupid ' . $select, $params, 'id, userid');
            if (empty($users)) {
                return false;
            }
            return array_unique($users);
        }
    }

    /**
     * Get the group mode (0=NOGROUPS, 1=VISIBLEGROUPS, 2=SEPARATEGROUPS).
     *
     * @return int The groupmode of this activity or course.
     */
    public function get_groupmode() {
        if ($this->cm) {
            return groups_get_activity_groupmode($this->cm);
        }
        if ($this->course) {
            return groups_get_course_groupmode($this->course);
        }
        return NOGROUPS;
    }

    /**
     * Get the current user's cumulative progress totals for this activity.
     *
     * @return object Progress totals keyed by watch, learn, speak and chat.
     */
    public function get_progress() {
        global $DB, $USER;
        $progress = (object)[
        'watch' => 0,
        'learn' => 0,
        'speak' => 0,
        'chat' => 0,
        ];
        $table = 'englishcentral_attempts';
        $params = ['ecid' => $this->id,
                    'userid' => $USER->id];
        if ($attempts = $DB->get_records($table, $params)) {
            foreach ($attempts as $attempt) {
                $progress->watch += $attempt->watchcomplete;
                $progress->learn += $attempt->learncount;
                $progress->speak += $attempt->speakcount;
                $progress->chat += $attempt->chatcount;
            }
        }
        return $progress;
    }

    /**
     * Update the current user's attempt with progress data from an EC dialog, and trigger events.
     *
     * @param object $dialog JSON data returned from the EC REST call.
     * @return void
     */
    public function update_progress($dialog) {
        global $DB, $USER;

        // Extract/create $attempt.
        $table = 'englishcentral_attempts';
        $params = ['ecid' => $this->id,
                    'userid' => $USER->id,
                    'videoid' => $dialog->dialogID];
        // Reuse the existing attempt if $USER has attempted this video before, otherwise create one.
        $attempt = $DB->get_record($table, $params);
        if (!$attempt) {
            $attempt = (object)$params;
            $attempt->timecreated = $this->time;
        }

        $progress = $this->extract_progress($dialog, $attempt);

        foreach ($progress as $name => $value) {
            $attempt->$name = $value;
        }

        if (empty($attempt->id)) {
            $attempt->id = $DB->insert_record($table, $attempt);
        } else {
            $DB->update_record($table, $attempt);
        }

        // Trigger progress update event.
        $event = \mod_englishcentral\event\progress_updated::create([
        'context' => $this->context,
        'objectid' => $attempt->id,
        'other' => ['ecid' => $this->id],
        ]);
        $event->add_record_snapshot($table, $attempt);
        $event->trigger();

        englishcentral_update_grades($this->ecinstance, $USER->id);
        // Update completion state.
        $completion = new \completion_info($this->course);
        if ($completion->is_enabled($this->cm) && ($this->completiongoals)) {
            $completion->update_state($this->cm, COMPLETION_COMPLETE);
        }
    }

    /**
     * Format data about dialog activities returned from EC ReportCard api
     * e.g. /rest/report/dialog/{dialogID}/progress
     *
     * @param array $dialog JSON data returned from EC REST call
     * @param object $attempt record from "englishcentral_attempts"
     * @return array of $progress data
     */
    public function extract_progress($dialog, $attempt) {
        $progress = $this->build_initial_progress($dialog);
        $progress = $this->merge_attempt_progress_ids($progress, $attempt);
        $progress = $this->apply_dialog_activities($progress, $dialog);
        return $this->finalize_progress_counts($progress);
    }

    /**
     * Build the initial $progress array template for extract_progress(), populated
     * with the dialog's hash and total points, if present.
     *
     * @param object $dialog JSON data returned from EC REST call.
     * @return array The initial $progress array.
     */
    private function build_initial_progress($dialog) {
        // Initialize totals for goals.
        $progress = [
        'dialogID' => $dialog->dialogID,

        'watchcomplete' => 0,
        'watchtotal'    => 0,
        'watchcount'    => 0,
        'watchlineids'  => [], // DialogLineID's of lines watched,.

        'learncomplete' => 0,
        'learntotal'    => 0,
        'learncount'    => 0,
        'learnwordids'  => [], // WordHeadID's of words learned,.

        'speakcomplete' => 0,
        'speaktotal'    => 0,
        'speakcount'    => 0,
        'speaklineids'  => [], // DialogLineID's of lines spoken,.

        'chatcomplete' => 0,
        'chattotal'    => 0,
        'chatcount'    => 0,
        'chatquestionids'  => [], // ChatQuestionID's of chat questions discussed,.

        'totalpoints'   => 0,

        // This info is no longer available.
        'activetime'    => 0,
        'totaltime'     => 0,
        'sessionScore'  => 0,
        'sessionGrade'  => '', // A-F.
        ];

        if (isset($dialog->hash)) {
            $progress['hash'] = $dialog->hash;
        }
        if (isset($dialog->totalPoints)) {
            $progress['totalpoints']  = $dialog->totalPoints;
        }

        return $progress;
    }

    /**
     * Populate the $progress array's *ids fields with values earned in a previous
     * attempt, so this call's newly-earned ids can be merged in on top.
     *
     * @param array $progress The $progress array, as built by build_initial_progress().
     * @param object $attempt record from "englishcentral_attempts".
     * @return array The updated $progress array.
     */
    private function merge_attempt_progress_ids($progress, $attempt) {
        $names = ['watchlineids', 'learnwordids', 'speaklineids', 'chatquestionids'];
        foreach ($names as $thename) {
            if (isset($attempt->$thename) && $attempt->$thename) {
                $progress[$thename] = explode(',', $attempt->$thename);
                $progress[$thename] = array_fill_keys($progress[$thename], 1);
            }
        }
        return $progress;
    }

    /**
     * Accumulate each of the dialog's activities (watch/learn/speak/chat) into the
     * $progress array's completion flags and *ids sets.
     *
     * @param array $progress The $progress array, as updated by merge_attempt_progress_ids().
     * @param object $dialog JSON data returned from EC REST call.
     * @return array The updated $progress array.
     */
    private function apply_dialog_activities($progress, $dialog) {
        // Dialog activities should not be empty, but oddly occasionally it is,
        // so we try to fall back gracefully without killing it for students.
        if (empty($dialog->activities)) {
            return $progress;
        }

        foreach ($dialog->activities as $activity) {
            // ActivityType     : watchActivity / speakActivity.
            // activityID       : 208814
            // activityTypeID   : (see below)
            // activityPoints   : 10
            // activityProgress : 1
            // completed        : 1
            // Grade            : A (speakActivity only ?).

            // Extract DB fields.
            switch ($activity->activityTypeID) {
                case \mod_englishcentral\auth::ACTIVITYTYPE_WATCH: // Value 9.
                case \mod_englishcentral\auth::ACTIVITYTYPE_WATCHCOMPREHENSIONCHOICE: // Value 40.
                    $progress['watchcomplete'] = (empty($activity->completed) ? 0 : 1);
                    foreach ($activity->watchedDialogLines as $line) {
                        $progress['watchlineids'][$line->dialogLineID] = 1;
                    }
                    break;

                case \mod_englishcentral\auth::ACTIVITYTYPE_LEARN: // Value 10.
                    $progress['learncomplete'] = (empty($activity->completed) ? 0 : 1);
                    foreach ($activity->learnedDialogLines as $line) {
                        foreach ($line->learnedWords as $word) {
                            if ($word->completed) {
                                $progress['learnwordids'][$word->wordHeadID] = 1;
                            }
                        }
                    }
                    break;

                case \mod_englishcentral\auth::ACTIVITYTYPE_SPEAK: // Value 11.
                    $progress['speakcomplete'] = (empty($activity->completed) ? 0 : 1);
                    foreach ($activity->spokenDialogLines as $line) {
                        $progress['speaklineids'][$line->dialogLineID] = 1;
                    }
                    break;

                case \mod_englishcentral\auth::ACTIVITYTYPE_CHAT: // Value 55.
                    $progress['chatcomplete'] = (empty($activity->completed) ? 0 : 1);
                    foreach ($activity->submittedQuestionIds as $questionid) {
                        $progress['chatquestionids'][$questionid] = 1;
                    }
                    break;
            }
        }

        return $progress;
    }

    /**
     * Finalize the $progress array's counts: derive each *count from the size of its
     * *ids set, then collapse each *ids set back into a comma-separated string for
     * storage in the englishcentral_attempts table.
     *
     * @param array $progress The $progress array, as updated by apply_dialog_activities().
     * @return array The finalized $progress array.
     */
    private function finalize_progress_counts($progress) {
        $progress['watchcount'] += count($progress['watchlineids']);
        $progress['learncount'] += count($progress['learnwordids']);
        $progress['speakcount'] += count($progress['speaklineids']);
        $progress['chatcount'] += count($progress['chatquestionids']);

        $progress['watchlineids'] = implode(',', array_keys($progress['watchlineids']));
        $progress['learnwordids'] = implode(',', array_keys($progress['learnwordids']));
        $progress['speaklineids'] = implode(',', array_keys($progress['speaklineids']));
        $progress['chatquestionids'] = implode(',', array_keys($progress['chatquestionids']));

        return $progress;
    }

    /**
     * Get the comma-separated list of attempt fields to select.
     *
     * @param bool $addvideoid Whether to include the videoid field.
     * @return string The comma-separated field list.
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function get_attempts_fields($addvideoid = true) {
        $fields = 'watchcount,watchcomplete,' .
              'learncount,learncomplete,' .
              'speakcount,speakcomplete,' .
              'chatcount,chatcomplete';
        if ($addvideoid) {
            $fields = "videoid,$fields";
        }
        return $fields;
    }

    /**
     * Get the current user's attempts for this activity.
     *
     * @param int $videoid Optional video id to restrict the attempts to.
     * @return array The matching attempt records.
     */
    public function get_attempts($videoid = 0) {
        global $DB, $USER;
        $params = ['ecid' => $this->id,
                    'userid' => $USER->id];
        if ($videoid) {
            $params['videoid'] = $videoid;
        }
        $fields = $this->get_attempts_fields();
        if ($attempts = $DB->get_records('englishcentral_attempts', $params, 'id', $fields)) {
            return $attempts;
        } else {
            return [];
        }
    }
}
