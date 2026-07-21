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
 * Date: 2018/06/26
 * Time: 13:16
 * @package    mod_englishcentral
 * @copyright  2014 onwards Justin Hunt; 2024 onwards EnglishCentral
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_englishcentral\output;

use mod_englishcentral\constants;
use mod_englishcentral\utils;

/**
 * Renderer for englishcentral reports.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods) A renderer class exposing one small,
 *   focused public method per report widget; that's the standard Moodle renderer shape.
 */
class report_renderer extends \plugin_renderer_base {
    /**
     * Render the reports menu.
     *
     * @param object $moduleinstance The englishcentral module instance.
     * @param object $cm The course module.
     * @param int $dayslimit The days limit filter.
     * @param string $format The report format.
     * @return string
     */
    public function render_reportmenu($moduleinstance, $cm, $dayslimit, $format) {
        $reports = [];
        $theurl = new \moodle_url(
            constants::M_URL . '/reports.php',
            ['id' => $cm->id, 'n' => $moduleinstance->id, 'dayslimit' => $dayslimit, 'format' => $format]
        );
        $theurl->param('report', 'attemptssummary');
        $graphicalattempts = new \single_button(
            $theurl,
            get_string('attemptssummaryreport', constants::M_COMPONENT),
            'get'
        );
        $reports[] = ['button' => $this->render($graphicalattempts),
        'text' => get_string('attemptssummaryreport_explanation', constants::M_COMPONENT)];

        $theurl->param('report', 'attempts');
        $attempts = new \single_button(
            $theurl,
            get_string('attemptsreport', constants::M_COMPONENT),
            'get'
        );
        $reports[] = ['button' => $this->render($attempts),
            'text' => get_string('attempts_explanation', constants::M_COMPONENT)];

        $theurl->param('report', 'videoperformance');
        $videoperformance = new \single_button(
            $theurl,
            get_string('videoperformancereport', constants::M_COMPONENT),
            'get'
        );
        $reports[] = ['button' => $this->render($videoperformance),
            'text' => get_string('videoperformance_explanation', constants::M_COMPONENT)];

        $theurl->param('report', 'courseattempts');
        $courseattempts = new \single_button(
            $theurl,
            get_string('courseattemptsreport', constants::M_COMPONENT),
            'get'
        );
        $reports[] = ['button' => $this->render($courseattempts),
            'text' => get_string('courseattempts_explanation', constants::M_COMPONENT)];

        $data = ['reports' => $reports];
        $ret = $this->render_from_template('mod_englishcentral/reportsmenu', $data);

        return $ret;
    }

    /**
     * Render the delete all attempts button.
     *
     * @param object $cm The course module.
     * @return string
     */
    public function render_delete_allattempts($cm) {
        $deleteallbutton = new \single_button(
            new \moodle_url(constants::M_URL . '/manageattempts.php', ['id' => $cm->id, 'action' => 'confirmdeleteall']),
            get_string('deleteallattempts', constants::M_COMPONENT),
            'get'
        );
        $ret = \html_writer::div($this->render($deleteallbutton), constants::M_CLASS . '_actionbuttons');
        return $ret;
    }

    /**
     * Render the report title heading HTML.
     *
     * @param object $course The course object.
     * @param string $username The user name displayed in the title.
     * @return string
     */
    public function render_reporttitle_html($course, $username) {
        $ret = $this->output->heading(format_string($course->fullname), 2);
        $ret .= $this->output->heading(get_string('reporttitle', constants::M_COMPONENT, $username), 3);
        return $ret;
    }

    /**
     * Render the HTML shown when a section has no data.
     *
     * @return string
     */
    public function render_empty_section_html() {
        return $this->output->heading(get_string('nodataavailable', constants::M_COMPONENT), 3);
    }

    /**
     * Render the export buttons HTML.
     *
     * @param object $cm The course module.
     * @param object $formdata The report form data.
     * @param string $showreport The report being shown.
     * @return string
     */
    public function render_exportbuttons_html($cm, $formdata, $showreport) {
        // Convert formdata to array.
        $formdata = (array) $formdata;
        $formdata['id'] = $cm->id;
        $formdata['report'] = $showreport;
        $formdata['format'] = 'csv';
        $excel = new \single_button(
            new \moodle_url(constants::M_URL . '/reports.php', $formdata),
            get_string('exportexcel', constants::M_COMPONENT),
            'get'
        );

        return \html_writer::div($this->render($excel), constants::M_CLASS . '_actionbuttons');
    }

    /**
     * Render the CSV export button for the grading page.
     *
     * @param object $cm The course module.
     * @param object $formdata The submitted form data.
     * @param string $action The grading action.
     * @return string The rendered HTML.
     */
    public function render_grading_exportbuttons_html($cm, $formdata, $action) {
        // Convert formdata to array.
        $formdata = (array) $formdata;
        $formdata['id'] = $cm->id;
        $formdata['action'] = $action;
        $formdata['format'] = 'csv';
        $excel = new \single_button(
            new \moodle_url(constants::M_URL . '/grading.php', $formdata),
            get_string('exportexcel', constants::M_COMPONENT),
            'get'
        );

        return \html_writer::div($this->render($excel), constants::M_CLASS . '_actionbuttons');
    }

