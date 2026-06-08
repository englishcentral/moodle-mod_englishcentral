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
 * englishcentral module admin settings and defaults
 *
 * @package    mod
 * @subpackage englishcentral
 * @copyright  2014 Justin Hunt poodllsupport@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_englishcentral\constants;
use mod_englishcentral\utils;

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $plugin = 'mod_englishcentral';

    // EnglishCentral API credentials.
    $name = 'partnerid';
    $label = get_string($name, $plugin);
    $explain = get_string($name . 'explain', $plugin);
    $settings->add(new admin_setting_configtext("$plugin/$name", $label, $explain, '', PARAM_TEXT));

    $name = 'consumerkey';
    $label = get_string($name, $plugin);
    $explain = get_string($name . 'explain', $plugin);
    $settings->add(new admin_setting_configtext("$plugin/$name", $label, $explain, '', PARAM_TEXT));

    $name = 'consumersecret';
    $label = get_string($name, $plugin);
    $explain = get_string($name . 'explain', $plugin);
    $settings->add(new admin_setting_configtext("$plugin/$name", $label, $explain, '', PARAM_TEXT));

    $name = 'encryptedsecret';
    $label = get_string($name, $plugin);
    $explain = get_string($name . 'explain', $plugin);
    $settings->add(new admin_setting_configtext("$plugin/$name", $label, $explain, '', PARAM_TEXT));

    // Progress dials options.
    $name = 'progressdials';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    $default = constants::M_PROGRESSDIALS_TOP;
    $options = [constants::M_PROGRESSDIALS_BOTTOM => get_string('progressdials_bottom', constants::M_COMPONENT),
        constants::M_PROGRESSDIALS_TOP => get_string('progressdials_top', constants::M_COMPONENT)];
    $settings->add(new admin_setting_configselect(
        constants::M_COMPONENT . "/$name",
        $label,
        $details,
        $default,
        $options
    ));

    // Chat Mode.
    $name = 'chatmode';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    $settings->add(new admin_setting_configcheckbox(
        constants::M_COMPONENT . "/$name",
        $label,
        $details,
        true
    ));

    // Reports Table.
    $name = 'reportstable';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    $default = constants::M_USE_DATATABLES;
    $options = utils::fetch_options_reportstable();
    $settings->add(new admin_setting_configselect(
        constants::M_COMPONENT . "/$name",
        $label,
        $details,
        $default,
        $options
    ));

    // Advanced section.
    $name = 'advancedsection';
    $label = get_string($name, $plugin);
    $details = get_string($name . '_details', $plugin);
    $settings->add(new admin_setting_heading("$plugin/$name", $label, $details));

    $name = 'developmentmode';
    $label = get_string($name, $plugin);
    $explain = get_string($name . 'explain', $plugin);
    $default = (strpos($CFG->wwwroot, '/localhost/') === false ? 0 : 1);
    $settings->add(new admin_setting_configcheckbox("$plugin/$name", $label, $explain, $default));

    $name = 'playerversion';
    $label = get_string($name, $plugin);
    $explain = get_string($name . 'explain', $plugin);
    $default = get_string($name . 'default', $plugin);
    $options = array('JSDK2' => 'JSDK2', 'JSDK3' => 'JSDK3');
    $settings->add(new admin_setting_configselect("$plugin/$name", $label, $explain, $default, $options));

    $settings->add(new admin_setting_configcheckbox(
        $plugin . '/enablesetuptab',
        get_string('enablesetuptab', $plugin),
        get_string('enablesetuptab_details', $plugin),
        0
    ));

}
