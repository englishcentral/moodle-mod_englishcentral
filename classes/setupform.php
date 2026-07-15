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
 * Setup Form for englishcentral Activity
 *
 * @package    mod_englishcentral
 * @author     Justin Hunt <poodllsupport@gmail.com>
 * @copyright  (C) 1999 onwards Justin Hunt  http://poodll.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_englishcentral;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use mod_englishcentral\constants;
use mod_englishcentral\utils;

/**
 * Abstract class that item type's inherit from.
 *
 * This is the abstract class that add item type forms must extend.
 *
 * @copyright  2021 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setupform extends \moodleform {
    /**
     * This is used to identify this itemtype.
     * @var string
     */
    public $type;

    /**
     * The simple string that describes the item type e.g. audioitem, textitem
     * @var string
     */
    public $typestring;


    /**
     * An array of options used in the htmleditor
     * @var array
     */
    protected $editoroptions = [];

    /**
     * An array of options used in the filemanager
     * @var array
     */
    protected $filemanageroptions = [];

    /**
     * An array of options used in the filemanager
     * @var array
     */
    protected $moduleinstance = null;


    /**
     * Add the required basic elements to the form.
     *
     * This method adds the basic elements to the form including title and contents
     * and then calls custom_definition();
     */
    final public function definition() {
        global $CFG;

        $mform = $this->_form;
        $context = $this->_customdata['context'];
        utils::add_mform_elements($mform, $context, true);

        // add the action buttons
        $this->add_action_buttons(get_string('cancel'), get_string('savechangesanddisplay'));
    }

    /**
     * Add a filemanager element for uploading media.
     *
     * @param string $name The base element name.
     * @param int $count The count of the element to add, or -1 for none.
     * @param string|null $label The element label, null means default.
     * @param bool $required Whether the element is required.
     * @return void
     */
    final protected function add_media_upload($name, $count = -1, $label = null, $required = false) {
        if ($count > -1) {
            $name = $name . $count;
        }

        $this->_form->addElement(
            'filemanager',
            $name,
            $label,
            null,
            $this->filemanageroptions
        );
    }

    /**
     * Add a filemanager element for uploading the audio prompt.
     *
     * @param string|null $label The element label, null means default.
     * @param bool $required Whether the element is required.
     * @return void
     */
    final protected function add_media_prompt_upload($label = null, $required = false) {
        return $this->add_media_upload(constants::AUDIOPROMPT, -1, $label, $required);
    }


    /**
     * Convenience function: Adds an response editor
     *
     * @param int $count The count of the element to add
     * @param string $label, null means default
     * @param bool $required
     * @return void
     */
    final protected function add_editorarearesponse($count, $label = null, $required = false) {
        if ($label === null) {
            $label = get_string('response', constants::M_COMPONENT);
        }
        $this->_form->addElement(
            'editor',
            constants::TEXTANSWER . $count . '_editor',
            $label,
            ['rows' => '4', 'columns' => '80'],
            $this->editoroptions
        );
        $this->_form->setDefault(constants::TEXTANSWER . $count . '_editor', ['text' => '', 'format' => FORMAT_MOODLE]);
        if ($required) {
            $this->_form->addRule(constants::TEXTANSWER . $count . '_editor', get_string('required'), 'required', null, 'client');
        }
    }
}
