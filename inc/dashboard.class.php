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

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

class PluginMetabaseDashboard extends CommonDBTM
{
    /**
     * {@inheritDoc}
     * @see CommonGLPI::getTypeName()
     */
    public static function getTypeName($nb = 0)
    {
        return __s('Metabase dashboard', 'metabase');
    }

    /**
     * Check if the currently logged-in user is able to view at least one
     * dashboard, combining profile, group and user based rights.
     *
     * CUSTOM FORK: rights are additive (OR) across profile / groups / user.
     *
     * @return boolean
     */
    public static function canCurrentUserViewDashboards(): bool
    {
        if (PluginMetabaseProfileright::canProfileViewDashboards($_SESSION['glpiactiveprofile']['id'])) {
            return true;
        }

        if (PluginMetabaseItemright::canItemViewDashboards(User::class, Session::getLoginUserID())) {
            return true;
        }

        if (PluginMetabaseItemright::canGroupsViewDashboards($_SESSION['glpigroups'] ?? [])) {
            return true;
        }

        return false;
    }

    /**
     * Check if the currently logged-in user is able to view the given
     * dashboard, combining profile, group and user based rights.
     *
     * rights are additive (OR) across profile / groups / user.
     *
     * @param integer $dashboardUuid
     *
     * @return boolean
     */
    public static function canCurrentUserViewDashboard($dashboardUuid): bool
    {
        if (PluginMetabaseProfileright::canProfileViewDashboard($_SESSION['glpiactiveprofile']['id'], $dashboardUuid)) {
            return true;
        }

        if (PluginMetabaseItemright::canItemViewDashboard(User::class, Session::getLoginUserID(), $dashboardUuid)) {
            return true;
        }

        if (PluginMetabaseItemright::canGroupsViewDashboard($_SESSION['glpigroups'] ?? [], $dashboardUuid)) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     * @see CommonGLPI::getTabNameForItem()
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        switch ($item->getType()) {
            case 'Central':
                if (self::canCurrentUserViewDashboards()) {
                    return self::createTabEntry(self::getTypeName(), 0, $item::getType(), PluginMetabaseConfig::getIcon());
                }

                break;
        }

        return '';
    }

    /**
     * {@inheritDoc}
     * @see CommonGLPI::displayTabContentForItem()
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        switch (get_class($item)) {
            case Central::class:
                if (self::canCurrentUserViewDashboards()) {
                    self::showForCentral($item, $withtemplate);
                }

                break;
        }

        return true;
    }

    /**
     * Display central tab.
     *
     * @param Central $item
     * @param number $withtemplate
     *
     * @return void
     */
    public static function showForCentral(Central $item, $withtemplate = 0, $is_helpdesk = false)
    {
        $apiclient = new PluginMetabaseAPIClient();

        $currentUuid = $_GET['uuid'] ?? null;

        $dashboards = $apiclient->getDashboards();
        if (is_array($dashboards)) {
            $dashboards = array_filter(
                $dashboards,
                function ($dashboard) {
                    $isEmbeddingEnabled = $dashboard['enable_embedding'];
                    $canView            = self::canCurrentUserViewDashboard($dashboard['id']);

                    return $isEmbeddingEnabled && $canView;
                },
            );
        }

        if (empty($dashboards)) {
            return;
        }

        if (null === $currentUuid) {
            $firstDashboard = current($dashboards);
            $currentUuid    = $firstDashboard['id'];
        }

        Dropdown::showFromArray(
            'current_dashboard',
            array_combine(array_column($dashboards, 'id'), array_column($dashboards, 'name')),
            [
                'on_change' => ($is_helpdesk) ? 'location.href = location.origin+location.pathname+"?uuid="+$(this).val()' : 'reloadTab("uuid=" + $(this).val());',
                'value'     => $currentUuid,
            ],
        );

        $config = PluginMetabaseConfig::getConfig();

        $signer_config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($config['embedded_token']),
        );
        $token = $signer_config->builder()
          ->withClaim('resource', [
              'dashboard' => (int) $currentUuid,
          ])
          ->withClaim('params', new stdClass())
          ->getToken($signer_config->signer(), $signer_config->signingKey());

        $url = rtrim($config['metabase_url'], '/');
        echo "<iframe src='$url/embed/dashboard/{$token->toString()}#bordered=false'
                    id='metabase_iframe'
                    allowtransparency></iframe>";
    }
}
