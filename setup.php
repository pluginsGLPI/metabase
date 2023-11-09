<?php

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
 * @copyright Copyright (C) 2018-2023 by Metabase plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/metabase
 * -------------------------------------------------------------------------
 */

define('PLUGIN_METABASE_VERSION', '1.3.3');

// Minimal GLPI version, inclusive
define("PLUGIN_METABASE_MIN_GLPI", "10.0.0");
// Maximum GLPI version, exclusive
define("PLUGIN_METABASE_MAX_GLPI", "10.0.99");

if (!defined("PLUGINMETABASE_DIR")) {
    define("PLUGINMETABASE_DIR", __DIR__);
}
if (!defined("PLUGINMETABASE_REPORTS_DIR")) {
    define("PLUGINMETABASE_REPORTS_DIR", PLUGINMETABASE_DIR . "/reports");
}
if (!defined("PLUGINMETABASE_DASHBOARDS_DIR")) {
    define("PLUGINMETABASE_DASHBOARDS_DIR", PLUGINMETABASE_DIR . "/dashboards");
}

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_metabase()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['metabase'] = true;

   // add autoload for vendor
    include_once(PLUGINMETABASE_DIR . "/vendor/autoload.php");

   // don't load hooks if plugin not enabled (or glpi not logged)
    if (!Plugin::isPluginActive('metabase') || !Session::getLoginUserID()) {
        return true;
    }

   // config page
    Plugin::registerClass('PluginMetabaseConfig', ['addtabon' => 'Config']);
    $PLUGIN_HOOKS['config_page']['metabase'] = 'front/config.form.php';

   // add dashboards
    Plugin::registerClass('PluginMetabaseDashboard', ['addtabon' => 'Central']);

   //display helpdesk menu if self-service and if is able to view at least one dashboard.
    if (
        $_SESSION['glpiactiveprofile']['interface'] == 'helpdesk'
        && PluginMetabaseProfileright::canProfileViewDashboards($_SESSION['glpiactiveprofile']['id'])
    ) {
        $PLUGIN_HOOKS['helpdesk_menu_entry']['metabase'] = '/front/selfservice.php';
        $PLUGIN_HOOKS['helpdesk_menu_entry_icon']['metabase'] = 'ti ti-chart-bar';
    }


   // profile rights management
    Plugin::registerClass('PluginMetabaseProfileright', ['addtabon' => 'Profile']);

   // css & js
    $PLUGIN_HOOKS['add_css']['metabase'] = 'metabase.css';
    $PLUGIN_HOOKS['add_javascript']['metabase'] = 'metabase.js';

   // Encryption
    $PLUGIN_HOOKS['secured_configs']['metabase'] = ['password'];
}


/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_metabase()
{
    return [
        'name'           => 'Metabase',
        'version'        => PLUGIN_METABASE_VERSION,
        'author'         => '<a href="http://www.teclib.com">Teclib\'</a>',
        'license'        => 'GPLv2+',
        'homepage'       => 'https://github.com/pluginsGLPI/metabase',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_METABASE_MIN_GLPI,
                'max' => PLUGIN_METABASE_MAX_GLPI,
            ]
        ]
    ];
}

function plugin_metabase_recursive_remove_empty($haystack)
{
    foreach ($haystack as $key => $value) {
        if (is_array($value)) {
            if (count($value) == 0) {
                unset($haystack[$key]);
            } else {
                $haystack[$key] = plugin_metabase_recursive_remove_empty($haystack[$key]);
            }
        } else if ($haystack[$key] === "") {
            unset($haystack[$key]);
        }
    }

    return $haystack;
}

function metabaseGetIdByField($itemtype = "", $field = "", $value = "")
{
    global $DB;

    $query = "SELECT `id`
             FROM `" . $itemtype::getTable() . "`
             WHERE `$field` = '" . addslashes($value) . "'";
    $result = $DB->query($query);

    if ($DB->numrows($result) == 1) {
        return $DB->result($result, 0, 'id');
    }
    return false;
}
