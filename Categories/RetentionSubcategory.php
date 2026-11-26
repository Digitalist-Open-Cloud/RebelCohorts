<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelCohorts\Categories;

use Piwik\Category\Subcategory;

class RetentionSubcategory extends Subcategory
{
    public const ID = 'RebelCohorts_Retention';

    protected $categoryId = CohortsCategory::ID;
    protected $id = self::ID;
    protected $order = 1;
}
