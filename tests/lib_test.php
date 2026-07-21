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
 * Unit tests for mod_englishcentral lib functions.
 *
 * These give the CI pipeline a real `mod_englishcentral_testsuite` to run and
 * guard the core Moodle integration hooks against regressions during upgrades.
 *
 * @package    mod_englishcentral
 * @category   test
 * @copyright  2026 EnglishCentral
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_englishcentral;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/englishcentral/lib.php');

/**
 * @covers ::englishcentral_supports
 */
final class lib_test extends \advanced_testcase {
    /**
     * The feature-support hook must advertise the capabilities the plugin relies
     * on (backup, completion, grading). If a Moodle upgrade removes one of these
     * FEATURE_* constants this test surfaces it immediately.
     */
    public function test_englishcentral_supports(): void {
        $this->resetAfterTest();

        $this->assertTrue(englishcentral_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(englishcentral_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertTrue(englishcentral_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertTrue(englishcentral_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertNull(englishcentral_supports('a_feature_that_does_not_exist'));
    }

    /**
     * Creating an activity instance via the generator must persist a row whose
     * fields round-trip. This exercises englishcentral_add_instance() and the
     * install.xml schema together.
     */
    public function test_add_instance_persists_row(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('englishcentral', [
            'course' => $course->id,
            'name'   => 'E2E test activity',
        ]);

        $this->assertNotEmpty($instance->id);
        $row = $DB->get_record('englishcentral', ['id' => $instance->id]);
        $this->assertSame('E2E test activity', $row->name);
    }
}
