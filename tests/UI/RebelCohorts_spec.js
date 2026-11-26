describe('RebelCohorts', function () {
    this.fixture = 'Piwik\\Plugins\\RebelCohorts\\tests\\Fixtures\\SiteWithCohorts';

    before(function () {
        testEnvironment.pluginsToLoad = ['RebelCohorts'];
        testEnvironment.save();
    });

    async function open(period, selector) {
        const params = `idSite=1&period=${period}&date=2024-02-25`;
        await page.goto(`?module=CoreHome&action=index&${params}#?${params}&category=RebelCohorts_Cohorts&subcategory=RebelCohorts_Retention`);
        await page.waitForSelector(selector);
        await page.waitForNetworkIdle();
    }

    function texts(selector) {
        return page.evaluate((one) => [...document.querySelectorAll(one)]
            .map((element) => element.innerText.replace(/\s+/g, ' ').trim()), selector);
    }

    function cohorts() {
        return texts('.rebelCohortsGrid tbody tr');
    }

    it('should be in the menu with the icon of the plugin', async function () {
        await open('week', '.rebelCohortsGrid tbody tr');

        const menu = await page.evaluate(() => {
            const category = document.querySelector('#secondNavBar .rebelCohortsIcon').closest('li');
            // The first link is the one that opens the category for screen readers.
            return [...category.querySelectorAll('ul a')].map((link) => link.textContent.trim()).slice(1);
        });

        expect(menu).to.deep.equal(['Retention']);
    });

    it('should have a row for each cohort with the share that came back in the weeks that have numbers', async function () {
        expect(await texts('.rebelCohortsGrid thead th')).to.deep.equal(['First visit', 'New visitors', 'Week 1', 'Week 2']);
        expect(await cohorts()).to.deep.equal([
            'Feb 5 – 11, 2024 3 66.7% 33.3%',
            'Feb 12 – 18, 2024 1 100%',
            'Feb 19 – 25, 2024 1',
        ]);
        expect(await texts('.rebelCohortsGrid tfoot tr')).to.deep.equal(['All cohorts 5 75% 33.3%']);
    });

    it('should show the goals only when conversions are measured', async function () {
        const isGoalShown = () => page.evaluate(() => document.querySelector('.rebelCohortsGoalLabel').offsetParent !== null);

        expect(await isGoalShown()).to.equal(false);
    });

    it('should tell how many of 100 new visitors came back', async function () {
        expect(await texts('.rebelCohortsTile')).to.deep.equal(['WEEK 1 75 of 100 new visitors came back']);
    });

    it('should draw the cohort of a clicked row next to all cohorts', async function () {
        expect(await page.evaluate(() => document.querySelectorAll('.rebelCohortsCurve polyline').length)).to.equal(1);

        await page.evaluate(() => [...document.querySelectorAll('.rebelCohortsGrid tbody tr')]
            .find((row) => row.textContent.includes('Feb 5')).click());

        expect(await page.evaluate(() => document.querySelectorAll('.rebelCohortsCurve polyline').length)).to.equal(2);
        expect(await texts('.rebelCohortsLegendOne')).to.deep.equal(['The new visitors of Feb 5 – 11, 2024']);
    });

    it('should show the share that converted the picked goal, from the first week', async function () {
        await page.click('.rebelCohortsMeasure[data-measure="converted"]');
        await page.waitForNetworkIdle();

        expect(await texts('.rebelCohortsGrid thead th:nth-child(3)')).to.deep.equal(['The first week']);
        expect(await cohorts()).to.deep.equal([
            'Feb 5 – 11, 2024 3 33.3% 33.3% 0%',
            'Feb 12 – 18, 2024 1 0% 0%',
            'Feb 19 – 25, 2024 1 0%',
        ]);
        expect(await texts('.rebelCohortsTile')).to.include('THE FIRST WEEK 20 of 100 new visitors converted');
    });

    it('should tell when the report was generated', async function () {
        expect(await page.evaluate(() => document.querySelector('.rebelCohortsGenerated').textContent)).to.match(/^Report generated /);
    });

    it('should ask for another period when it is a year', async function () {
        await open('year', '.rebelCohortsHint');

        expect(await texts('.rebelCohortsHint')).to.deep.equal(['Cohorts are by day, week or month. Pick one of them as the period.']);
    });
});
