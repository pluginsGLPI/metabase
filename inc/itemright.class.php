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

use Glpi\Application\View\TemplateRenderer;

/**
 * Dashboard access rights granted to a Group or to a User.
 *
 * Works alongside PluginMetabaseProfileright: a dashboard is visible when the
 * active profile, one of the user's groups or the user itself has READ access.
 */
class PluginMetabaseItemright extends CommonDBTM
{
    /**
     * Itemtypes that can receive dashboard rights.
     */
    public const SUPPORTED_ITEMTYPES = [Group::class, User::class];

    /**
     * {@inheritDoc}
     * @see CommonGLPI::getTypeName()
     */
    public static function getTypeName($nb = 0)
    {
        return __s('Metabase', 'metabase');
    }

    /**
     * Check whether given itemtype can receive dashboard rights.
     *
     * @param mixed $itemtype
     *
     * @return bool
     */
    public static function isSupportedItemtype($itemtype): bool
    {
        return in_array($itemtype, self::SUPPORTED_ITEMTYPES, true);
    }

    /**
     * {@inheritDoc}
     * @see CommonGLPI::getTabNameForItem()
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (
            $item instanceof CommonDBTM
            && self::isSupportedItemtype($item::class)
            && !$item->isNewItem()
            && $item::canView()
        ) {
            return self::createTabEntry(self::getTypeName(), 0, $item::getType(), PluginMetabaseConfig::getIcon());
        }

        return '';
    }

    /**
     * {@inheritDoc}
     * @see CommonGLPI::displayTabContentForItem()
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (
            $item instanceof CommonDBTM
            && self::isSupportedItemtype($item::class)
            && $item->can($item->getID(), READ)
        ) {
            self::showForItem($item);
        }

        return true;
    }

    /**
     * Display dashboard rights matrix for given group or user.
     *
     * READ right on the item is required to see the matrix,
     * UPDATE right on the item is required to change it.
     *
     * @param CommonDBTM $item Group or User
     *
     * @return bool
     */
    public static function showForItem(CommonDBTM $item): bool
    {
        $apiclient  = new PluginMetabaseAPIClient();
        $dashboards = $apiclient->getDashboards();

        if (!is_array($dashboards) || $dashboards === []) {
            TemplateRenderer::getInstance()->display('@metabase/itemright.html.twig', [
                'no_dashboard' => true,
            ]);
            return false;
        }

        $rights = self::getRightsForItem($item::class, $item->getID());

        $dashboards_data = [];
        foreach ($dashboards as $dashboard) {
            $dashboards_data[] = [
                'id'     => (int) $dashboard['id'],
                'name'   => $dashboard['name'],
                'rights' => $rights[(int) $dashboard['id']] ?? 0,
            ];
        }

        TemplateRenderer::getInstance()->display('@metabase/itemright.html.twig', [
            'no_dashboard'  => false,
            'form_url'      => self::getFormURL(),
            'itemtype'      => $item::class,
            'items_id'      => $item->getID(),
            'can_update'    => $item->can($item->getID(), UPDATE),
            'dashboards'    => $dashboards_data,
            'right_choices' => [
                0    => __('No access'),
                READ => __('Read'),
            ],
        ]);

        return true;
    }

    /**
     * Check if the given groups or user are able to view at least one dashboard.
     *
     * @param string $itemtype Group::class or User::class
     * @param int[]  $items_ids
     *
     * @return bool
     */
    public static function canItemsViewDashboards(string $itemtype, array $items_ids): bool
    {
        return self::countViewableRights($itemtype, $items_ids) > 0;
    }

    /**
     * Check if the given groups or user are able to view given dashboard.
     *
     * @param string $itemtype     Group::class or User::class
     * @param int[]  $items_ids
     * @param int    $dashboard_id Metabase dashboard id
     *
     * @return bool
     */
    public static function canItemsViewDashboard(string $itemtype, array $items_ids, int $dashboard_id): bool
    {
        return self::countViewableRights($itemtype, $items_ids, $dashboard_id) > 0;
    }

    /**
     * Count rights rows granting READ access to the given items.
     *
     * @param string   $itemtype
     * @param int[]    $items_ids
     * @param int|null $dashboard_id Restrict to this dashboard if not null
     *
     * @return int
     */
    private static function countViewableRights(string $itemtype, array $items_ids, ?int $dashboard_id = null): int
    {
        $items_ids = array_values(array_filter(array_map('intval', $items_ids)));
        if (!self::isSupportedItemtype($itemtype) || $items_ids === []) {
            return 0;
        }

        $where = [
            'itemtype' => $itemtype,
            'items_id' => $items_ids,
            'rights'   => ['&', READ],
        ];
        if ($dashboard_id !== null) {
            $where['dashboard_id'] = $dashboard_id;
        }

        return countElementsInTable(self::getTable(), $where);
    }

    /**
     * Returns rights of given item, indexed by dashboard id.
     *
     * @param string $itemtype
     * @param int    $items_id
     *
     * @return array<int, int>
     */
    private static function getRightsForItem(string $itemtype, int $items_id): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['dashboard_id', 'rights'],
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'itemtype' => $itemtype,
                'items_id' => $items_id,
            ],
        ]);

        $rights = [];
        foreach ($iterator as $row) {
            $rights[(int) $row['dashboard_id']] = (int) $row['rights'];
        }

        return $rights;
    }

    /**
     * Defines rights of given item for given dashboard.
     *
     * @param string $itemtype     Group::class or User::class
     * @param int    $items_id
     * @param int    $dashboard_id Metabase dashboard id
     * @param int    $rights
     *
     * @return void
     */
    public static function setDashboardRightsForItem(string $itemtype, int $items_id, int $dashboard_id, int $rights): void
    {
        $criteria = [
            'itemtype'     => $itemtype,
            'items_id'     => $items_id,
            'dashboard_id' => $dashboard_id,
        ];

        $itemright = new self();
        if ($itemright->getFromDBByCrit($criteria)) {
            $itemright->update([
                'id'     => $itemright->getID(),
                'rights' => $rights,
            ]);
        } else {
            $itemright->add($criteria + ['rights' => $rights]);
        }
    }

    /**
     * Remove rights of a purged group or user.
     *
     * @param CommonDBTM $item
     *
     * @return void
     */
    public static function cleanForItem(CommonDBTM $item): void
    {
        $itemright = new self();
        $itemright->deleteByCriteria([
            'itemtype' => $item::class,
            'items_id' => $item->getID(),
        ]);
    }

    /**
     * Install item rights database.
     *
     * @param Migration $migration
     *
     * @return void
     */
    public static function install(Migration $migration)
    {
        /** @var DBmysql $DB */
        global $DB;

        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

        $table = self::getTable();

        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");

            $query = "CREATE TABLE IF NOT EXISTS `$table` (
                     `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                     `itemtype` varchar(100) NOT NULL,
                     `items_id` int {$default_key_sign} NOT NULL,
                     `dashboard_id` int NOT NULL,
                     `rights` int NOT NULL,
                     PRIMARY KEY (`id`),
                     UNIQUE `unicity` (`itemtype`, `items_id`, `dashboard_id`),
                     KEY `dashboard_id` (`dashboard_id`)
                  ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);
        }
    }

    /**
     * Uninstall item rights database.
     *
     * @return void
     */
    public static function uninstall()
    {
        /** @var DBmysql $DB */
        global $DB;

        $DB->doQuery('DROP TABLE IF EXISTS `' . self::getTable() . '`');
    }
}
