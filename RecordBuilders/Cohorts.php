<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelCohorts\RecordBuilders;

use Piwik\ArchiveProcessor;
use Piwik\ArchiveProcessor\Record;
use Piwik\ArchiveProcessor\RecordBuilder;
use Piwik\DataTable;
use Piwik\Period\Factory as PeriodFactory;

/**
 * The visitors of a day, week or month by how many periods ago their first visit was: how many
 * they were, their visits, and for each goal how many of them converted.
 */
class Cohorts extends RecordBuilder
{
    // A row for each number of periods since the first visit, 0 for the visitors who are new.
    public const RECORD = 'RebelCohorts_cohorts';

    // ponytail: visitors are followed for this many periods, the one of their first visit included.
    public const PERIODS = 12;

    // The periods a cohort can be. A year and a range have no report.
    public const PERIOD_LABELS = ['day', 'week', 'month'];

    public function isEnabled(ArchiveProcessor $archiveProcessor): bool
    {
        return in_array($archiveProcessor->getParams()->getPeriod()->getLabel(), self::PERIOD_LABELS, true);
    }

    public function getRecordMetadata(ArchiveProcessor $archiveProcessor): array
    {
        return [Record::make(Record::TYPE_BLOB, self::RECORD)];
    }

    /**
     * The visitors of a week are not the sum of the visitors of its days, so a week and a month
     * are counted from the logs like a day.
     */
    public function buildForNonDayPeriod(ArchiveProcessor $archiveProcessor): void
    {
        $this->buildFromLogs($archiveProcessor);
    }

    protected function aggregate(ArchiveProcessor $archiveProcessor): array
    {
        $params = $archiveProcessor->getParams();
        $period = $params->getPeriod();
        $logAggregator = $archiveProcessor->getLogAggregator();

        // When each of the periods began, this one first, as the logs have it.
        $starts = [];
        for ($ago = 0; $ago < self::PERIODS; $ago++) {
            $starts[] = PeriodFactory::build($period->getLabel(), $period->getDateStart()->subPeriod($ago, $period->getLabel()))
                ->getDateTimeStart()->setTimezone($params->getSite()->getTimezone())->getDatetime();
        }

        $firstVisit = 'DATE_SUB(log_visit.visit_first_action_time, INTERVAL COALESCE(log_visit.visitor_seconds_since_first, 0) SECOND)';
        $oldest = array_pop($starts);
        $ago = '0';
        foreach ($starts as $start) {
            $ago .= " + ($firstVisit < '$start')";
        }
        $ago = '(' . $ago . ')';
        $isFollowed = "$firstVisit >= '$oldest'";

        $table = new DataTable();

        $visits = $logAggregator->generateQuery(
            "$ago AS ago, COUNT(DISTINCT log_visit.idvisitor) AS nb_uniq_visitors, COUNT(*) AS nb_visits",
            ['log_visit'],
            $logAggregator->getWhereStatement('log_visit', 'visit_last_action_time') . ' AND ' . $isFollowed,
            'ago',
            false
        );
        foreach ($logAggregator->getDb()->fetchAll($visits['sql'], $visits['bind']) as $row) {
            $table->sumRowWithLabel(self::getLabel((int) $row['ago']), [
                'nb_uniq_visitors' => (int) $row['nb_uniq_visitors'],
                'nb_visits' => (int) $row['nb_visits'],
            ]);
        }

        // A segment picks the conversions by the visit they happened in.
        $conversions = $logAggregator->generateQuery(
            "log_conversion.idgoal AS idgoal, $ago AS ago, COUNT(DISTINCT log_conversion.idvisitor) AS nb_visitors, "
            . 'COUNT(*) AS nb_conversions, SUM(log_conversion.revenue) AS revenue',
            ['log_conversion', 'log_visit'],
            $logAggregator->getWhereStatement('log_conversion', 'server_time') . ' AND log_conversion.idgoal >= 0 AND ' . $isFollowed,
            'log_conversion.idgoal, ago',
            false
        );
        foreach ($logAggregator->getDb()->fetchAll($conversions['sql'], $conversions['bind']) as $row) {
            $table->sumRowWithLabel(self::getLabel((int) $row['ago']), [
                self::getGoalColumn((int) $row['idgoal'], 'nb_visitors') => (int) $row['nb_visitors'],
                self::getGoalColumn((int) $row['idgoal'], 'nb_conversions') => (int) $row['nb_conversions'],
                self::getGoalColumn((int) $row['idgoal'], 'revenue') => (float) $row['revenue'],
            ]);
        }

        return [self::RECORD => $table];
    }

    public static function getLabel(int $periodsAgo): string
    {
        return 'ago' . $periodsAgo;
    }

    /**
     * @param string $metric nb_visitors, nb_conversions or revenue
     */
    public static function getGoalColumn(int $idGoal, string $metric): string
    {
        return 'goal_' . $idGoal . '_' . $metric;
    }
}
