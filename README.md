# Rebel Cohorts

[![PHPCS check](https://github.com/Digitalist-Open-Cloud/RebelCohorts/actions/workflows/phpcs.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/RebelCohorts/actions/workflows/phpcs.yaml)
[![Semgrep OSS scan](https://github.com/Digitalist-Open-Cloud/RebelCohorts/actions/workflows/semgrep.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/RebelCohorts/actions/workflows/semgrep.yaml)
[![Test plugin with Matomo](https://github.com/Digitalist-Open-Cloud/RebelCohorts/actions/workflows/matomo.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/RebelCohorts/actions/workflows/matomo.yaml)
[![PHPStan check](https://github.com/Digitalist-Open-Cloud/RebelCohorts/actions/workflows/phpstan.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/RebelCohorts/actions/workflows/phpstan.yaml)

A Matomo plugin that shows whether new visitors come back. A cohort is the visitors who came for
the first time in the same day, week or month. The plugin follows each cohort through the
periods after: how many of them came back, and how many converted a goal.

## Install

Activate the plugin. It needs no change to the tracking code. The report is filled when Matomo
archives; to fill it for periods that are archived already, run:

```sh
./console core:invalidate-report-data --dates=<from>,<to> --plugin=RebelCohorts
./console core:archive
```

Weeks and months are counted from the visits themselves and not from the reports of their days.
Fill them while Matomo still has the visits: a period whose visits are deleted cannot be filled.

## The page

**Cohorts > Retention** in the reporting menu. The period you pick in Matomo is what a cohort is:
with a week picked, the page has the twelve latest weeks, ending with the picked one. A period
that is not over is left out. With a year or a range picked there is nothing to show.

Pick what to measure at the top:

- **Came back**: the share of a cohort that visited again in each period after the first.
- **Converted**: the share that converted the goal you pick, from the period of the first visit on.
  A website with ecommerce also has its orders to pick.

The page has three parts:

- How many of 100 new visitors came back or converted, at three points in time, and which cohort
  was best and weakest.
- A chart of how the share falls, for all cohorts as one. Click a row of the table to draw that
  cohort next to it.
- A table with a row for each cohort and a column for each period after. A darker cell is a
  larger share.

Under the report is when it was generated, as under the reports of Matomo.

## What counts

- A visitor is who Matomo recognises as one by the visitor ID. A visitor who changes device or
  clears cookies is a new visitor. Without cookies Matomo does not recognise a visitor for longer
  than a visit, and the cohorts say little.
- A visitor counts once in a period, however many visits they made in it.
- A period begins when it does in the timezone of the website.
- A segment picks the visits that are counted. The size of a cohort is its new visitors with a
  visit that matches.

## API

`RebelCohorts.getCohorts` needs view access to the website. It takes `idSite`, `period` (`day`,
`week` or `month`), `date`, and optionally `idGoal` (0 for ecommerce orders) and `segment`. A row
is a cohort: `nb_visitors` is its new visitors and `nb_returned_<n>` how many of them came back
`n` periods after. With a goal it also has `nb_converted_<n>`, `nb_conversions_<n>` and
`revenue_<n>`, from 0 for the period of the first visit.

## License

GPL v3 or later, see [LICENSE.md](LICENSE.md).
