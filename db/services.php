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
 * Services definition.
 *
 * @package    mod_englishcentral
 * @author  Frédéric Massart - FMCorz.net
 * @copyright  2014 onwards Justin Hunt; 2024 onwards EnglishCentral
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = [

    'mod_englishcentral_add_video' => [
        'classname'   => 'mod_englishcentral_external',
        'methodname'  => 'add_video',
        'description' => 'Add a video to the activity',
        'capabilities' => 'mod/englishcentral:manage',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
