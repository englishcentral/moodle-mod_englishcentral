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
 * Utils for EnglishCentral plugin
 *
 * @package    mod_englishcentral
 * @copyright  2020 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_englishcentral;

use mod_englishcentral\constants;


/**
 * Utils class holding shared helper functions for mod_englishcentral.
 */
class utils {
    /**
     * Add the standard mod_form elements shared by the module settings and setup tab forms.
     *
     * @param \MoodleQuickForm $mform The form to add elements to.
     * @param object $instance The module instance.
     * @param object $cm The course module.
     * @param object $course The course.
     * @param \context $context The module context.
     * @param bool $setuptab Whether this is being added to the setup tab form.
     * @return void
     */
    public static function add_mform_elements($mform, $instance, $cm, $course, $context, $setuptab = false) {
        global $CFG, $PAGE;

        $plugin = 'mod_englishcentral';
        $config = get_config($plugin);

        $ec = \mod_englishcentral\activity::create($instance, $cm, $course, $context);
        $auth = \mod_englishcentral\auth::create($ec);

        // If this is setup tab we need to add a field to tell it the id of the activity.
        if ($setuptab) {
            $mform->addElement('hidden', 'n');
            $mform->setType('n', PARAM_INT);
        }

        $dateoptions = ['optional' => true];
        $textoptions = ['size' => \mod_englishcentral_mod_form::TEXT_NUM_SIZE];

        $PAGE->requires->js_call_amd("$plugin/form", 'init');

        // -------------------------------------------------------------------------------
        $name = 'general';
        $label = get_string($name, 'form');
        $mform->addElement('header', $name, $label);
        // -------------------------------------------------------------------------------

        // Adding the standard "name" field.
        $name = 'name';
        $label = get_string('activityname', $plugin);
        $mform->addElement('text', $name, $label, ['size' => '64']);
        if (empty($CFG->formatstringstriptags)) {
            $mform->setType($name, PARAM_CLEAN);
        } else {
            $mform->setType($name, PARAM_TEXT);
        }
        $mform->addRule($name, null, 'required', null, 'client');
        $mform->addRule($name, get_string('maximumchars', null, 255), 'maxlength', 255, 'client');
        $mform->addHelpButton($name, 'activityname', $plugin);

        // Adding the standard "intro" and "introformat" fields.
        // Note that we do not support this in tabs.
        if (! $setuptab) {
            $label = get_string('moduleintro');
            $params = [
                'context' => $context,
                'maxfiles' => EDITOR_UNLIMITED_FILES,
                'noclean' => true,
                'subdirs' => true,
            ];
            $mform->addElement('editor', 'introeditor', $label, ['rows' => 10], $params);
            $mform->setType('introeditor', PARAM_RAW); // No XSS prevention here, users must be trusted.
            $mform->addElement('advcheckbox', 'showdescription', get_string('showdescription'));
            $mform->addHelpButton('showdescription', 'showdescription');
        }

        // -----------------------------------------------------------------------------
        $name = 'timing';
        $label = get_string($name, 'form');
        $mform->addElement('header', $name, $label);
        $mform->setExpanded($name, true);
        // -----------------------------------------------------------------------------

        $name = 'activityopen';
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        $mform->addHelpButton($name, $name, $plugin);
        self::set_type_default_advanced($mform, $config, $name, PARAM_INT);

        $name = 'videoopen';
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        $mform->addHelpButton($name, $name, $plugin);
        self::set_type_default_advanced($mform, $config, $name, PARAM_INT);

        $name = 'videoclose';
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        $mform->addHelpButton($name, $name, $plugin);
        self::set_type_default_advanced($mform, $config, $name, PARAM_INT);

        $name = 'activityclose';
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        $mform->addHelpButton($name, $name, $plugin);
        self::set_type_default_advanced($mform, $config, $name, PARAM_INT);

        // -------------------------------------------------------------------------------
        $name = 'goals';
        $label = get_string($name, $plugin);
        $mform->addElement('header', $name, $label);
        $mform->setExpanded($name, true);
        // -------------------------------------------------------------------------------

        $goals = [
            'watchgoal' => 5,
            'learngoal' => 10,
            'speakgoal' => 10,
            'chatgoal'  => 5,
            'studygoal' => 70,
        ];
        // Remove the chat goal unless both chat mode and mimic chat are enabled.
        if (!($ec->chatmode_enabled() && $auth->mimichat_enabled())) {
            unset($goals['chatgoal']);
        }
        foreach ($goals as $goal => $default) {
            $label = get_string($goal, $plugin);
            $units = get_string($goal . 'units', $plugin);
            $elements = [
                    $mform->createElement('text', $goal, '', $textoptions),
                    $mform->createElement('static', '', '', $units),
            ];
            $mform->addElement('group', $goal . 'group', $label, $elements, ' ', false);
            $mform->setType($goal, PARAM_INT);
            $mform->setDefault($goal, $default);
            $mform->addHelpButton($goal . 'group', $goal, $plugin);
        }

        // -----------------------------------------------------------------------------
        $name = 'display';
        $label = get_string($name, 'form');
        $mform->addElement('header', $name, $label);
        $mform->setExpanded($name, true);
        // -----------------------------------------------------------------------------

        $name = 'showduration';
        $label = get_string($name, $plugin);
        $mform->addElement('selectyesno', $name, $label);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, 1);

