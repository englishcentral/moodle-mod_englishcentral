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
 * Defines all the backup steps that will be used by {@see backup_englishcentral_activity_task}
 *
 * @package    mod_englishcentral
 * @category    backup
 * @copyright   2014 Justin Hunt <poodllsupport@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the complete webquest structure for backup, with file and id annotations
 *
 * @SuppressWarnings(PHPMD.LongClassName) Name follows Moodle's mandatory
 *   backup_{component}_activity_structure_step convention.
 */
class backup_englishcentral_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the structure of the 'englishcentral' element inside the webquest.xml file
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        // XML nodes declaration - non-user data.

        $fieldnames = ['id', 'course']; // Excluded fields.
        $fieldnames = $this->get_fieldnames('englishcentral', $fieldnames);
        $activity = new backup_nested_element('englishcentral', ['id'], $fieldnames);

        $videos = new backup_nested_element('videos');
        $fieldnames = ['id', 'ecid']; // Excluded fields.
        $fieldnames = $this->get_fieldnames('englishcentral_videos', $fieldnames);
        $video = new backup_nested_element('video', ['id'], $fieldnames);

        // Build the tree in the order needed for restore.

        $activity->add_child($videos);
        $videos->add_child($video);

        // Data sources.

        $activity->set_source_table('englishcentral', ['id' => backup::VAR_ACTIVITYID]);
        $video->set_source_table('englishcentral_videos', ['ecid' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $this->define_user_data_structure($activity);
        }

        // File annotations.

        $activity->annotate_files('mod_englishcentral', 'intro', null);

        // Return the root element, wrapped in a standard activity structure.

        return $this->prepare_activity_structure($activity);
    }

    /**
     * Declares, wires up, sources and annotates the user-data elements
     * (accountids, attempts, phonemes) of the englishcentral activity,
     * as children of the given activity element.
     *
     * @param backup_nested_element $activity The root activity element.
     */
    protected function define_user_data_structure($activity) {
        // XML nodes declaration - user data.

        $accountids = new backup_nested_element('accountids');
        $fieldnames = ['id']; // Excluded fields.
        $fieldnames = $this->get_fieldnames('englishcentral_accountids', $fieldnames);
        $fieldnames[] = 'partnerid'; // Additional field.
        $accountid = new backup_nested_element('accountid', ['id'], $fieldnames);

        $attempts = new backup_nested_element('attempts');
        $fieldnames = ['id', 'ecid']; // Excluded fields.
        $fieldnames = $this->get_fieldnames('englishcentral_attempts', $fieldnames);
        $attempt = new backup_nested_element('attempt', ['id'], $fieldnames);

        $phonemes = new backup_nested_element('phonemes');
        $fieldnames = ['id', 'ecid']; // Excluded fields (keep attemptid).
        $fieldnames = $this->get_fieldnames('englishcentral_phonemes', $fieldnames);
        $phoneme = new backup_nested_element('phoneme', ['id'], $fieldnames);

        // Build the tree in the order needed for restore.

        $activity->add_child($accountids);
        $accountids->add_child($accountid);

        $activity->add_child($attempts);
        $attempts->add_child($attempt);

        $activity->add_child($phonemes);
        $phonemes->add_child($phoneme);

        // Data sources.

        // Accountids (include partnerid in each record).
        $partnerid = $this->get_backup_site_partnerid();
        if ($partnerid) {
            [$sql, $params] = $this->get_accountids_userids($this->get_setting_value(backup::VAR_ACTIVITYID));
            $sql = "SELECT *, $partnerid AS partnerid " .
                   'FROM {englishcentral_accountids} ' .
                   "WHERE accountid > 0 AND userid $sql";
            $accountid->set_source_sql($sql, $params);
        }

        // Attempts.
        $params = ['ecid' => backup::VAR_PARENTID];
        $attempt->set_source_table('englishcentral_attempts', $params);

        // Phonemes.
        $params = ['ecid' => backup::VAR_PARENTID];
        $phoneme->set_source_table('englishcentral_phonemes', $params);
        // Note that a phoneme should probably be a child of an attempt
        // but we put it as a child of an EC activity for legacy reasons
        // I.e. that's how things were done in earlier versions of this module.

        // Id annotations (foreign keys on non-parent tables).

        $accountid->annotate_ids('user', 'userid');
        $attempt->annotate_ids('user', 'userid');
        $phoneme->annotate_ids('user', 'userid');
    }

    /**
     * Fetch the partnerID configured on the backup site, caching it for the
     * lifetime of the backup. Only site admins have access to this setting.
     *
     * @return int The partnerID, or 0 if the current user cannot access it.
     */
    protected function get_backup_site_partnerid() {
        static $partnerid = null;

        if ($partnerid === null) {
            $partnerid = 0;
            if (has_capability('moodle/site:config', context_system::instance())) {
                $configvalue = get_config('mod_englishcentral', 'partnerid');
                if ($configvalue && is_numeric($configvalue)) {
                    $partnerid = intval($configvalue);
                }
            }
        }

        return $partnerid;
    }

    /**
     * get_fieldnames
     *
     * @uses $DB
     * @param account $tablename the name of the Moodle table (without prefix)
     * @param array $excludedfieldnames these field names will be excluded
     * @return array of field names
     */
    protected function get_fieldnames($tablename, array $excludedfieldnames) {
        global $DB;
        $fieldnames = array_keys($DB->get_columns($tablename));
        return array_diff($fieldnames, $excludedfieldnames);
    }

    /**
     * get_accountids_userids
     *
     * Get userids for all users who have attempted this EnglishCentral activity
     *
     * @uses $DB
     * @param int $ecid the englishcentral activity instance id
     * @return array ($userids, $params) to extract accountids used in this EnglishCentral activity
     */
    protected function get_accountids_userids($ecid) {
        global $DB;

        if ($userids = $DB->get_records_menu('englishcentral_attempts', ['ecid' => $ecid], 'id', 'id,userid')) {
            $userids = array_unique($userids);
        } else {
            $userids = [];
        }

        // Note: we don't put the ids into $params like this:
        // return $DB->get_in_or_equal($userids);
        // because Moodle 2.0 backup expects only backup::VAR_xxx
        // constants, which are all negative, in $params, and will
        // throw an exception for any positive values in $params.
        // - baseelementincorrectfinalorattribute
        // Backup/util/structure/base_final_element.class.php.

        switch (count($userids)) {
            case 0:
                $userids = '< 0';
                break;
            case 1:
                $userids = '= ' . reset($userids);
                break;
            default:
                $userids = 'IN (' . implode(',', $userids) . ')';
        }

        return [$userids, []];
    }
}
