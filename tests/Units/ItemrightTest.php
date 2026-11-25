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

declare(strict_types=1);

namespace GlpiPlugin\Metabase\Tests\Units;

use Glpi\Tests\DbTestCase;
use Group;
use PluginMetabaseItemright;
use User;

final class ItemrightTest extends DbTestCase
{
    public function testGetViewableDashboardIdsWithoutUserNorGroups(): void
    {
        $this->assertSame([], PluginMetabaseItemright::getViewableDashboardIds(0, []));
    }

    public function testGetViewableDashboardIdsFromUserRights(): void
    {
        $user = $this->createUser();

        PluginMetabaseItemright::setDashboardRightsForItem(User::class, $user, 10, READ);
        PluginMetabaseItemright::setDashboardRightsForItem(User::class, $user, 11, 0);

        $this->assertSame([10 => true], PluginMetabaseItemright::getViewableDashboardIds($user, []));
    }

    public function testGetViewableDashboardIdsFromGroupRights(): void
    {
        $group_1 = $this->createGroup();
        $group_2 = $this->createGroup();

        PluginMetabaseItemright::setDashboardRightsForItem(Group::class, $group_1, 20, READ);
        PluginMetabaseItemright::setDashboardRightsForItem(Group::class, $group_1, 21, 0);
        PluginMetabaseItemright::setDashboardRightsForItem(Group::class, $group_2, 22, READ);

        $this->assertSame([20 => true], PluginMetabaseItemright::getViewableDashboardIds(0, [$group_1]));

        $viewable = PluginMetabaseItemright::getViewableDashboardIds(0, [$group_1, $group_2]);
        ksort($viewable);
        $this->assertSame([20 => true, 22 => true], $viewable);
    }

    public function testGetViewableDashboardIdsIsAdditiveAcrossSources(): void
    {
        $user    = $this->createUser();
        $group_1 = $this->createGroup();
        $group_2 = $this->createGroup();

        // "No access" on a group does not hide a dashboard granted through another source.
        PluginMetabaseItemright::setDashboardRightsForItem(User::class, $user, 30, READ);
        PluginMetabaseItemright::setDashboardRightsForItem(Group::class, $group_1, 30, 0);
        PluginMetabaseItemright::setDashboardRightsForItem(Group::class, $group_1, 31, READ);
        PluginMetabaseItemright::setDashboardRightsForItem(Group::class, $group_2, 31, READ);

        $viewable = PluginMetabaseItemright::getViewableDashboardIds($user, [$group_1, $group_2]);
        ksort($viewable);
        $this->assertSame([30 => true, 31 => true], $viewable);
    }

    public function testGetViewableDashboardIdsIgnoresOtherItems(): void
    {
        $user       = $this->createUser();
        $other_user = $this->createUser();
        $group      = $this->createGroup();

        PluginMetabaseItemright::setDashboardRightsForItem(User::class, $other_user, 40, READ);
        // Same id as the user, but as a group: must not be mixed up with the user rights.
        PluginMetabaseItemright::setDashboardRightsForItem(Group::class, $user, 41, READ);
        PluginMetabaseItemright::setDashboardRightsForItem(Group::class, $group, 42, READ);

        $this->assertSame([], PluginMetabaseItemright::getViewableDashboardIds($user, []));
        $this->assertSame([42 => true], PluginMetabaseItemright::getViewableDashboardIds(0, [$group]));
    }

    public function testGetViewableDashboardIdsIgnoresInvalidGroupIds(): void
    {
        $group = $this->createGroup();

        PluginMetabaseItemright::setDashboardRightsForItem(Group::class, $group, 50, READ);

        $this->assertSame([], PluginMetabaseItemright::getViewableDashboardIds(0, [0]));
        $this->assertSame([50 => true], PluginMetabaseItemright::getViewableDashboardIds(0, [0, (string) $group]));
    }

    private function createUser(): int
    {
        return $this->createItem(User::class, ['name' => 'metabase_user_' . $this->getUniqueString()])->getID();
    }

    private function createGroup(): int
    {
        return $this->createItem(Group::class, [
            'name'        => 'metabase_group_' . $this->getUniqueString(),
            'entities_id' => 0,
        ])->getID();
    }
}
