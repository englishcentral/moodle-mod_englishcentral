define(['jquery', 'core/log', 'require'], function($, log, require) {
    "use strict"; // jshint ;_;

    /*
    This file contains class and ID definitions.
     */

    log.debug('Poodll EnglishCentral Teacher Datatables helper: initialising');

    return {
        // Pass in config, amd set up table.
        init: function(props) {
            // Pick up opts from html.
            var that = this;
            // Load the locally bundled DataTables library at runtime. It is loaded
            // via requirejs (rather than as a static AMD dependency) so we can build
            // the plugin-relative URL from M.cfg.wwwroot instead of hard-coding a CDN
            // path. Because the library registers itself with define(['jquery'], ...),
            // requirejs binds it to Moodle's jQuery instance, so $().DataTable() works.
            var dturl = M.cfg.wwwroot + '/mod/englishcentral/thirdparty/datatables/jquery.dataTables.min.js';
            require([dturl], function() {
                var thetable = $('#' + props.tableid);
                that.dt = thetable.DataTable(props.tableprops);
            });
        }
    }; // End of return value.
});
