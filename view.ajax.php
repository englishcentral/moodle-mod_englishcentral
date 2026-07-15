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
 * AJAX endpoint that serves data for the englishcentral activity view.
 *
 * @package    mod_englishcentral
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

/** Include required files */
require_once('../../config.php');

// Check we are a valid user.
require_sesskey();

// Get expected input params.
$id = optional_param('id', 0, PARAM_INT); // Course_modules id.
// The 'data' param is always sent as an array (or omitted); every consumer
// below guards with is_array(), so an empty array is a safe default.
$data = optional_param_array('data', [], PARAM_RAW);
$action = optional_param('action', '', PARAM_ALPHA);

// Extract key records from DB.
$cm = get_coursemodule_from_id('englishcentral', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('englishcentral', ['id' => $cm->instance], '*', MUST_EXIST);

// Check we are logged in.
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Check we have suitable capability.
if ($action == 'getoptions' || $action == 'storeresults' || $action == 'showstatus') {
    require_capability('mod/englishcentral:view', $context); // Student.
} else {
    require_capability('mod/englishcentral:manage', $context); // Teacher.
}

// Initialize EC activity/auth objects.
$ec = \mod_englishcentral\activity::create($instance, $cm, $course, $context);
$auth = \mod_englishcentral\auth::create($ec);

// Initialize the renderer.
$renderer = $PAGE->get_renderer($ec->plugin);
$renderer->attach_activity_and_auth($ec, $auth);

switch ($action) {
    case 'getoptions':
        echo json_encode([
            'accept1'       => \mod_englishcentral\auth::ACCEPT_V1,
            'consumerkey'   => $auth->consumerkey,
            'sdktoken'      => $auth->get_sdk_token(),
            'sdkmode'       => $auth->get_sdk_mode(),
            'sdkversion'    => $auth->get_sdk_version(),
            'authorization' => $auth->get_authorization(),
            'sitelanguage'  => $auth->get_site_language(),
            'searchurl'     => $auth->get_search_url(),
            'fetchurl'      => $auth->get_fetch_url(),
            'settings'      => $auth->get_player_settings()]);
        break;

    case 'storeresults':
        if (is_array($data) && array_key_exists('dialogID', $data)) {
            $data = (object)$data;
            $dialog = $auth->fetch_dialog_progress($data->dialogID, $data->sdktoken);
            $ec->update_progress($dialog);
            echo $renderer->show_progress();
        }
        break;

    case 'showstatus':
        if (is_array($data) && array_key_exists('dialogID', $data)) {
            $data = (object)$data;
            $attempts = $ec->get_attempts($data->dialogID);
            if ($video = reset($attempts)) {
                echo $renderer->show_video_status($video);
            }
        }
        break;

    case 'addvideo':
        if (is_array($data) && array_key_exists('dialogId', $data)) {
            $data = (object)$data;
            $table = 'englishcentral_videos';
            $videoid = intval($data->dialogId);
            $recordid = \mod_englishcentral\utils::add_video($ec->id, $videoid);
            echo $renderer->show_video($data);
        }
        break;

    case 'sortvideo':
        if (is_array($data) && array_key_exists('dialogId', $data) && array_key_exists('sortorder', $data)) {
            // Sanity check on incoming values.
            $data = (object)$data;
            $targetvideoid = intval($data->dialogId);
            $targetsortorder = intval($data->sortorder);

            if ($targetvideoid && $targetsortorder) {
                // Define DB table name.
                $table = 'englishcentral_videos';

                // Set all sort orders to negative.
                // We need to do this because the DB index requies unique (ecid, sortorder).
                $params = ['ecid' => $ec->id];
                $DB->execute('UPDATE {' . $table . '} SET sortorder = -sortorder WHERE ecid = :ecid', $params);

                if ($videos = $DB->get_records($table, $params, 'sortorder DESC')) {
                    $sortorder = 1;
                    foreach ($videos as $video) {
                        $params = ['id' => $video->id];
                        if (intval($video->videoid) == $targetvideoid) {
                            $DB->set_field($table, 'sortorder', $targetsortorder, $params);
                        } else if ($sortorder >= $targetsortorder) {
                            $DB->set_field($table, 'sortorder', $sortorder + 1, $params);
                            $sortorder++;
                        } else {
                            $DB->set_field($table, 'sortorder', $sortorder, $params);
                            $sortorder++;
                        }
                    }
                }
            }
            // There's nothing to return because the element has been dragged.
            // To the correct position in the browser.
        }
        break;

    case 'removevideo':
        if (is_array($data) && array_key_exists('dialogId', $data)) {
            $data = (object)$data;
            $table = 'englishcentral_videos';
            $DB->delete_records($table, ['ecid' => $ec->id, 'videoid' => intval($data->dialogId)]);
        }
        break;
}
