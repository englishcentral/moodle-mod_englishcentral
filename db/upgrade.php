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
 * This file keeps track of upgrades to the englishcentral module
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations. The upgrade
 * function in this file will attempt to perform all the necessary actions to
 * upgrade your older installation to the current version. If there's something
 * it cannot do itself, it will tell you what you need to do.  The commands in
 * here will all be database-neutral, using the functions defined in DLL libraries.
 *
 * @package    mod_englishcentral
 * @copyright  2014 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_englishcentral\constants;


/**
 * Execute englishcentral upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_englishcentral_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    $newversion = 2015031501;
    if ($oldversion < $newversion) {
        // Define field timecreated to be added to englishcentral.
        $table = new xmldb_table('englishcentral');
        $field = new xmldb_field('lightboxmode', XMLDB_TYPE_INTEGER, '3', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');

        // Add field lightboxmode.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, "$newversion", 'englishcentral');
    }

    $newversion = 2018012403;
    if ($oldversion < $newversion) {
        require_once($CFG->dirroot . '/mod/englishcentral/db/upgradelib.php');

        // Create USERIDS table.
        // (this will be renamed to ACCOUNTIDS later).

        $table = new xmldb_table('englishcentral_userids');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('ecuserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('engluser_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $table->add_index('engluser_ecuserid', XMLDB_INDEX_UNIQUE, ['ecuserid']);

        xmldb_englishcentral_create_table($dbman, $table);

        // Create VIDEOS table.

        $table = new xmldb_table('englishcentral_videos');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ecid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('videoid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('visible', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('englvide_ecid', XMLDB_INDEX_NOTUNIQUE, ['ecid']);
        $table->add_index('englvide_videoid', XMLDB_INDEX_NOTUNIQUE, ['videoid']);
        $table->add_index('englvide_sortorder', XMLDB_INDEX_NOTUNIQUE, ['ecid,sortorder']);

        xmldb_englishcentral_create_table($dbman, $table);

        // Transfer videoids.

        if ($records = $DB->get_records('englishcentral')) {
            $table = 'englishcentral_videos';
            foreach ($records as $record) {
                if (empty($record->videoid)) {
                    continue;
                }
                $params = ['ecid' => $record->id,
                                'videoid' => $record->videoid];
                if ($DB->record_exists($table, $params)) {
                    continue;
                }
                $DB->insert_record($table, $params);
            }
        }

        // Remove fields from ENGLISHCENTRAL table.

        $table = new xmldb_table('englishcentral');
        $fields = ['videotitle', 'videoid', 'goalperiod',
                        'watchmode', 'speakmode', 'learnmode',
                        'hiddenchallengemode', 'speaklitemode',
                        'lightboxmode', 'simpleui', 'maxattempts'];
        foreach ($fields as $field) {
            $field = new xmldb_field($field);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        // Add fields to ENGLISHCENTRAL table.

        $table = new xmldb_table('englishcentral');
        $fields = [
            new xmldb_field('watchgoal', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0', 'introformat'),
            new xmldb_field('learngoal', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0', 'watchgoal'),
            new xmldb_field('speakgoal', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0', 'learngoal'),
            new xmldb_field('studygoal', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'speakgoal'),
            new xmldb_field('availablefrom', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'studygoal'),
            new xmldb_field('availableuntil', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'availablefrom'),
            new xmldb_field('readonlyfrom', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'availableuntil'),
            new xmldb_field('readonlyuntil', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'readonlyfrom'),
        ];

        foreach ($fields as $field) {
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_type($table, $field);
            } else {
                $dbman->add_field($table, $field);
            }
        }

        // Replace ATTEMPTS table.

        $table = new xmldb_table('englishcentral_attempts');
        $fields = ['englishcentralid' => 'ecid'];
        $oldname = 'englishcentral_attempt';

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ecid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('videoid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('linestotal', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('totalactivetime', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('watchedcomplete', XMLDB_TYPE_INTEGER, '2');
        $table->add_field('activetime', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('datecompleted', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('linesrecorded', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('lineswatched', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('points', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('recordingcomplete', XMLDB_TYPE_INTEGER, '2');
        $table->add_field('sessiongrade', XMLDB_TYPE_CHAR, '255');
        $table->add_field('sessionscore', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('englatte_ecid', XMLDB_INDEX_NOTUNIQUE, ['ecid']);
        $table->add_index('englatte_userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('englatte_videoid', XMLDB_INDEX_NOTUNIQUE, ['videoid']);

        xmldb_englishcentral_replace_table($dbman, $table, $fields, $oldname);

        // Replace PHONEMES table.

        $table = new xmldb_table('englishcentral_phonemes');
        $fields = ['englishcentralid' => 'ecid'];
        $oldname = 'englishcentral_phs';

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ecid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('phoneme', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('badcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('goodcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('englphs_ecid', XMLDB_INDEX_NOTUNIQUE, ['ecid']);
        $table->add_index('englphs_attemptid', XMLDB_INDEX_NOTUNIQUE, ['attemptid']);
        $table->add_index('englphs_userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        xmldb_englishcentral_replace_table($dbman, $table, $fields, $oldname);

        upgrade_mod_savepoint(true, "$newversion", 'englishcentral');
    }

    $newversion = 2018012805;
    if ($oldversion < $newversion) {
        $config = get_config('englishcentral');
        foreach ($config as $name => $value) {
            set_config($name, $value, 'mod_englishcentral');
            unset_config($name, 'englishcentral');
        }

        upgrade_mod_savepoint(true, "$newversion", 'englishcentral');
    }

    $newversion = 2018020417;
    if ($oldversion < $newversion) {
        // Rename timing fields in main "englishcentral" table.
        $table = new xmldb_table('englishcentral');
        $fields = [
            'activityopen'  => new xmldb_field('availablefrom', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'),
            'activityclose' => new xmldb_field('availableuntil', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'),
            'videoopen'     => new xmldb_field('readonlyfrom', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'),
            'videoclose'    => new xmldb_field('readonlyuntil', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'),
        ];
        foreach ($fields as $newname => $field) {
            $oldexists = $dbman->field_exists($table, $field);
            $newexists = $dbman->field_exists($table, $newname);
            if ($oldexists) {
                if ($newexists) {
                    $dbman->drop_field($table, $field);
                    $oldexists = false;
                } else {
                    $dbman->rename_field($table, $field, $newname);
                    $newexists = true;
                }
            }
            $field->setName($newname);
            if ($newexists) {
                $dbman->change_field_type($table, $field);
            } else {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, "$newversion", 'englishcentral');
    }

    $newversion = 2018021020;
    if ($oldversion < $newversion) {
        require_once($CFG->dirroot . '/mod/englishcentral/db/upgradelib.php');

        // Create ACCOUNTIDS table.

        $table = new xmldb_table('englishcentral_accountids');
        $fields = ['ecuserid' => 'accountid'];
        $oldname = 'englishcentral_userids';

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('accountid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('engluser_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Use NOTUNIQUE, because initially the accountid is set to "0" for all users
        // Later, it gets set to a unique non-zero value.
        $table->add_index('engluser_accountid', XMLDB_INDEX_NOTUNIQUE, ['accountid']);

        xmldb_englishcentral_replace_table($dbman, $table, $fields, $oldname);

        // Adjust VIDEOS table.

        $table = new xmldb_table('englishcentral_videos');

        // Remove videotitle field.
        $field = new xmldb_field('videotitle');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Add visible field.
        $field = new xmldb_field('visible', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'videoid');
        if (! $dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add sortorder field.
        $field = new xmldb_field('sortorder', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0', 'visible');
        // Add the field only if it does not already exist.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            // Define new index on sortorder field.
            $index = new xmldb_index('englvide_sortorder', XMLDB_INDEX_UNIQUE, ['ecid,sortorder']);

            // Remove index, if it already exists.
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }

            // Set sortorder field on existing records.
            $ecid = 0;
            $sortorder = 0;
            if ($videos = $DB->get_records($table->getName(), [], 'ecid,id')) {
                foreach ($videos as $video) {
                    if ($ecid && $ecid == $video->ecid) {
                        $sortorder++;
                    } else {
                        $sortorder = 1;
                    }
                    $ecid = $video->ecid;
                    $DB->set_field($table->getName(), 'sortorder', $sortorder, ['id' => $video->id]);
                }
            }

            // Add index on sortorder.
            $dbman->add_index($table, $index);
        }

        upgrade_mod_savepoint(true, "$newversion", 'englishcentral');
    }

    $newversion = 2018022532;
    if ($oldversion < $newversion) {
        require_once($CFG->dirroot . '/mod/englishcentral/db/upgradelib.php');

        // Define table englishcentral_attempts to be created.
        $table = new xmldb_table('englishcentral_attempts');

        // Define modified  field names (OLD => NEW).
        $fields = [
            'lineswatched'      => 'watchcount',
            'watchedcomplete'   => 'watchcomplete',
            'linestotal'        => 'speaktotal',
            'linesrecorded'     => 'speakcount',
            'recordingcomplete' => 'speakcomplete',
            'points'            => 'totalpoints',
            'totalactivetime'   => 'totaltime',
            'datecompleted'     => 'timecompleted',
        ];

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ecid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('videoid', XMLDB_TYPE_INTEGER, '10');

        $table->add_field('watchcomplete', XMLDB_TYPE_INTEGER, '2');
        $table->add_field('watchtotal', XMLDB_TYPE_INTEGER, '10'); // Number of watchable lines.
        $table->add_field('watchcount', XMLDB_TYPE_INTEGER, '10'); // Number of lines watched.
        $table->add_field('watchlineids', XMLDB_TYPE_TEXT);          // Comma-separated list of line ids.

        $table->add_field('learncomplete', XMLDB_TYPE_INTEGER, '2');
        $table->add_field('learntotal', XMLDB_TYPE_INTEGER, '10'); // Number of learnable words.
        $table->add_field('learncount', XMLDB_TYPE_INTEGER, '10'); // Number of words learned.
        $table->add_field('learnwordids', XMLDB_TYPE_TEXT);          // Comma-separated list of word ids.

        $table->add_field('speakcomplete', XMLDB_TYPE_INTEGER, '2');
        $table->add_field('speaktotal', XMLDB_TYPE_INTEGER, '10'); // Number of speakable lines.
        $table->add_field('speakcount', XMLDB_TYPE_INTEGER, '10'); // Number of lines spoken.
        $table->add_field('speaklineids', XMLDB_TYPE_TEXT);          // Comma-separated list of line ids.

        $table->add_field('totalpoints', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('sessiongrade', XMLDB_TYPE_CHAR, '255'); // EC grade (e.g. "A").
        $table->add_field('sessionscore', XMLDB_TYPE_INTEGER, '10'); // EC numeric score (e.g. 97).

        $table->add_field('activetime', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('totaltime', XMLDB_TYPE_INTEGER, '10');

        $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // The following fields doesn't seem to be necessary.
        $table->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');

        // Keys for englishcentral_attempts.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Indexes for englishcentral_attempts.
        $table->add_index('englatte_ecid', XMLDB_INDEX_NOTUNIQUE, ['ecid']);
        $table->add_index('englatte_userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('englatte_videoid', XMLDB_INDEX_NOTUNIQUE, ['videoid']);

        // Create/modify the table.
        xmldb_englishcentral_create_table($dbman, $table, $fields);

        upgrade_mod_savepoint(true, "$newversion", 'englishcentral');
    }

    $newversion = 2018022735;
    if ($oldversion < $newversion) {
        require_once($CFG->dirroot . '/mod/englishcentral/lib.php');

        // Update/create grades for all EC activities.

        // Set up sql strings.
        $strupdating = get_string('updatinggrades', 'mod_englishcentral');
        $select = 'ec.*, cm.idnumber AS cmidnumber';
        $from   = '{englishcentral} ec, {course_modules} cm, {modules} m';
        $where  = 'ec.id = cm.instance AND cm.module = m.id AND m.name = ?';
        $params = ['englishcentral'];

        // Get previous record index (if any).
        $configname = 'updategrades';
        $configvalue = get_config('mod_englishcentral', $configname);
        if (is_numeric($configvalue)) {
            $i_min = intval($configvalue);
        } else {
            $i_min = 0;
        }

        if ($i_max = $DB->count_records_sql("SELECT COUNT('x') FROM $from WHERE $where", $params)) {
            if ($rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where", $params)) {
                if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
                    $bar = false;
                } else {
                    $bar = new progress_bar('englishcentralupgradegrades', 500, true);
                }
                $i = 0;
                foreach ($rs as $ec) {
                    // Update grade.
                    if ($i >= $i_min) {
                        upgrade_set_timeout(); // Apply for more time (3 mins).
                        englishcentral_update_grades($ec);
                    }

                    // Update progress bar.
                    $i++;
                    if ($bar) {
                        $bar->update($i, $i_max, $strupdating . ": ($i/$i_max)");
                    }

                    // Update record index.
                    if ($i > $i_min) {
                        set_config($configname, $i, 'mod_englishcentral');
                    }
                }
                $rs->close();
            }
        }

        // Delete the record index.
        unset_config($configname, 'mod_englishcentral');

        upgrade_mod_savepoint(true, "$newversion", 'englishcentral');
    }

    $newversion = 2018030651;
    if ($oldversion < $newversion) {
        // Select attempts records whose ecid + videoid does not exist in videos table.
        $select = 'ea.*';
        $from   = '{englishcentral_attempts} ea ' .
                  'LEFT JOIN {englishcentral_videos} ev ON ea.ecid = ev.ecid AND ea.videoid = ev.videoid';
        $where  = 'ea.ecid = ? AND ev.id IS NULL';
        $params = [1]; // This issue only affects attempts with ecid==1.

        // SELECT ea.* FROM mdl_englishcentral_attempts ea
        // LEFT JOIN mdl_englishcentral_videos ev
        // ON ea.ecid = ev.ecid
        // AND ea.videoid = ev.videoid
        // WHERE ea.ecid = 1
        // AND ev.id IS NULL;.
        if ($orphans = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
            $fields = ['watchcount' => 'watchlineids',
                            'learncount' => 'learnwordids',
                            'speakcount' => 'speaklineids'];
            foreach ($orphans as $orphan) {
                // Merge all attempts by this user at this video.
                // Try to locate a valid $ecid while we're at it.
                $ecid = 0;
                $record = null; // New attempt.
                $table = 'englishcentral_attempts';
                $params = ['userid' => $orphan->userid,
                                'videoid' => $orphan->videoid];
                $attempts = $DB->get_records($table, $params, 'id');
                foreach ($attempts as $attempt) {
                    if ($record === null) {
                        $record = clone($attempt);
                        foreach ($fields as $field) {
                            $record->$field = [];
                        }
                    } else {
                        // Remove this $attempt.
                        $DB->delete_records($table, ['id' => $attempt->id]);
                    }
                    // Transfer attempt details.
                    foreach ($fields as $field) {
                        $record->$field += array_fill_keys(explode(',', $attempt->$field), 1);
                    }
                    if ($ecid == 0) {
                        $ecid = ($attempt->ecid == $orphan->ecid ? 0 : $attempt->ecid);
                    }
                }
                foreach ($fields as $count => $field) {
                    $record->$field = array_keys($record->$field);
                    $record->$field = array_filter($record->$field);
                    $record->$count = count($record->$field);
                    $record->$field = implode(',', $record->$field);
                }
                if ($ecid == 0) {
                    if ($ecid = $DB->get_records('englishcentral_videos', ['videoid' => $orphan->videoid])) {
                        $ecid = reset($ecid);
                        $ecid = $ecid->ecid;
                    } else {
                        $ecid = 0; // Shouldn't happen !!
                    }
                }
                if ($ecid) {
                    $record->ecid = $ecid;
                    $DB->update_record($table, $record);
                } else {
                    // Sorry, we couldn't rescue this orphan :-(.
                    // Probably because we have no record of its videoid.
                    $DB->delete_records($table, ['id' => $record->id]);
                }
            }
        }

        upgrade_mod_savepoint(true, "$newversion", 'englishcentral');
    }

    $newversion = 2018041763;
    if ($oldversion < $newversion) {
        // Remove all attempts where userid OR videoid IS NULL.
        $DB->delete_records_select('englishcentral_attempts', 'userid IS NULL OR userid = ?', [0]);
        $DB->delete_records_select('englishcentral_attempts', 'videoid IS NULL OR videoid = ?', [0]);

        // Remove duplicate attempts with same userid + videoid.
        // NOTE: the old version of this module kept ALL attempts
        // and used the "status" field to denote old (status=0)
        // Or latest (status=1) attempts.
        $table = 'englishcentral_attempts';
        $select = $DB->sql_concat('userid', "'_'", 'videoid');
        $select = "MIN(id) AS minid, $select AS ids, COUNT(*) AS countrecords";
        $from   = '{' . $table . '}';
        $group  = 'userid, videoid';
        $having = 'countrecords > ?';
        $params = [1];
        $records = "SELECT $select FROM $from GROUP BY $group HAVING $having";
        if ($records = $DB->get_records_sql($records, $params)) {
            foreach ($records as $record) {
                [$userid, $videoid] = explode('_', $record->ids);
                $params = ['userid' => $userid,
                                'videoid' => $videoid];
                $ids = $DB->get_records($table, $params, 'id DESC');
                $ids = array_keys($ids);
                array_shift($ids); // I.e. keep newest record.
                [$select, $params] = $DB->get_in_or_equal($ids);
                $DB->delete_records_select($table, "id $select", $params);
            }
        }
        upgrade_mod_savepoint(true, "$newversion", 'englishcentral');
    }

    $newversion = 2018042565;
    if ($oldversion < $newversion) {
        // Add custom completion fields for EnglishCentral module.
        $table = new xmldb_table('englishcentral');
        $fields = [
            new xmldb_field('completionmingrade', XMLDB_TYPE_FLOAT, '6,2', null, XMLDB_NOTNULL, null, 0.00),
            new xmldb_field('completionpass', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0),
            new xmldb_field('completiongoals', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0),
        ];
        $previous = '';
        foreach ($fields as $field) {
            if ($previous) {
                $field->setPrevious($previous);
            }
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_type($table, $field);
            } else {
                $dbman->add_field($table, $field);
            }
            $previous = $field->getName();
        }
        upgrade_mod_savepoint(true, "$newversion", 'englishcentral');
    }

    $newversion = 2020032512;
    if ($oldversion < $newversion) {
        // Remove default config settings, because they cause errors in JSDK.
        $params = ['partnerid', 'consumerkey', 'consumersecret', 'encryptedsecret'];
        [$select, $params] = $DB->get_in_or_equal($params);

        $select = 'plugin = ? AND name ' . $select . ' AND ' . $DB->sql_like('value', '?');
        $params = array_merge(['mod_englishcentral'], $params, ['YOUR %']);

        $DB->set_field_select('config_plugins', 'value', '', $select, $params);
        upgrade_mod_savepoint(true, "$newversion", 'englishcentral');
    }

    // Add foriframe option to englishcentral table.
    $newversion = 2021053100;
    if ($oldversion < $newversion) {
        $table = new xmldb_table('englishcentral');

        // Define field items to be added to englishcentral.
        $field = new xmldb_field('foriframe', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);

        // Add richtextprompt field to minilesson table.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, $newversion, 'englishcentral');
    }

    $newversion = 2022010900;
    if ($oldversion < $newversion) {
        // Add custom completion fields for EnglishCentral module.
        $table = new xmldb_table('englishcentral');
        $fields = [
            new xmldb_field('completionmingrade', XMLDB_TYPE_FLOAT, '6,2', null, XMLDB_NOTNULL, null, 0.00),
            new xmldb_field('completionpass', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0),
            new xmldb_field('completiongoals', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0),
        ];
        $previous = '';
        foreach ($fields as $field) {
            if ($previous) {
                $field->setPrevious($previous);
            }
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            $previous = $field->getName();
        }
        upgrade_mod_savepoint(true, $newversion, 'englishcentral');
    }

    $newversion = 2022031827;
    if ($oldversion < $newversion) {
        $table = new xmldb_table('englishcentral');
        $fields = [
            new xmldb_field('showduration', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 1),
            new xmldb_field('showlevelnumber', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 1),
            new xmldb_field('showleveltext', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 1),
            new xmldb_field('showdetails', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 0),
        ];
        $previous = 'studygoal';
        foreach ($fields as $field) {
            if ($previous) {
                $field->setPrevious($previous);
            }
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_type($table, $field);
            } else {
                $dbman->add_field($table, $field);
            }
            $previous = $field->getName();
        }
    }

    $newversion = 2023040432;
    if ($oldversion < $newversion) {
        set_config('progressdials', constants::M_PROGRESSDIALS_TOP, constants::M_COMPONENT);
    }

    $newversion = 2023111237;
    if ($oldversion < $newversion) {
        require_once($CFG->dirroot . '/mod/englishcentral/db/upgradelib.php');

        // Add auth table.
        $table = new xmldb_table('englishcentral_auth');

        // Add fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('created_at', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('secret', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);

        // Add keys and index.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('user_id', XMLDB_INDEX_UNIQUE, ['user_id']);

        // Create table if it does not exist.
        xmldb_englishcentral_create_table($dbman, $table);

        upgrade_mod_savepoint(true, $newversion, 'englishcentral');
    }

    $newversion = 2024060534;
    if ($oldversion < $newversion) {
        $tables = [
            'englishcentral' => [
                new xmldb_field('chatgoal', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0', 'speakgoal'),
            ],
            'englishcentral_attempts' => [
                new xmldb_field('chatcomplete', XMLDB_TYPE_INTEGER, '2', null, null, null, null, 'speaklineids'),
                new xmldb_field('chattotal', XMLDB_TYPE_INTEGER, '10'),
                new xmldb_field('chatcount', XMLDB_TYPE_INTEGER, '10'),
                new xmldb_field('chatquestionids', XMLDB_TYPE_TEXT),
            ],
        ];
        foreach ($tables as $table => $fields) {
            $table = new xmldb_table($table);
            $previous = '';
            foreach ($fields as $field) {
                if ($previous) {
                    $field->setPrevious($previous);
                }
                if ($dbman->field_exists($table, $field)) {
                    $dbman->change_field_type($table, $field);
                } else {
                    $dbman->add_field($table, $field);
                }
                $previous = $field->getName();
            }
        }
        upgrade_mod_savepoint(true, $newversion, 'englishcentral');
    }

    $newversion = 2024060835;
    if ($oldversion < $newversion) {
        // Remove deprecated config settings.
        $config = get_config('mod_englishcentral');
        $names = [
            'hiddenchallengemode', 'lightboxmode',
            'learnmode', 'speakmode', 'watchmode',
            'simpleui', 'speaklitemode',
        ];
        foreach ($names as $name) {
            if (property_exists($config, $name)) {
                unset_config($name, 'mod_englishcentral');
                unset($config->$name);
            }
        }
        upgrade_mod_savepoint(true, $newversion, 'englishcentral');
    }

    $newversion = 2024122047;
    if ($oldversion < $newversion) {
        // Add auth table.
        $table = new xmldb_table('englishcentral_videos');
        $fields = [];
        $fields[] = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 'video name');
        $fields[] = new xmldb_field('detailsjson', XMLDB_TYPE_TEXT, null, null, null, null);
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, $newversion, 'englishcentral');
    }

    $newversion = 2025040342;
    if ($oldversion < $newversion) {
        require_once($CFG->dirroot . '/mod/englishcentral/db/upgradelib.php');
        xmldb_englishcentral_check_structure($dbman);
        upgrade_mod_savepoint(true, $newversion, 'englishcentral');
    }

    return true;
}
