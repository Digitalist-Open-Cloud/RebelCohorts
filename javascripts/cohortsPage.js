/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

(function () {
    var SVG = 'http://www.w3.org/2000/svg';
    // The periods after the first visit that the tiles tell about, for each measure.
    var TILES = { back: [1, 4, 8], converted: [0, 1, 4], revenue: [0, 4, 8] };
    // The darkest a cell of the table gets.
    var DARKEST = 0.55;
    // Best and weakest are told when at least this many cohorts can be compared.
    var COMPARED = 3;

    function el(tag, className, text) {
        var element = document.createElement(tag);
        element.className = className || '';
        if (text !== undefined) {
            element.textContent = text;
        }
        return element;
    }

    function svg(tag, attributes, text) {
        var element = document.createElementNS(SVG, tag);
        Object.keys(attributes).forEach(function (name) {
            element.setAttribute(name, attributes[name]);
        });
        if (text !== undefined) {
            element.textContent = text;
        }
        return element;
    }

    function number(value) {
        return window.CoreHome.NumberFormatter.formatNumber(value, 1);
    }

    function percent(share) {
        return number(100 * share) + '%';
    }

    function measure(root) {
        return root.querySelector('.rebelCohortsMeasure[aria-pressed="true"]').getAttribute('data-measure');
    }

    // A share of new visitors, or revenue for each of them.
    function show(root, value) {
        return measure(root) === 'revenue'
            ? window.CoreHome.NumberFormatter.formatCurrency(value, root.getAttribute('data-currency'), 2)
            : percent(value);
    }

    // How many of a cohort came back, or converted, a number of periods after their first visit.
    // For revenue: what the cohort brought in up to and including that period.
    // Null when that period is not over yet.
    function count(root, row, after) {
        var picked = measure(root);
        if (!row.nb_visitors) {
            return null;
        }
        if (picked === 'revenue') {
            var revenue = 0;
            for (var period = 0; period <= after; period++) {
                revenue += row['revenue_' + period];
            }
            return row['revenue_' + after] === undefined ? null : revenue;
        }
        var value = row[(picked === 'back' ? 'nb_returned_' : 'nb_converted_') + after];
        return value === undefined ? null : value;
    }

    function share(root, row, after) {
        var value = count(root, row, after);
        return value === null ? null : value / row.nb_visitors;
    }

    // The share of all cohorts that have the period, as if they were one.
    function average(root, rows, after) {
        var counted = 0;
        var visitors = 0;
        rows.forEach(function (row) {
            var value = count(root, row, after);
            if (value !== null) {
                counted += value;
                visitors += row.nb_visitors;
            }
        });
        return visitors ? counted / visitors : null;
    }

    // The periods after the first visit that have numbers: the first visit itself has them for a goal only.
    function periods(root, rows) {
        var list = [];
        for (var after = measure(root) === 'back' ? 1 : 0; after < rows.length; after++) {
            if (average(root, rows, after) !== null) {
                list.push(after);
            }
        }
        return list;
    }

    function heading(root, after) {
        var texts = root.rebelCohorts.texts;
        return after === 0 ? texts.first : texts.period.replace('%s', after);
    }

    function drawTiles(root, rows, list) {
        var texts = root.rebelCohorts.texts;
        var box = root.querySelector('.rebelCohortsTiles');
        box.textContent = '';
        TILES[measure(root)].forEach(function (after) {
            if (list.indexOf(after) === -1) {
                return;
            }
            var tile = el('div', 'rebelCohortsTile');
            tile.appendChild(el('span', 'rebelCohortsTileWhen', heading(root, after)));
            var value = average(root, rows, after);
            tile.appendChild(el('strong', '', measure(root) === 'revenue' ? show(root, value) : number(100 * value)));
            tile.appendChild(el('span', '', texts[measure(root)].tile));
            box.appendChild(tile);
        });

        // The best and the weakest cohort in the first of the periods.
        var say = root.querySelector('.rebelCohortsSay');
        say.textContent = '';
        var compared = rows.filter(function (row) { return share(root, row, list[0]) !== null; })
            .sort(function (a, b) { return share(root, b, list[0]) - share(root, a, list[0]); });
        var best = compared[0];
        var weakest = compared[compared.length - 1];
        if (compared.length < COMPARED || share(root, best, list[0]) === share(root, weakest, list[0])) {
            return;
        }
        [[texts.best, best], [texts.weakest, weakest]].forEach(function (one) {
            say.appendChild(el('p', '', one[0].replace('%1$s', heading(root, list[0])).replace('%2$s', one[1].label)
                .replace('%3$s', texts[measure(root)].share.replace('%s', show(root, share(root, one[1], list[0]))))));
        });
    }

    function drawCurve(root, rows, list) {
        var state = root.rebelCohorts;
        var width = 640;
        var height = 200;
        var left = 48;
        var right = 16;
        var top = 10;
        var bottom = 24;
        var lines = [['rebelCohortsAll', list.map(function (after) { return average(root, rows, after); })]];
        var picked = rows[state.picked];
        if (picked) {
            lines.push(['rebelCohortsOne', list.map(function (after) { return share(root, picked, after); }).filter(function (value) { return value !== null; })]);
        }
        var max = Math.max.apply(null, lines[0][1].concat(picked ? lines[1][1] : [])) * 1.1 || 1;
        var x = function (index) { return left + (list.length > 1 ? index * (width - left - right) / (list.length - 1) : 0); };
        var y = function (value) { return top + (height - top - bottom) * (1 - value / max); };

        var chart = svg('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img' });
        [0, max / 2.2, max / 1.1].forEach(function (value) {
            chart.appendChild(svg('line', { x1: left, x2: width - right, y1: y(value), y2: y(value), 'class': 'rebelCohortsAxis' }));
            chart.appendChild(svg('text', { x: left - 6, y: y(value) + 4, 'text-anchor': 'end' }, show(root, value)));
        });
        list.forEach(function (after, index) {
            chart.appendChild(svg('text', { x: x(index), y: height - 6, 'text-anchor': index === 0 ? 'start' : index === list.length - 1 ? 'end' : 'middle' }, state.texts.period.replace('%s', after)));
        });
        lines.forEach(function (line) {
            chart.appendChild(svg('polyline', { 'class': line[0], points: line[1].map(function (value, index) { return x(index) + ',' + y(value); }).join(' ') }));
            line[1].forEach(function (value, index) {
                var dot = svg('circle', { 'class': line[0], cx: x(index), cy: y(value), r: 3 });
                dot.appendChild(svg('title', {}, heading(root, list[index]) + ': ' + show(root, value)));
                chart.appendChild(dot);
            });
        });

        var box = root.querySelector('.rebelCohortsCurve');
        box.textContent = '';
        box.appendChild(chart);

        var legend = root.querySelector('.rebelCohortsLegendOne');
        legend.hidden = !picked;
        legend.lastElementChild.textContent = picked ? state.texts.newVisitorsOf.replace('%s', picked.label) : '';
    }

    function cell(root, value, of, max) {
        var td = el('td');
        if (value === null) {
            return td;
        }
        var tint = el('span', 'rebelCohortsTint');
        tint.style.opacity = max ? (DARKEST * (value / of) / max).toFixed(3) : 0;
        td.appendChild(tint);
        td.appendChild(el('span', '', show(root, value / of)));
        td.title = root.rebelCohorts.texts[measure(root) === 'revenue' ? 'revenueOf' : 'ofNewVisitors']
            .replace('%1$s', measure(root) === 'revenue' ? show(root, value) : number(value)).replace('%2$s', number(of));
        return td;
    }

    function drawGrid(root, rows, list) {
        var state = root.rebelCohorts;
        var table = root.querySelector('.rebelCohortsGrid');
        var max = Math.max.apply(null, rows.map(function (row) {
            return Math.max.apply(null, list.map(function (after) { return share(root, row, after) || 0; }));
        }));
        table.textContent = '';

        var head = table.appendChild(el('thead')).appendChild(el('tr'));
        [state.texts.firstVisit, state.texts.newVisitors].concat(list.map(function (after) { return heading(root, after); })).forEach(function (text) {
            head.appendChild(el('th', '', text));
        });

        var body = table.appendChild(el('tbody'));
        rows.forEach(function (row, index) {
            // A period without new visitors has no cohort to follow.
            if (!row.nb_visitors) {
                return;
            }
            var tr = body.appendChild(el('tr'));
            tr.setAttribute('tabindex', '0');
            tr.setAttribute('aria-selected', index === state.picked ? 'true' : 'false');
            tr.appendChild(el('td', '', row.label));
            tr.appendChild(el('td', '', number(row.nb_visitors)));
            list.forEach(function (after) {
                tr.appendChild(cell(root, count(root, row, after), row.nb_visitors, max));
            });

            var pick = function () {
                state.picked = state.picked === index ? null : index;
                draw(root);
            };
            tr.addEventListener('click', pick);
            tr.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    pick();
                }
            });
        });

        var all = rows.reduce(function (sum, row) { return sum + row.nb_visitors; }, 0);
        var foot = table.appendChild(el('tfoot')).appendChild(el('tr'));
        foot.appendChild(el('td', '', state.texts.allCohorts));
        foot.appendChild(el('td', '', number(all)));
        list.forEach(function (after) {
            foot.appendChild(el('td', '', show(root, average(root, rows, after))));
        });
    }

    function draw(root) {
        var rows = root.rebelCohorts.rows;
        var list = periods(root, rows);
        var report = root.querySelector('.rebelCohortsReport');
        var hint = root.querySelector('.rebelCohortsHint');
        if (hint) {
            hint.remove();
        }
        report.hidden = !list.length;
        if (!list.length) {
            root.insertBefore(el('p', 'rebelCohortsHint', root.rebelCohorts.texts.noData), report);
            return;
        }

        drawTiles(root, rows, list);
        drawCurve(root, rows, list);
        drawGrid(root, rows, list);
    }

    // Asks the API. An answer that comes after something else was picked is dropped.
    function ask(root, method, parameters, callback) {
        var state = root.rebelCohorts;
        var asked = state.asked;
        var ajax = new window.ajaxHelper();
        parameters.module = 'API';
        parameters.method = 'RebelCohorts.' + method;
        parameters.format = 'json';
        parameters.filter_limit = -1;
        ajax.addParams(parameters, 'get');
        ajax.setFormat('json');
        ajax.setCallback(function (answer) {
            if (state.asked === asked && answer && answer.result !== 'error') {
                callback(answer);
            }
        });
        ajax.send();
    }

    function load(root) {
        var state = root.rebelCohorts;
        var isGoal = measure(root) !== 'back';
        state.asked++;
        root.querySelector('.rebelCohortsGoalLabel').hidden = !isGoal;
        root.querySelector('.rebelCohortsRevenueHelp').hidden = measure(root) !== 'revenue';

        ask(root, 'getCohorts', isGoal ? { idGoal: root.querySelector('.rebelCohortsGoal').value } : {}, function (rows) {
            state.rows = rows;
            draw(root);
        });
        ask(root, 'getReportGenerated', {}, function (generated) {
            root.querySelector('.rebelCohortsGenerated').textContent = generated.value || '';
        });
    }

    function start(root) {
        root.rebelCohorts = { texts: JSON.parse(root.getAttribute('data-texts')), asked: 0, rows: [], picked: null };

        root.querySelectorAll('.rebelCohortsMeasure').forEach(function (button) {
            button.addEventListener('click', function () {
                root.querySelectorAll('.rebelCohortsMeasure').forEach(function (other) {
                    other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
                });
                load(root);
            });
        });
        root.querySelector('.rebelCohortsGoal').addEventListener('change', function () {
            load(root);
        });

        load(root);
    }

    // Matomo puts the widget in the page after it has loaded, and again when the date changes.
    function startAll() {
        document.querySelectorAll('.rebelCohorts:not([data-started])').forEach(function (root) {
            root.setAttribute('data-started', '1');
            start(root);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        startAll();
        new MutationObserver(startAll).observe(document.body, { childList: true, subtree: true });
    });
})();
