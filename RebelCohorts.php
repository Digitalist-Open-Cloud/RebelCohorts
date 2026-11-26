<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelCohorts;

use Piwik\Plugin;

class RebelCohorts extends Plugin
{
    public function registerEvents()
    {
        return [
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles' => 'getJavaScriptFiles',
        ];
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = 'plugins/RebelCohorts/stylesheets/menuIcon.less';
        $stylesheets[] = 'plugins/RebelCohorts/stylesheets/cohorts.less';
    }

    public function getJavaScriptFiles(&$files)
    {
        $files[] = 'plugins/RebelCohorts/javascripts/cohortsPage.js';
    }
}
