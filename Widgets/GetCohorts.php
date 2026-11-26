<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelCohorts\Widgets;

use Piwik\API\Request as ApiRequest;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugins\RebelCohorts\Categories\CohortsCategory;
use Piwik\Plugins\RebelCohorts\Categories\RetentionSubcategory;
use Piwik\Plugins\RebelCohorts\RecordBuilders\Cohorts;
use Piwik\Request;
use Piwik\Site;
use Piwik\Tracker\GoalManager;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

/**
 * The page of cohorts. It holds the goals to pick from and its script asks the API for the cohorts.
 */
class GetCohorts extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId(CohortsCategory::ID);
        $config->setSubcategoryId(RetentionSubcategory::ID);
        $config->setName(RetentionSubcategory::ID);
        $config->setIsWide();
        $config->setOrder(1);
    }

    public function render()
    {
        $request = Request::fromRequest();
        $idSite = $request->getIntegerParameter('idSite', 0);
        Piwik::checkUserHasViewAccess($idSite);

        $period = $request->getStringParameter('period', '');
        if (!in_array($period, Cohorts::PERIOD_LABELS, true)) {
            return $this->renderTemplate('cohortsPage', ['period' => '']);
        }

        $goals = [];
        foreach (ApiRequest::processRequest('Goals.getGoals', ['idSite' => $idSite, 'filter_limit' => -1], []) as $goal) {
            $goals[(int) $goal['idgoal']] = Common::unsanitizeInputValue($goal['name']);
        }
        if (Site::isEcommerceEnabledFor($idSite)) {
            $goals[GoalManager::IDGOAL_ORDER] = Piwik::translate('RebelCohorts_EcommerceOrders');
        }

        $unit = ucfirst($period);

        return $this->renderTemplate('cohortsPage', [
            'period' => $period,
            'help' => Piwik::translate('RebelCohorts_Help' . $unit),
            'goals' => $goals,
            'texts' => [
                'noData' => Piwik::translate('CoreHome_ThereIsNoDataForThisReport'),
                'loading' => Piwik::translate('General_LoadingData'),
                'period' => Piwik::translate('RebelCohorts_Period' . $unit),
                'first' => Piwik::translate('RebelCohorts_First' . $unit),
                'firstVisit' => Piwik::translate('RebelCohorts_FirstVisit'),
                'newVisitors' => Piwik::translate('RebelCohorts_NewVisitors'),
                'allCohorts' => Piwik::translate('RebelCohorts_AllCohorts'),
                'newVisitorsOf' => Piwik::translate('RebelCohorts_NewVisitorsOf'),
                'ofNewVisitors' => Piwik::translate('RebelCohorts_NOfNewVisitors'),
                'best' => Piwik::translate('RebelCohorts_Best'),
                'weakest' => Piwik::translate('RebelCohorts_Weakest'),
                'back' => ['tile' => Piwik::translate('RebelCohorts_TileBack'), 'share' => Piwik::translate('RebelCohorts_ShareBack')],
                'converted' => ['tile' => Piwik::translate('RebelCohorts_TileConverted'), 'share' => Piwik::translate('RebelCohorts_ShareConverted')],
            ],
        ]);
    }
}
