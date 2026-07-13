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
 *
 * CUSTOM FORK NOTICE
 * -------------------------------------------------------------------------
 * This class was added in a local fork to allow defining dashboard access
 * rights per Group and per User, in addition to the native per Profile
 * rights handled by PluginMetabaseProfileright.
 * -------------------------------------------------------------------------
 */

class PluginMetabaseItemright extends CommonDBTM
{
    /**
     * Itemtypes supported by this "generic" right holder.
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
     * Returns the GLPI right (and value) required to manage metabase
     * dashboard rights for the given itemtype.
     *
     * @param string $itemtype Group::class or User::class
     *
     * @return array{0: string, 1: int} [rightname, right value]
     */
    private static function getRequiredRight(string $itemtype): array
    {
        return match ($itemtype) {
            Group::class => ['group', UPDATE],
            User::class  => ['user', UPDATE],
            default      => ['profile', UPDATE],
        };
    }

    /**
     * {@inheritDoc}
     * @see CommonGLPI::getTabNameForItem()
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        $itemtype = $item::getType();

        if (!in_array($itemtype, self::SUPPORTED_ITEMTYPES, true)) {
            return '';
        }

        [$rightname, $rightvalue] = self::getRequiredRight($itemtype);
        if (Session::haveRight($rightname, $rightvalue)) {
            return self::createTabEntry(self::getTypeName(), 0, $itemtype, PluginMetabaseConfig::getIcon());
        }

        return '';
    }

    /**
     * {@inheritDoc}
     * @see CommonGLPI::displayTabContentForItem()
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        $itemtype = $item::getType();

        if (!in_array($itemtype, self::SUPPORTED_ITEMTYPES, true)) {
            return true;
        }

        [$rightname, $rightvalue] = self::getRequiredRight($itemtype);
        if (Session::haveRight($rightname, $rightvalue)) {
            $itemright = new self();
            $itemright->showRightsForm($itemtype, $item->fields['id']);
        }

        return true;
    }

    /**
     * Display item (group/user) rights form.
     *
     * @param string  $itemtype Group::class or User::class
     * @param integer $itemsId  Group or User id
     * @param array   $options
     *
     * @return bool
     */
    public function showRightsForm($itemtype, $itemsId, $options = [])
    {
        if (!in_array($itemtype, self::SUPPORTED_ITEMTYPES, true)) {
            return false;
        }

        [$rightname, $rightvalue] = self::getRequiredRight($itemtype);
        if (!Session::haveRight($rightname, $rightvalue)) {
            return false;
        }

        $apiclient  = new PluginMetabaseAPIClient();
        $dashboards = $apiclient->getDashboards();

        if (!$dashboards) {
            echo '<div class="alert alert-warning">' . __s('No dashboards found in Metabase.', 'metabase') . '</div>';
            return false;
        }

        echo '<form method="post" action="' . self::getFormURL() . '">';
        echo '<div class="spaced" id="tabsbody">';
        echo '<table class="tab_cadre_fixe" id="mainformtable">';

        echo '<tr class="headerRow"><th colspan="2">' . self::getTypeName() . '</th></tr>';

        Plugin::doHook('pre_item_form', ['item' => $this, 'options' => &$options]);

        echo '<tr><th colspan="2">' . __s('Rights management', 'metabase') . '</th></tr>';

        echo '<input type="hidden" name="itemtype" value="' . $itemtype . '" />';
        echo '<input type="hidden" name="items_id" value="' . $itemsId . '" />';

        echo '<tr class="tab_bg_4">';
        echo '<td colspan="2" class="center">';
        echo '<button type="submit" class="btn btn-outline-secondary" name="set_rights_to_all" value="1">'
        . "<i class='ti ti-check'></i>"
        . '<span>' . __s('Allow access to all', 'metabase') . '</span>'
        . '</button>';
        echo ' &nbsp; ';
        echo '<button type="submit" class="btn btn-outline-secondary" name="set_rights_to_all" value="0">'
        . "<i class='ti ti-forbid'></i>"
        . '<span>' . __s('Disallow access to all', 'metabase') . '</span>'
        . '</button>';
        echo '</td>';
        echo '</tr>';

        foreach ($dashboards as $dashboard) {
            echo '<tr class="tab_bg_1">';
            echo '<td>' . $dashboard['name'] . '</td>';
            echo '<td>';
            Profile::dropdownRight(
                sprintf('dashboard[%d]', $dashboard['id']),
                [
                    'value'   => self::getItemRightForDashboard($itemtype, $itemsId, $dashboard['id']),
                    'nonone'  => 0,
                    'noread'  => 0,
                    'nowrite' => 1,
                ],
            );
            echo '</td>';
            echo '</tr>';
        }

        echo '<tr class="tab_bg_4">';
        echo '<td colspan="2" class="center">';
        echo Html::submit(_sx('button', 'Save'), [
            'name'  => 'update',
            'icon'  => 'ti ti-device-floppy',
            'class' => 'btn btn-primary',
        ]);
        echo '</td>';
        echo '</tr>';

        echo '</table>';
        echo '</div>';

        Html::closeForm();

        return true;
    }

