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

if (isset($_REQUEST['update'])) {
    Session::checkRight('profile', UPDATE);

    if (
        !array_key_exists('profiles_id', $_REQUEST)
        || empty($_REQUEST['profiles_id'])
        || !array_key_exists('dashboards', $_REQUEST)
        || !is_string($_REQUEST['dashboards'])
        || !is_string($_REQUEST['dashboards_read'] ?? '')
    ) {
        Session::addMessageAfterRedirect(
            __s('Invalid request.', 'metabase'),
            false,
            ERROR,
        );
        Html::back();
    }

    // displayed dashboards and readable ones, each sent as one comma separated field
    $readableUuids = array_flip(array_map('intval', array_filter(explode(',', $_REQUEST['dashboards_read'] ?? ''))));

    $rightsByDashboard       = [];
    $viewableDashboardsUuids = [];
    foreach (array_filter(explode(',', $_REQUEST['dashboards'])) as $dashboardUuid) {
        $dashboardUuid = (int) $dashboardUuid;
        $rights        = isset($readableUuids[$dashboardUuid]) ? READ : 0;

        $rightsByDashboard[$dashboardUuid] = $rights;
        if (($rights & READ) !== 0) {
            $viewableDashboardsUuids[] = $dashboardUuid;
        }
    }

    PluginMetabaseProfileright::setDashboardsRightsForProfile(
        $_REQUEST['profiles_id'],
        $rightsByDashboard,
    );

    $apiclient = new PluginMetabaseAPIClient();
    $apiclient->enableDashboardsEmbeddedDisplay($viewableDashboardsUuids);
} elseif (isset($_REQUEST['set_rights_to_all'])) {
    Session::checkRight('profile', UPDATE);

    if (!array_key_exists('profiles_id', $_REQUEST) || empty($_REQUEST['profiles_id'])) {
        Session::addMessageAfterRedirect(
            __s('Invalid request.', 'metabase'),
            false,
            ERROR,
        );
        Html::back();
    }

    $apiclient = new PluginMetabaseAPIClient();

    $rightsByDashboard       = [];
    $viewableDashboardsUuids = [];
    foreach ($apiclient->getDashboards() as $dashboard) {
        $rightsByDashboard[$dashboard['id']] = $_REQUEST['set_rights_to_all'];

        $viewableDashboardsUuids[] = $dashboard['id'];
    }

    PluginMetabaseProfileright::setDashboardsRightsForProfile(
        $_REQUEST['profiles_id'],
        $rightsByDashboard,
    );

    $apiclient->enableDashboardsEmbeddedDisplay($viewableDashboardsUuids);
}

Html::back();
