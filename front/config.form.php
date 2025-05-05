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

include('../../../inc/includes.php');

if (isset($_REQUEST['create_database'])) {
    Session::checkRight("config", CREATE);
    PluginMetabaseConfig::createGLPIDatabase();
    Html::back();
} elseif (isset($_REQUEST['set_database'])) {
    Session::checkRight("config", UPDATE);
    PluginMetabaseConfig::setExistingDatabase((int) $_REQUEST['db_id']);
    Html::back();
} elseif (isset($_REQUEST['push_json'])) {
    Session::checkRight("config", UPDATE);
    PluginMetabaseConfig::pushReports();
    PluginMetabaseConfig::pushDashboards();
    Html::back();
} elseif (isset($_REQUEST['push_datamodel'])) {
    Session::checkRight("config", UPDATE);
    PluginMetabaseConfig::createDataModel((int) $_REQUEST['glpi_db_id']);
    Html::back();
} else {
    Session::checkRight("config", READ);
    /** @var array $CFG_GLPI */
    Html::redirect($CFG_GLPI['root_doc'] . '/front/config.form.php?forcetab=PluginMetabaseConfig$1');
}
