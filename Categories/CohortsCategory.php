<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelCohorts\Categories;

use Piwik\Category\Category;

class CohortsCategory extends Category
{
    public const ID = 'RebelCohorts_Cohorts';

    protected $id = self::ID;
    protected $order = 14;
    protected $icon = 'rebelCohortsIcon';
}
