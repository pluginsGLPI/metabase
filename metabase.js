/**
 * -------------------------------------------------------------------------
 * Metabase plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Metabase.
 *
 * Metabase is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Metabase is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Metabase. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2018-2022 by Metabase plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/metabase
 * -------------------------------------------------------------------------
 */

$(function() {

   // do like a jquery toggle but based on a parameter
   $.fn.toggleFromValue = function(val) {
      if (val === 1
          || val === "1"
          || val === true) {
         this.show();
      } else {
         this.hide();
      }
   };

   $(document).on("click", ".metabase_collection_list label", function() {
      $(this).toggleClass('expanded');
   });

   $(document).on("click", "a.extract", function() {
      var id = $(this).data('id');
      var type = $(this).data('type');
      $('<div></div>').dialog({
         modal: true,
         open: function (){
            $(this).load(CFG_GLPI.root_doc + '/' + GLPI_PLUGINS_PATH.metabase + '/ajax/extract_json.php', {
               'id': id,
               'type': type
            });
         },
         height: 800,
         width: '80%'
      });
   });
});