    /**
     * Output a report as a downloadable CSV file and terminate the script.
     *
     * @param string $sectiontitle The report section title, used as the file name.
     * @param array $head The report heading fields.
     * @param array $rows The formatted report rows.
     * @param array $fields The list of fields to output for each row.
     * @return void
     * @SuppressWarnings(PHPMD.ExitExpression) A CSV download must terminate the script
     *   before any further page output is sent.
     */
    public function render_report_csv($sectiontitle, $head, $rows, $fields) {

        // Use the sectiontitle as the file name. Clean it and change any non-filename characters to '_'.
        $name = clean_param($sectiontitle, PARAM_FILE);
        $name = preg_replace("/[^A-Z0-9]+/i", "_", utils::super_trim($name));
        $quote = '"';
        $delim = ",";
        $newline = "\r\n";

        header("Content-Disposition: attachment; filename=$name.csv");
        header("Content-Type: text/comma-separated-values");

        // Echo header.
        $heading = "";
        foreach ($head as $headfield) {
            $heading .= $quote . $headfield . $quote . $delim;
        }
        echo $heading . $newline;

        // Echo data rows.
        foreach ($rows as $row) {
            $datarow = "";
            foreach ($fields as $field) {
                $datarow .= $quote . $row->{$field} . $quote . $delim;
            }
            echo $datarow . $newline;
        }
        exit();
    }

    /**
     * Render a report as an HTML table.
     *
     * @param string $report The report identifier.
     * @param array $head The report heading fields.
     * @param array $rows The formatted report rows.
     * @param array $fields The list of fields to output for each row.
     * @return string The rendered HTML.
     */
    public function render_report_tabular($report, $head, $rows, $fields) {
        if (empty($rows)) {
            return $this->render_empty_section_html();
        }

        // Set up our table and head attributes.
        $tableattributes = ['class' => 'generaltable ' . constants::M_CLASS . '_table'];

        $htmltable = new \html_table();
        $tableid = \html_writer::random_id(constants::M_COMPONENT);
        $htmltable->id = $tableid;
        $htmltable->attributes = $tableattributes;

        $headcells = [];
        foreach ($head as $headcell) {
            $headcells[] = new \html_table_cell($headcell);
        }
        $htmltable->head = $head;

        foreach ($rows as $row) {
            $htr = new \html_table_row();
            // Set up descrption cell.
            foreach ($fields as $field) {
                $cell = new \html_table_cell($row->{$field});
                $cell->attributes = ['class' => constants::M_CLASS . '_cell_' . $report . '_' . $field];
                $htr->cells[] = $cell;
            }

            $htmltable->data[] = $htr;
        }

        $html = \html_writer::table($htmltable);

        // If datatables set up datatables.
        $config = get_config(constants::M_COMPONENT);
        if ($config->reportstable == constants::M_USE_DATATABLES) {
            $dtlang = [];
            $dtlang['search'] = get_string('datatables_search', constants::M_COMPONENT);
            $dtlang['emptyTable'] = get_string('datatables_emptytable', constants::M_COMPONENT);
            $dtlang['zeroRecords'] = get_string('datatables_zerorecords', constants::M_COMPONENT);
            $dtlang['paginate'] = [];
            $dtlang['paginate']['first'] = get_string('datatables_paginate_first', constants::M_COMPONENT);
            $dtlang['paginate']['last'] = get_string('datatables_paginate_last', constants::M_COMPONENT);
            $dtlang['paginate']['next'] = get_string('datatables_paginate_next', constants::M_COMPONENT);
            $dtlang['paginate']['previous'] = get_string('datatables_paginate_previous', constants::M_COMPONENT);
            $dtlang['aria'] = [];
            $dtlang['aria']['sortAscending'] = get_string('datatables_aria_sortascending', constants::M_COMPONENT);
            $dtlang['aria']['sortDescending'] = get_string('datatables_aria_sortdescending', constants::M_COMPONENT);
            $dtlang['info'] = get_string('datatables_info', constants::M_COMPONENT);
            $dtlang['infoEmpty'] = get_string('datatables_infoempty', constants::M_COMPONENT);
            $dtlang['lengthMenu'] = get_string('datatables_lengthmenu', constants::M_COMPONENT);

            $tableprops = [];
            $tableprops['paging'] = true;
            $tableprops['pageLength'] = 10;
            $tableprops['language'] = $dtlang;
            $opts = [];
            $opts['tableid'] = $tableid;
            $opts['tableprops'] = $tableprops;
            $this->page->requires->js_call_amd(constants::M_COMPONENT . "/datatables", 'init', [$opts]);
        }
        return $html;
    }

