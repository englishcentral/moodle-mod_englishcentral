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
 * mod_englishcentral PHPUnit data generator.
 *
 * Lets tests build activity instances with $this->getDataGenerator()
 * ->create_module('englishcentral', [...]).
 *
 * @package    mod_englishcentral
 * @category   test
 * @copyright  2026 EnglishCentral
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Generator class for mod_englishcentral.
 */
class mod_englishcentral_generator extends testing_module_generator {
    /**
     * Create a new englishcentral activity instance, applying sensible defaults
     * for the fields the module form would otherwise supply.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass the activity instance record
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        $defaults = [
            'name'              => 'EnglishCentral',
            'intro'             => 'Test EnglishCentral activity',
            'introformat'       => FORMAT_HTML,
            'grade'             => 100,
            'watchgoal'         => 3,
            'learngoal'         => 20,
            'speakgoal'         => 10,
            'gradeoptions'      => 0,
        ];
        foreach ($defaults as $field => $value) {
            if (!isset($record->$field)) {
                $record->$field = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }
}
