<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelCohorts\tests\Fixtures;

use Piwik\Plugins\Goals\API as GoalsApi;
use Piwik\Tests\Framework\Fixture;

/**
 * A website with the goal "Signed up" and five visitors. Weeks begin on a Monday.
 * - Three came first in the week of 5 February: one came back in each of the two weeks after
 *   and signed up in the first of them, one came back twice in the week after, and one signed
 *   up in the first visit and never came back.
 * - One came first in the week of 12 February and came back the week after.
 * - One came first in the week of 19 February.
 *
 * A second website, twelve hours ahead of UTC, has one visitor who came first when it was Monday
 * 12 February there and still Sunday in UTC, and came back in the week after.
 */
class SiteWithCohorts extends Fixture
{
    // A day of the last week that has visits.
    public const DAY = '2024-02-25';

    public function setUp(): void
    {
        if (!self::siteCreated(1)) {
            self::createWebsite('2023-01-01 00:00:00', 0, 'Cohorts', 'http://example.org');
            self::createWebsite('2023-01-01 00:00:00', 0, 'Ahead', 'http://example.org', 1, null, null, 'Pacific/Fiji');
        }

        if (!GoalsApi::getInstance()->getGoals(1)) {
            GoalsApi::getInstance()->addGoal(1, 'Signed up', 'url', '/signup', 'contains', false, 10);
        }

        self::visitor(1, ['2024-02-06' => false, '2024-02-13' => true, '2024-02-20' => false]);
        self::visitor(2, ['2024-02-06' => false, '2024-02-13' => false, '2024-02-15' => false]);
        self::visitor(3, ['2024-02-07' => true]);
        self::visitor(4, ['2024-02-13' => false, '2024-02-21' => false]);
        self::visitor(5, ['2024-02-20' => false]);

        self::visitor(6, ['2024-02-11 14:00:00' => false, '2024-02-20 14:00:00' => false], 2);
    }

    public function tearDown(): void
    {
    }

    /**
     * The visits of one visitor.
     *
     * @param array<string, bool> $visits the day, or the time in UTC, of a visit => whether the visitor signs up in it
     */
    public static function visitor(int $visitor, array $visits, int $idSite = 1): void
    {
        $tracker = self::getTracker($idSite, self::DAY . ' 10:00:00', true, true);
        $tracker->setTokenAuth(self::getTokenAuth());
        $tracker->setVisitorId(sprintf('%016x', $visitor));
        $tracker->setIp(long2ip(ip2long('10.0.0.0') + $visitor));

        foreach ($visits as $time => $signsUp) {
            $time = strtotime((strlen($time) === 10 ? $time . ' 10:00:00' : $time) . ' UTC');
            $tracker->setForceVisitDateTime(gmdate('Y-m-d H:i:s', $time));
            $tracker->setUrl('http://example.org/');
            self::checkResponse($tracker->doTrackPageView('Start'));

            if ($signsUp) {
                $tracker->setForceVisitDateTime(gmdate('Y-m-d H:i:s', $time + 300));
                $tracker->setUrl('http://example.org/signup');
                self::checkResponse($tracker->doTrackPageView('Sign up'));
            }
        }
    }
}