    /**
     * Render the reports page footer, including the return-to-reports link and export buttons.
     *
     * @param object $moduleinstance The module instance.
     * @param object $cm The course module.
     * @param object $formdata The submitted form data.
     * @param string $showreport The report currently being shown.
     * @return string The rendered HTML.
     */
    public function show_reports_footer($moduleinstance, $cm, $formdata, $showreport) {
        // A return to reports top link.
        $link = new \moodle_url(
            constants::M_URL . '/reports.php',
            [
                'report' => 'menu',
                'id' => $cm->id,
                'n' => $moduleinstance->id,
                'dayslimit' => $formdata->dayslimit,
                'format' => $formdata->format,
            ]
        );
        $ret = \html_writer::link(
            $link,
            get_string('returntoreports', constants::M_COMPONENT),
            ['class' => 'mod_ec_returntoreports']
        );
        $ret .= $this->render_exportbuttons_html($cm, $formdata, $showreport);
        return $ret;
    }

    /**
     * Render a selector for the number of attempts shown per page.
     *
     * @param \moodle_url $url The base URL for the selector.
     * @param object $paging The paging object, providing the current perpage value.
     * @return string The rendered HTML.
     */
    public function show_perpage_selector($url, $paging) {
        $options = ['5' => 5, '10' => 10, '20' => 20, '40' => 40, '80' => 80, '150' => 150];
        $selector = new \single_select($url, 'perpage', $options, $paging->perpage);
        $selector->set_label(get_string('attemptsperpage', constants::M_COMPONENT));
        return $this->render($selector);
    }

    /**
     * Render the days-limit and format selectors for the user report.
     *
     * @param \moodle_url $url The base URL for the selectors.
     * @param int $currentdayslimit The currently selected days limit.
     * @param string $currentformat The currently selected format.
     * @return string The rendered HTML.
     */
    public function show_user_report_options($url, $currentdayslimit, $currentformat) {
        $dayslimitselector = $this->fetch_dayslimit_selector($url, $currentdayslimit);
        $formatselector = $this->fetch_format_selector($url, $currentformat);
        return \html_writer::div($formatselector . $dayslimitselector, 'mod_ec_user_report_opts float-right');
    }

    /**
     * Render a selector for the report's days limit.
     *
     * @param \moodle_url $url The base URL for the selector.
     * @param int $currentselection The currently selected days limit.
     * @return string The rendered HTML.
     */
    public function fetch_dayslimit_selector($url, $currentselection) {
        $options = ['0' => get_string('nodayslimit', constants::M_COMPONENT),
            '7' => get_string('xdayslimit', constants::M_COMPONENT, 7),
            '14' => get_string('xdayslimit', constants::M_COMPONENT, 14),
            '30' => get_string('xdayslimit', constants::M_COMPONENT, 30),
            '90' => get_string('xdayslimit', constants::M_COMPONENT, 90),
            '180' => get_string('xdayslimit', constants::M_COMPONENT, 180),
            '365' => get_string('xdayslimit', constants::M_COMPONENT, 365)];
        $theurl = clone $url;
        $theurl->remove_params('dayslimit');
        $selector = new \single_select($theurl, 'dayslimit', $options, $currentselection);
        $widget = $this->render($selector);
        return $widget;
    }

    /**
     * Render the selector for the report's display format (tabular/graphical/combined).
     *
     * @param \moodle_url $url The base URL for the selector.
     * @param string $currentselection The currently selected format.
     * @return string The rendered HTML.
     */
    public function fetch_format_selector($url, $currentselection) {
        $params = [];
        $theurl = clone $url;

        $theurl->param('format', 'tabular');
        $params['tableurl'] = $theurl->out();

        $theurl->param('format', 'graphical');
        $params['charturl'] = $theurl->out();

        $theurl->param('format', 'combined');
        $params['combiurl'] = $theurl->out();

        switch ($currentselection) {
            case "tabular":
                $params['istable'] = true;
                break;
            case "graphical":
                $params['ischart'] = true;
                break;
            case "combined":
            default:
                $params['iscombi'] = true;
                break;
        }

        return  $this->render_from_template('mod_englishcentral/reportformatselector', $params);
    }

    /**
     * Returns HTML to display a single paging bar to provide access to other pages  (usually in a search)
     *
     * @param int $totalcount The total number of entries available to be paged through
     * @param stdclass $paging an object containting sort/perpage/pageno fields. Created in reports.php and grading.php
     * @param string|moodle_url $baseurl url of the current page, the $pagevar parameter is added
     * @return string the HTML to output.
     */
    public function show_paging_bar($totalcount, $paging, $baseurl) {
        $pagevar = "pageno";
        // Add paging params to url (NOT pageno).
        $baseurl->params(['perpage' => $paging->perpage, 'sort' => $paging->sort]);
        return $this->output->paging_bar($totalcount, $paging->pageno, $paging->perpage, $baseurl, $pagevar);
    }

    /**
     * Render the export buttons appropriate for the current report.
     *
     * @param object $cm The course module.
     * @param object $formdata The submitted form data.
     * @param string $showreport The report currently being shown.
     * @return string The rendered HTML.
     */
    public function show_export_buttons($cm, $formdata, $showreport) {
        switch ($showreport) {
            case 'grading':
                return $this->render_grading_exportbuttons_html($cm, $formdata, $showreport);
            default:
                return $this->render_exportbuttons_html($cm, $formdata, $showreport);
        }
    }
}