        self::set_type_default_advanced($mform, $config, $name, PARAM_INT);

        $name = 'showlevelnumber';
        $label = get_string($name, $plugin);
        $mform->addElement('selectyesno', $name, $label);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, 1);

        $name = 'showleveltext';
        $label = get_string($name, $plugin);
        $mform->addElement('selectyesno', $name, $label);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, 1);

        $name = 'showdetails';
        $label = get_string($name, $plugin);
        $options = [get_string('no'),
                         get_string('showtostudentsonly', $plugin),
                         get_string('showtoteachersonly', $plugin),
                         get_string('showtoteachersandstudents', $plugin)];
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, 3);
    }

    /**
     * Set a form field's type, default value and advanced state from the plugin config.
     *
     * @param \MoodleQuickForm $mform The form containing the field.
     * @param object $config The plugin config object.
     * @param string $name The name of the field.
     * @param int $type A PARAM_xxx constant value.
     * @param mixed $default The fallback default value if none is set in config.
     * @return void
     */
    public static function set_type_default_advanced($mform, $config, $name, $type, $default = null) {
        $mform->setType($name, $type);
        if (isset($config->$name)) {
            $mform->setDefault($name, $config->$name);
        } else if ($default) {
            $mform->setDefault($name, $default);
        }
        $advname = 'adv' . $name;
        if (isset($config->$advname)) {
            $mform->setAdvanced($name, $config->$advname);
        }
    }

    /**
     * Get the options for the "reportstable" setting.
     *
     * @return array Options keyed by their constant value.
     */
    public static function fetch_options_reportstable() {
        $options = [constants::M_USE_DATATABLES => get_string("reporttableajax", constants::M_COMPONENT),
            constants::M_USE_PAGEDTABLES => get_string("reporttablepaged", constants::M_COMPONENT)];
        return $options;
    }

    /**
     * Add a video to an activity's video list, if not already present.
     *
     * @param int $ecid The englishcentral activity id.
     * @param int $videoid The video id to add.
     * @return int The id of the video record.
     */
    public static function add_video($ecid, $videoid) {
            global $DB;

            $table = 'englishcentral_videos';
            $record = ['ecid' => $ecid,
                'videoid' => $videoid];
            // Only insert the video if it is not already in our database.
            if ($record['videoid'] != $DB->get_field($table, 'videoid', $record)) {
                if ($sortorder = $DB->get_field($table, 'MAX(sortorder)', ['ecid' => $ecid])) {
                    $sortorder++;
                } else {
                    $sortorder = 1;
                }
                $record['sortorder'] = $sortorder;
                $record['id'] = $DB->insert_record($table, $record);
            }
            return $record['id'];
    }

    /**
     * Trim a string, gracefully handling null input.
     *
     * @param string|null $str The string to trim.
     * @return string The trimmed string, or an empty string if null.
     */
    public static function super_trim($str) {
        if ($str == null) {
            return '';
        } else {
            $str = trim($str);
            return $str;
        }
    }

    /**
     * Check whether a string is valid JSON.
     *
     * @param string $string The string to check.
     * @return bool True if the string is valid JSON.
     */
    public static function is_json($string) {
        if (!$string) {
            return false;
        }
        if (empty($string)) {
            return false;
        }
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}
