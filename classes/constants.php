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
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/06/16
 * Time: 19:31
 */

namespace mod_englishcentral;

    defined('MOODLE_INTERNAL') || die();

class constants
{
    // component name, db tables, things that define app
    const M_COMPONENT = 'mod_englishcentral';
    const M_TABLE = 'englishcentral';
    const M_ATTEMPTSTABLE = 'englishcentral_attempts';
    const M_VIDEOSTABLE = 'englishcentral_videos';
    const M_AUTHTABLE = 'englishcentral_auth';
    const M_MODNAME = 'englishcentral';
    const M_URL = '/mod/englishcentral';
    const M_PATH = '/mod/englishcentral';
    const M_CLASS = 'mod_englishcentral';
    const M_PLUGINSETTINGS = '/admin/settings.php?section=modsettingenglishcentral';
    const M_PROGRESSDIALS_TOP = 1;
    const M_PROGRESSDIALS_BOTTOM = 0;
    const M_USE_DATATABLES = 0;
    const M_USE_PAGEDTABLES = 1;
}
