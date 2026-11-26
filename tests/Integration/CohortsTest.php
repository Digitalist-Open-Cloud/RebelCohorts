<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelCohorts\tests\Integration;

use Piwik\API\Request;
use Piwik\Date;
use Piwik\NoAccessException;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Plugins\RebelCohorts\API;
use Piwik\Plugins\RebelCohorts\RecordBuilders\Cohorts;
use Piwik\Plugins\RebelCohorts\tests\Fixtures\SiteWithCohorts;
use Piwik\Plugins\RebelCohorts\Widgets\GetCohorts;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group RebelCohorts
 * @group Plugins
 */
class CohortsTest extends IntegrationTestCase
{
    /**
     * @var SiteWithCohorts
     */
    public static $fixture;

    public function setUp(): void
    {
        parent::setUp();

        Fixture::loadAllTranslations();
        unset($_GET['period']);
    }

    public function testACohortIsTheVisitorsWhoCameFirstInAWeekAndHasHowManyCameBackInTheWeeksAfter(): void
    {
        $cohorts = $this->cohorts();

        $this->assertCount(Cohorts::PERIODS, $cohorts);
        $this->assertSame(
            ['nb_visitors' => 3, 'nb_returned_1' => 2, 'nb_returned_2' => 1],
            $cohorts['2024-02-05']
        );
        $this->assertSame(['nb_visitors' => 1, 'nb_returned_1' => 1], $cohorts['2024-02-12']);
        $this->assertSame(['nb_visitors' => 1], $cohorts['2024-02-19']);
    }

    public function testAWeekWithoutNewVisitorsIsAnEmptyCohort(): void
    {
        $this->assertSame(
            ['nb_visitors' => 0, 'nb_returned_1' => 0, 'nb_returned_2' => 0, 'nb_returned_3' => 0],
            $this->cohorts()['2024-01-29']
        );
    }

    public function testWithAGoalACohortHasHowManyOfItConvertedInEachWeek(): void
    {
        $cohort = $this->cohorts(['idGoal' => 1])['2024-02-05'];

        $this->assertSame([1, 1, 10.0], [$cohort['nb_converted_0'], $cohort['nb_conversions_0'], $cohort['revenue_0']]);
        $this->assertSame([1, 1, 10.0], [$cohort['nb_converted_1'], $cohort['nb_conversions_1'], $cohort['revenue_1']]);
        $this->assertSame([0, 0, 0.0], [$cohort['nb_converted_2'], $cohort['nb_conversions_2'], $cohort['revenue_2']]);
    }

    public function testCohortsCanBeByMonth(): void
    {
        SiteWithCohorts::visitor(5, ['2024-03-05' => false]);

        $cohorts = $this->cohorts(['period' => 'month', 'date' => '2024-03-15']);

        $this->assertSame(['nb_visitors' => 5, 'nb_returned_1' => 1], $cohorts['2024-02-01']);
        $this->assertSame(['nb_visitors' => 0], $cohorts['2024-03-01']);
    }

    public function testCohortsCanBeByDay(): void
    {
        $cohorts = $this->cohorts(['period' => 'day', 'date' => '2024-02-15']);

        $this->assertSame([2, 2, 1], [$cohorts['2024-02-06']['nb_visitors'], $cohorts['2024-02-06']['nb_returned_7'], $cohorts['2024-02-06']['nb_returned_9']]);
        $this->assertSame(['nb_visitors' => 1, 'nb_returned_1' => 0, 'nb_returned_2' => 0], $cohorts['2024-02-13']);
    }

    public function testAWeekBeginsWhenItDoesInTheTimezoneOfTheWebsite(): void
    {
        $cohorts = $this->cohorts(['idSite' => 2]);

        $this->assertSame(0, $cohorts['2024-02-05']['nb_visitors']);
        $this->assertSame(['nb_visitors' => 1, 'nb_returned_1' => 1], $cohorts['2024-02-12']);
    }

    public function testAPeriodThatIsNotOverIsLeftOut(): void
    {
        $lastWeek = PeriodFactory::build('week', Date::today()->subWeek(1))->getDateStart()->toString();

        $this->assertSame($lastWeek, array_key_last($this->cohorts(['date' => 'today'])));
    }

    public function testASegmentPicksTheVisitsThatAreCounted(): void
    {
        $cohort = $this->cohorts(['segment' => 'visitorType==new'])['2024-02-05'];

        $this->assertSame(['nb_visitors' => 3, 'nb_returned_1' => 0, 'nb_returned_2' => 0], $cohort);
    }

    public function testThereAreNoCohortsByYear(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('by day, week or month');

        $this->cohorts(['period' => 'year']);
    }

    public function testThePageCanTellWhenTheCohortsWereArchived(): void
    {
        $parameters = ['idSite' => 1, 'period' => 'week', 'date' => SiteWithCohorts::DAY];

        $this->assertStringStartsWith('Report generated ', Request::processRequest('RebelCohorts.getReportGenerated', $parameters, []));
    }

    /**
     * @dataProvider getApiMethods
     */
    public function testTheCohortsNeedViewAccessToTheWebsite(string $method): void
    {
        FakeAccess::clearAccess(false, [], [2]);

        $this->expectException(NoAccessException::class);

        API::getInstance()->$method(1, 'week', SiteWithCohorts::DAY);
    }

    public function getApiMethods(): array
    {
        return [['getCohorts'], ['getReportGenerated']];
    }

    public function testThePageListsTheGoalsOfTheWebsite(): void
    {
        $_GET['idSite'] = 1;
        $_GET['period'] = 'week';

        $html = (new GetCohorts())->render();

        $this->assertStringContainsString('<option value="1">Signed up</option>', $html);
        // A text that was not translated shows as its key.
        $this->assertSame(0, preg_match('/RebelCohorts_[A-Z]/', $html));
    }

    public function testThePageAsksForAnotherPeriodWhenItIsAYear(): void
    {
        $_GET['idSite'] = 1;
        $_GET['period'] = 'year';

        $html = (new GetCohorts())->render();

        $this->assertStringContainsString('Cohorts are by day, week or month.', $html);
        $this->assertStringNotContainsString('rebelCohortsGrid', $html);
    }

    public function testThePageNeedsViewAccessToTheWebsite(): void
    {
        $_GET['idSite'] = 1;
        FakeAccess::clearAccess(false, [], [2]);

        $this->expectException(NoAccessException::class);

        (new GetCohorts())->render();
    }

    /**
     * @return array<string, array<string, mixed>> the first day of a cohort => its columns
     */
    private function cohorts(array $parameters = []): array
    {
        $table = Request::processRequest('RebelCohorts.getCohorts', $parameters + [
            'idSite' => 1,
            'period' => 'week',
            'date' => SiteWithCohorts::DAY,
        ], []);

        $cohorts = [];
        foreach ($table->getRows() as $row) {
            $columns = $row->getColumns();
            unset($columns['label'], $columns['date']);
            $cohorts[$row->getColumn('date')] = $columns;
        }

        return $cohorts;
    }

    protected static function configureFixture($fixture)
    {
        parent::configureFixture($fixture);
        $fixture->createSuperUser = true;
    }

    public function provideContainerConfig()
    {
        return ['Piwik\Access' => new FakeAccess()];
    }
}

CohortsTest::$fixture = new SiteWithCohorts();
