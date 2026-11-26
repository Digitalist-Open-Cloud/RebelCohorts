<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelCohorts;

use Piwik\Archive;
use Piwik\DataTable;
use Piwik\Date;
use Piwik\Metrics\Formatter;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;
use Piwik\Plugins\RebelCohorts\RecordBuilders\Cohorts;
use Piwik\Site;

/**
 * Cohorts: the visitors who came for the first time in the same day, week or month, and how
 * many of them came back or converted in the periods after.
 *
 * @method static \Piwik\Plugins\RebelCohorts\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /**
     * A row for each of the latest periods that are over, ending with the one of the date, the
     * oldest first. A row has the visitors who came for the first time in its period as
     * nb_visitors, and for each period after it how many of them came back: nb_returned_1 for
     * the period after, nb_returned_2 for the one after that and so on.
     *
     * With a goal it also has how many of them converted it, the conversions and the revenue:
     * nb_converted_0, nb_conversions_0 and revenue_0 for the period of the first visit,
     * nb_converted_1 and so on for the periods after.
     *
     * @param int $idSite
     * @param string $period day, week or month
     * @param string $date
     * @param int|string $idGoal 0 for ecommerce orders, empty for no goal
     * @param string|bool $segment
     * @return DataTable
     */
    public function getCohorts($idSite, $period, $date, $idGoal = '', $segment = false)
    {
        $tables = $this->getTables($idSite, $period, $date, $segment);

        $cohorts = new DataTable();
        foreach ($tables as $index => $table) {
            $new = $table->getRowFromLabel(Cohorts::getLabel(0));
            $columns = [
                'label' => $table->getMetadata('period')->getLocalizedShortString(),
                'date' => $table->getMetadata('period')->getDateStart()->toString(),
                'nb_visitors' => $new ? (int) $new->getColumn('nb_uniq_visitors') : 0,
            ];

            for ($after = 0; isset($tables[$index + $after]); $after++) {
                $row = $tables[$index + $after]->getRowFromLabel(Cohorts::getLabel($after));
                if ($after > 0) {
                    $columns['nb_returned_' . $after] = $row ? (int) $row->getColumn('nb_uniq_visitors') : 0;
                }
                if ($idGoal !== '') {
                    $columns['nb_converted_' . $after] = $row ? (int) $row->getColumn(Cohorts::getGoalColumn((int) $idGoal, 'nb_visitors')) : 0;
                    $columns['nb_conversions_' . $after] = $row ? (int) $row->getColumn(Cohorts::getGoalColumn((int) $idGoal, 'nb_conversions')) : 0;
                    $columns['revenue_' . $after] = $row ? round((float) $row->getColumn(Cohorts::getGoalColumn((int) $idGoal, 'revenue')), 2) : 0.0;
                }
            }
            $cohorts->addRowFromSimpleArray($columns);
        }

        return $cohorts;
    }

    /**
     * When the cohorts were last archived, as a sentence for the page.
     *
     * @param int $idSite
     * @param string $period day, week or month
     * @param string $date
     * @param string|bool $segment
     * @return string empty when nothing is archived
     */
    public function getReportGenerated($idSite, $period, $date, $segment = false)
    {
        $archived = [];
        foreach ($this->getTables($idSite, $period, $date, $segment) as $table) {
            $archived[] = (string) $table->getMetadata(DataTable::ARCHIVED_DATE_METADATA_NAME);
        }
        $archived = array_filter($archived);
        if (!$archived) {
            return '';
        }

        // The same words as under the reports of Matomo.
        $date = Date::factory(max($archived));
        if ($date->getTimestamp() > Date::today()->getTimestamp()) {
            return Piwik::translate('CoreHome_ReportGeneratedXAgo', (new Formatter())->getPrettyTimeFromSeconds(time() - $date->getTimestamp(), true));
        }

        return Piwik::translate('CoreHome_ReportGeneratedOn', $date->getLocalized(Date::DATE_FORMAT_SHORT)) . ' (UTC)';
    }

    /**
     * @return DataTable[] the archived record of each period, the oldest first
     */
    private function getTables($idSite, $period, $date, $segment): array
    {
        $idSite = (int) $idSite;
        Piwik::checkUserHasViewAccess($idSite);
        if (!in_array($period, Cohorts::PERIOD_LABELS, true)) {
            throw new \Exception(Piwik::translate('RebelCohorts_PickPeriod'));
        }

        $timezone = Site::getTimezoneFor($idSite);
        $last = PeriodFactory::makePeriodFromQueryParams($timezone, $period, $date);

        // A period that is not over yet is left out: its visitors are still coming.
        $end = $last->getDateStart();
        if (!$last->getDateEnd()->isEarlier(Date::factoryInTimezone('today', $timezone))) {
            $end = $end->subPeriod(1, $period);
        }
        $start = $end->subPeriod(Cohorts::PERIODS - 1, $period);

        /** @var DataTable\Map $map */
        $map = Archive::build($idSite, $period, $start->toString() . ',' . $end->toString(), $segment)->getDataTable(Cohorts::RECORD);

        return array_values($map->getDataTables());
    }
}
