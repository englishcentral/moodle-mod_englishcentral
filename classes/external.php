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
 * External.
 *
 * @package    mod_englishcentral
 * @author  Justin Hunt - poodll.com
 * @copyright  2014 onwards Justin Hunt; 2024 onwards EnglishCentral
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
// This is for pre M4.0 and post M4.0 to work on same code base
require_once($CFG->libdir . '/externallib.php');

/*
 * This is for M4.0 and later
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
*/

use mod_englishcentral\utils;
use mod_englishcentral\constants;


/**
 * External class.
 *
 * @package mod_englishcentral
 * @author  Justin Hunt - Poodll.com
 */
class mod_englishcentral_external extends external_api {
    /**
     * Describe the parameters for add_video.
     *
     * @return external_function_parameters
     */
    public static function add_video_parameters() {
        return new external_function_parameters([
            'ecid' => new external_value(PARAM_INT),
            'videoid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Add a video to the activity.
     *
     * @param int $ecid The englishcentral activity id.
     * @param int $videoid The video id to add.
     * @return bool True on success.
     */
    public static function add_video($ecid, $videoid) {
        $ret = utils::add_video($ecid, $videoid);
        if ($ret) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Describe the return value for add_video.
     *
     * @return external_value
     */
    public static function add_video_returns() {
        return new external_value(PARAM_BOOL);
    }
}