    /**
     * Check if any group from the given list is able to view at least one dashboard.
     *
     * @param int[] $groupIds
     *
     * @return boolean
     */
    public static function canGroupsViewDashboards(array $groupIds): bool
    {
        foreach ($groupIds as $groupId) {
            if (self::canItemViewDashboards(Group::class, $groupId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if any group from the given list is able to view the given dashboard.
     *
     * @param int[]   $groupIds
     * @param integer $dashboardUuid
     *
     * @return boolean
     */
    public static function canGroupsViewDashboard(array $groupIds, $dashboardUuid): bool
    {
        foreach ($groupIds as $groupId) {
            if (self::canItemViewDashboard(Group::class, $groupId, $dashboardUuid)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if item (group/user) is able to view at least one dashboard.
     *
     * @param string  $itemtype
     * @param integer $itemsId
     *
     * @return boolean
     */
    public static function canItemViewDashboards($itemtype, $itemsId): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        if (empty($itemsId)) {
            return false;
        }

        $iterator = $DB->request(
            [
                'FROM'  => self::getTable(),
                'WHERE' => [
                    'itemtype' => $itemtype,
                    'items_id' => $itemsId,
                ],
            ],
        );

        foreach ($iterator as $right) {
            if (($right['rights'] & READ) !== 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if item (group/user) is able to view given dashboard.
     *
     * @param string  $itemtype
     * @param integer $itemsId
     * @param integer $dashboardUuid
     *
     * @return boolean
     */
    public static function canItemViewDashboard($itemtype, $itemsId, $dashboardUuid): bool
    {
        return (self::getItemRightForDashboard($itemtype, $itemsId, $dashboardUuid) & READ) !== 0;
    }

    /**
     * Returns item (group/user) rights for given dashboard.
     *
     * @param string  $itemtype
     * @param integer $itemsId
     * @param integer $dashboardUuid
     *
     * @return integer
     */
    private static function getItemRightForDashboard($itemtype, $itemsId, $dashboardUuid)
    {
        if (empty($itemsId)) {
            return 0;
        }

        $rightCriteria = [
            'itemtype'       => $itemtype,
            'items_id'       => $itemsId,
            'dashboard_uuid' => $dashboardUuid,
        ];

        $itemright = new self();
        if ($itemright->getFromDBByCrit($rightCriteria)) {
            return $itemright->fields['rights'];
        }

        return 0;
    }

    /**
     * Defines item (group/user) rights for dashboard.
     *
     * @param string  $itemtype
     * @param integer $itemsId
     * @param integer $dashboardUuid
     * @param integer $rights
     *
     * @return void
     */
    public static function setDashboardRightsForItem($itemtype, $itemsId, $dashboardUuid, $rights)
    {
        $itemright = new self();

        $rightsExists = $itemright->getFromDBByCrit(
            [
                'itemtype'       => $itemtype,
                'items_id'       => $itemsId,
                'dashboard_uuid' => $dashboardUuid,
            ],
        );

        if ($rightsExists) {
            $itemright->update(
                [
                    'id'     => $itemright->fields['id'],
                    'rights' => $rights,
                ],
            );
        } else {
            $itemright->add(
                [
                    'itemtype'       => $itemtype,
                    'items_id'       => $itemsId,
                    'dashboard_uuid' => $dashboardUuid,
                    'rights'         => $rights,
                ],
            );
        }
    }

    /**
     * Install itemrights database.
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
                     `dashboard_uuid` int NOT NULL,
                     `rights` int NOT NULL,
                     PRIMARY KEY (`id`),
                     UNIQUE `itemtype_items_id_dashboard_uuid` (`itemtype`, `items_id`, `dashboard_uuid`)
                  ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);
        }
    }

    /**
     * Uninstall itemrights database.
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
