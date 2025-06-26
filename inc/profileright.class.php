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

class PluginMetabaseProfileright extends CommonDBTM
{
    /**
     * Necessary right to edit the rights of this plugin.
     */
    public static $rightname = 'profile';

    /**
     * {@inheritDoc}
     * @see CommonGLPI::getTypeName()
     */
    public static function getTypeName($nb = 0)
    {
        return __('Metabase', 'metabase');
    }

    /**
     * {@inheritDoc}
     * @see CommonGLPI::getTabNameForItem()
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (Profile::class === $item->getType() && Session::haveRight('profile', READ)) {
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
        if ($item instanceof self && Session::haveRight('profile', READ)) {
            $profileright = new self();
            $profileright->showForm($item->fields['id']);
        }

        return true;
    }

    /**
     * Display profile rights form.
     *
     * @param integer $id Profile id
     * @param array $options
     *
     * @return bool
     */
    public function showForm($id, $options = [])
    {
        if (!Session::haveRight('profile', READ)) {
            return false;
        }

        echo '<form method="post" action="' . self::getFormURL() . '">';
        echo '<div class="spaced" id="tabsbody">';
        echo '<table class="tab_cadre_fixe" id="mainformtable">';

        echo '<tr class="headerRow"><th colspan="2">' . self::getTypeName() . '</th></tr>';

        Plugin::doHook('pre_item_form', ['item' => $this, 'options' => &$options]);

        echo '<tr><th colspan="2">' . __('Rights management', 'metabase') . '</th></tr>';

        echo '<input type="hidden" name="profiles_id" value="' . $id . '" />';

        if (Session::haveRight('profile', UPDATE)) {
            echo '<tr class="tab_bg_4">';
            echo '<td colspan="2" class="center">';
            echo '<button type="submit" class="btn btn-outline-secondary" name="set_rights_to_all" value="1">'
            . "<i class='ti ti-check'></i>"
            . '<span>' . __('Allow access to all', 'metabase') . '</span>'
            . '</button>';
            echo ' &nbsp; ';
            echo '<button type="submit" class="btn btn-outline-secondary" name="set_rights_to_all" value="0">'
            . "<i class='ti ti-forbid'></i>"
            . '<span>' . __('Disallow access to all', 'metabase') . '</span>'
            . '</button>';
            echo '</td>';
            echo '</tr>';
        }

        $apiclient  = new PluginMetabaseAPIClient();
        $dashboards = $apiclient->getDashboards();

        foreach ($dashboards as $dashboard) {
            echo '<tr class="tab_bg_1">';
            echo '<td>' . $dashboard['name'] . '</td>';
            echo '<td>';
            Profile::dropdownRight(
                sprintf('dashboard[%d]', $dashboard['id']),
                [
                    'value'   => self::getProfileRightForDashboard($id, $dashboard['id']),
                    'nonone'  => 0,
                    'noread'  => 0,
                    'nowrite' => 1,
                ],
            );
            echo '</td>';
            echo '</tr>';
        }

        if (Session::haveRight('profile', UPDATE)) {
            echo '<tr class="tab_bg_4">';
            echo '<td colspan="2" class="center">';
            echo Html::submit(_sx('button', 'Save'), [
                'name'  => 'update',
                'icon'  => 'ti ti-device-floppy',
                'class' => 'btn btn-primary',
            ]);
            echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</div>';

        Html::closeForm();

        return true;
    }

    /**
     * Check if profile is able to view at least one dashboard.
     *
     * @param integer $profileId
     *
     * @return boolean
     */
    public static function canProfileViewDashboards($profileId)
    {
        /** @var DBmysql $DB */
        global $DB;

        $iterator = $DB->request(
            [
                'FROM'  => self::getTable(),
                'WHERE' => [
                    'profiles_id' => $profileId,
                ],
            ],
        );

        foreach ($iterator as $right) {
            if ($right['rights'] & READ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if profile is able to view given dashboard.
     *
     * @param integer $profileId
     * @param integer $dashboardUuid
     *
     * @return integer
     */
    public static function canProfileViewDashboard($profileId, $dashboardUuid)
    {
        return self::getProfileRightForDashboard($profileId, $dashboardUuid) & READ;
    }

    /**
     * Returns profile rights for given dashboard.
     *
     * @param integer $profileId
     * @param integer $dashboardUuid
     *
     * @return integer
     */
    private static function getProfileRightForDashboard($profileId, $dashboardUuid)
    {
        $rightCriteria = [
            'profiles_id'    => $profileId,
            'dashboard_uuid' => $dashboardUuid,
        ];

        $profileRight = new self();
        if ($profileRight->getFromDBByCrit($rightCriteria)) {
            return $profileRight->fields['rights'];
        }

        return 0;
    }

    /**
     * Defines profile rights for dashboard.
     *
     * @param integer $profileId
     * @param integer $dashboardUuid
     * @param integer $rights
     *
     * @return void
     */
    public static function setDashboardRightsForProfile($profileId, $dashboardUuid, $rights)
    {
        $profileRight = new self();

        $rightsExists = $profileRight->getFromDBByCrit(
            [
                'profiles_id'    => $profileId,
                'dashboard_uuid' => $dashboardUuid,
            ],
        );

        if ($rightsExists) {
            $profileRight->update(
                [
                    'id'     => $profileRight->fields['id'],
                    'rights' => $rights,
                ],
            );
        } else {
            $profileRight->add(
                [
                    'profiles_id'    => $profileId,
                    'dashboard_uuid' => $dashboardUuid,
                    'rights'         => $rights,
                ],
            );
        }
    }

    /**
     * Install profiles database.
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
                     `profiles_id` int {$default_key_sign} NOT NULL,
                     `dashboard_uuid` int NOT NULL,
                     `rights` int NOT NULL,
                     PRIMARY KEY (`id`),
                     UNIQUE `profiles_id_dashboard_uuid` (`profiles_id`, `dashboard_uuid`)
                  ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);
        }
    }

    /**
     * Uninstall profiles database.
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
