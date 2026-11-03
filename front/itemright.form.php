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

$itemtype = $_POST['itemtype'] ?? null;
$items_id = (int) ($_POST['items_id'] ?? 0);

$item = match ($itemtype) {
    Group::class => new Group(),
    User::class  => new User(),
    default      => null,
};

if ($item === null || $items_id <= 0 || !$item->getFromDB($items_id)) {
    Session::addMessageAfterRedirect(
        __s('Invalid request.', 'metabase'),
        false,
        ERROR,
    );
    Html::back();
}

// UPDATE right on the targeted group / user is required to change its dashboard rights.
$item->check($items_id, UPDATE);

$viewable_dashboards_ids = [];

if (isset($_POST['update'])) {
    if (!isset($_POST['dashboard']) || !is_array($_POST['dashboard'])) {
        Session::addMessageAfterRedirect(
            __s('Invalid request.', 'metabase'),
            false,
            ERROR,
        );
        Html::back();
    }

    foreach ($_POST['dashboard'] as $dashboard_id => $rights) {
        $rights = ((int) $rights & READ) !== 0 ? READ : 0;

        PluginMetabaseItemright::setDashboardRightsForItem($item::class, $items_id, (int) $dashboard_id, $rights);

        if ($rights !== 0) {
            $viewable_dashboards_ids[] = (int) $dashboard_id;
        }
    }
} elseif (isset($_POST['set_rights_to_all'])) {
    $rights = ((int) $_POST['set_rights_to_all'] & READ) !== 0 ? READ : 0;

    $apiclient  = new PluginMetabaseAPIClient();
    $dashboards = $apiclient->getDashboards();

    foreach (is_array($dashboards) ? $dashboards : [] as $dashboard) {
        PluginMetabaseItemright::setDashboardRightsForItem($item::class, $items_id, (int) $dashboard['id'], $rights);

        if ($rights !== 0) {
            $viewable_dashboards_ids[] = (int) $dashboard['id'];
        }
    }
}

if ($viewable_dashboards_ids !== []) {
    $apiclient = new PluginMetabaseAPIClient();
    $apiclient->enableDashboardsEmbeddedDisplay($viewable_dashboards_ids);
}

Html::back();
