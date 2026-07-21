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

declare(strict_types=1);

namespace mod_englishcentral\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass for the forum activity.
 *
 * Class for defining english centrals custom completion rules and fetching the completion statuses
 * of the custom completion rules for a giveninstance and a user.
 *
 * @package    mod_englishcentral
 * @copyright Justin Hunt <poodllsupport@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $userid = $this->userid;

        if (!$ec = $DB->get_record('englishcentral', ['id' => $this->cm->instance])) {
            throw new \moodle_exception('Unable to find EnglishCentral with id ' . $this->cm->instance);
        }

        $course = $DB->get_record('course', ['id' => $this->cm->course], '*', MUST_EXIST);
        $ec = \mod_englishcentral\activity::create($ec, $this->cm, $course);
        $grade = \englishcentral_get_completion_grade($course, $this->cm, $userid, $ec);

        // Decimal (e.g. completionmingrade) fields are returned by MySQL as a string, and since
        // empty('0.0') returns false (!!), we must use numeric comparison. A zero/unset mingrade
        // threshold means the rule is trivially satisfied.
        if ($rule === 'completionmingrade' && (empty($ec->completionmingrade) || floatval($ec->completionmingrade) == 0.0)) {
            $state = true;
        } else {
            $state = \englishcentral_evaluate_completion_condition($rule, $ec, $grade);
        }

        return $state ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return [
            'completionmingrade',
            'completionpass',
            'completiongoals',
        ];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $completionmingrade = $this->cm->customdata['customcompletionrules']['completionmingrade'] ?? 0;

        return [
            'completionmingrade' => get_string('completiondetail:mingrade', 'englishcentral', $completionmingrade),
            'completionpass' => get_string('completiondetail:pass', 'englishcentral'),
            'completiongoals' => get_string('completiondetail:goals', 'englishcentral'),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionmingrade',
            'completionpass',
            'completiongoals',
            'completionusegrade',
        ];
    }
}
