let datatableColis;
let datatableLoading = false;

let chartArrivalUm;
let chartAssoRecep;
let chartDailyArrival;
let chartWeeklyArrival;
let chartColis;
let chartMonetaryFiability;
let chartFirstForAdmin;
let chartSecondForAdmin;

$(function () {
    // config chart js
    Chart.defaults.global.defaultFontFamily = 'Myriad';
    //// charts monitoring réception arrivage
    drawChartWithHisto($('#chartArrivalUm'), 'get_arrival_um_statistics').then((chart) => {
        chartArrivalUm = chart;
    });
    drawChartWithHisto($('#chartAssocRecep'), 'get_asso_recep_statistics').then((chart) => {
        chartAssoRecep = chart;
    });
    //// charts monitoring réception quai
    drawSimpleChart($('#chartDailyArrival'), 'get_daily_arrivals_statistics').then((chart) => {
        chartDailyArrival = chart;
    })
    drawSimpleChart($('#chartWeeklyArrival'), 'get_weekly_arrivals_statistics').then((chart) => {
        chartWeeklyArrival = chart;
    });
    drawSimpleChart($('#chartColis'), 'get_daily_packs_statistics').then((chart) => {
        chartColis = chart;
    });
    drawSimpleChart($('#chartMonetaryFiability'), 'get_monetary_fiability_statistics').then((chart) => {
        chartMonetaryFiability = chart;
    });
    drawMultipleBarChart($('#chartFirstForAdmin'), 'get_encours_count_by_nature_and_timespan', {graph: 1}, 1).then((chart) => {
        chartFirstForAdmin = chart;
    });
    drawMultipleBarChart($('#chartSecondForAdmin'), 'get_encours_count_by_nature_and_timespan', {graph: 2}, 2).then((chart) => {
        chartSecondForAdmin = chart;
    });

    let reloadFrequency = 1000 * 60 * 15;
    setInterval(reloadDashboards, reloadFrequency);

    let $indicators = $('#indicators');
    $('#btnIndicators').mouseenter(function () {
        $indicators.fadeIn();
    });
    $('#blocIndicators').mouseleave(function () {
        $indicators.fadeOut();
    });
});

function reloadDashboards() {
    if (datatableColis) {
        datatableColis.ajax.reload();
    }
    updateCharts();
    updateCarriers();
    refreshIndicatorsReceptionDock();
    let now = new Date();
    $('.refreshDate').text(('0' + (now.getDate() + 1)).slice(-2) + '/' + ('0' + (now.getMonth() + 1)).slice(-2) + '/' + now.getFullYear() + ' à ' + now.getHours() + ':' + now.getMinutes());
}

function updateCharts() {
    drawChartWithHisto($('#chartArrivalUm'), 'get_arrival_um_statistics', 'now', chartArrivalUm);
    drawChartWithHisto($('#chartAssocRecep'), 'get_asso_recep_statistics', 'now', chartAssoRecep);
    drawSimpleChart($('#chartDailyArrival'), 'get_daily_arrivals_statistics', chartDailyArrival);
    drawSimpleChart($('#chartWeeklyArrival'), 'get_weekly_arrivals_statistics', chartWeeklyArrival);
    drawSimpleChart($('#chartColis'), 'get_daily_packs_statistics', chartColis);
    drawSimpleChart($('#chartMonetaryFiability'), 'get_monetary_fiability_statistics', chartMonetaryFiability);
    drawMultipleBarChart($('#chartFirstForAdmin'), 'get_encours_count_by_nature_and_timespan', {graph: 1}, 1, chartFirstForAdmin);
    drawMultipleBarChart($('#chartSecondForAdmin'), 'get_encours_count_by_nature_and_timespan', {graph: 2}, 2, chartSecondForAdmin);
    updateIndicBoxAdmin();
}

function drawChartWithHisto($button, path, beforeAfter = 'now', chart = null) {
    return new Promise(function (resolve) {
        let $dashboardBox = $button.closest('.dashboard-box');
        let $rangeBtns = $dashboardBox.find('.range-buttons');
        let $firstDay = $rangeBtns.find('.firstDay');
        let $lastDay = $rangeBtns.find('.lastDay');

        let $canvas = $dashboardBox.find('canvas');

        let params = {
            'firstDay': $firstDay.data('day'),
            'lastDay': $lastDay.data('day'),
            'beforeAfter': beforeAfter
        };
        $.get(Routing.generate(path), params, function (data) {
            let labels = Object.keys(data.data);
            let datas = Object.values(data.data).map((value) => {
                if (typeof value == 'object' && 'count' in value) {
                    return value['count'];
                } else {
                    return value;
                }
            });

            $firstDay.text(data.firstDay);
            $firstDay.data('day', data.firstDayData);
            $lastDay.text(data.lastDay);
            $lastDay.data('day', data.lastDayData);

            let bgColors = [];
            for (let i = 0; i < labels.length - 1; i++) {
                bgColors.push('rgba(163,209,255, 1)');
            }
            bgColors.push('rgba(57,181,74, 1)');

            $rangeBtns.removeClass('d-none');

            // cas rafraîchissement
            if (chart) {
                updateData(chart, data);
                resolve();
                // cas initialisation
            } else {
                resolve(newChart($canvas, labels, datas, bgColors));
            }
        });
    });
}

function updateData(chart, data) {
    let dataToParse = data.data ? data.data : data;
    chart.data.labels = Object.keys(dataToParse);
    chart.data.datasets[0].data = Object.values(dataToParse).map((value) => {
        if (typeof value == 'object' && 'count' in value) {
            return value['count'];
        } else if (typeof value == 'object') {
            return Object.values(value)[0];
        } else {
            return value;
        }
    });
    chart.update();
}

function updateDataForMultiple(chart, data, labels) {
    chart.data.labels = labels;
    chart.data.datasets = data;
    chart.update();
}

function drawSimpleChart($canvas, path, chart = null) {
    return new Promise(function (resolve) {
        $.get(Routing.generate(path), function (data) {
            let labels = Object.keys(data);
            let datas = Object.values(data);
            let bgColors = [];
            for (let i = 0; i < labels.length - 1; i++) {
                bgColors.push('rgba(163,209,255, 1)');
            }
            bgColors.push('rgba(57,181,74, 1)');
            // cas rafraîchissement
            if (chart) {
                updateData(chart, data);
                resolve();
            } else {
                resolve(newChart($canvas, labels, datas, bgColors));
            }
        });
    });
}

function drawMultipleBarChart($canvas, path, params, chartNumber, chart = null) {
    return new Promise(function (resolve) {
        $.get(Routing.generate(path, params), function (data) {
            $('#empForChart' + chartNumber).text(data.location);
            $('#totalForChart' + chartNumber).text(data.total);
            let datas = [];
            let labels = Object.keys(data.data);
            if (chartNumber === 1) {
                datas = Object.values(data.data).map((valueArray) => {
                    return Object.values(valueArray)[0];
                });
            } else {
                let datasets = [];
                Object.values(data.data).forEach((data) => {
                    Object.keys(data).forEach((key) => {
                        if (!datasets[key]) datasets[key] = [];
                        datasets[key].push(data[key]);
                    });
                });
                Object.keys(Object.values(data.data)[0]).forEach((key) => {
                    datas.push({
                        label: key,
                        backgroundColor: "#"+((1<<24)*Math.random()|0).toString(16),
                        data: datasets[key]
                    })
                });
            }
            let bgColors = [];
            for (let i = 0; i < labels.length - 1; i++) {
                bgColors.push('rgba(163,209,255, 1)');
            }
            bgColors.push('rgba(57,181,74, 1)');
            if (chart) {
                if (chartNumber === 1) {
                    updateData(chart, data);
                } else {
                    updateDataForMultiple(chart, datas, labels);
                }
                resolve();
            } else {
                resolve(newChart($canvas, labels, datas, bgColors, chartNumber === 2));
            }
        });
    });
}

function updateIndicBoxAdmin() {
    $.get(Routing.generate('get_encours_and_emergencies_admin', true), function(data) {
        $('#urgenceCountCount').text(data.urgenceCount);
        $('#enCoursUrgenceCount').text(data.enCoursUrgence.count);
        $('#enCoursLitigeCount').text(data.enCoursLitige.count);
        $('#enCoursClearanceCount').text(data.enCoursClearance.count);
        $('#enCoursLitigeLabel').text(data.enCoursLitige.label);
        $('#enCoursClearanceLabel').text(data.enCoursClearance.label);
        $('#enCoursUrgenceLabel').text(data.enCoursUrgence.label);
    });
}

function goToFilteredDemande(type, filter) {
    let path = '';
    if (type === 'livraison') {
        path = 'demande_index';
    } else if (type === 'collecte') {
        path = 'collecte_index';
    } else if (type === 'manutention') {
        path = 'manutention_index';
    }

    let params = {
        reception: 0,
        filter: filter
    };
    let route = Routing.generate(path, params);
    window.location.href = route;
}

function newChart($canvasId, labels, data, bgColors, isMultiple = false) {
    if ($canvasId.length) {
        return new Chart($canvasId, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: !isMultiple ? [{
                    data: data,
                    backgroundColor: bgColors
                }] : data
            },
            options: {
                tooltips: false,
                responsive: true,
                legend: {
                    display: isMultiple,
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                },
                animation: {
                    onComplete: displayFiguresOnChart
                }
            }
        });
    } else {
        return null;
    }
}

function displayFiguresOnChart() {
    let ctx = (this.chart.ctx);
    ctx.font = Chart.helpers.fontString(Chart.defaults.global.defaultFontFamily, 'bold', Chart.defaults.global.defaultFontFamily);
    ctx.fillStyle = "white";
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';

    this.data.datasets.forEach(function (dataset) {
        for (let i = 0; i < dataset.data.length; i++) {
            for (let key in dataset._meta) {
                if (parseInt(dataset.data[i]) > 0) {
                    let model = dataset._meta[key].data[i]._model;
                    ctx.fillText(dataset.data[i], model.x, model.y + 5);
                }
            }
        }
    });
}

function loadRetards() {
    const $retardsTable = $('.retards-table');

    if (!datatableLoading) {
        datatableLoading = true;
        if (datatableColis) {
            datatableColis.destroy();
        }
        datatableColis = $retardsTable.DataTable({
            responsive: true,
            dom: 'tr',
            paging: false,
            scrollCollapse: true,
            scrollY: '18vh',
            processing: true,
            "language": {
                url: "/js/i18n/dataTableLanguage.json",
            },
            ajax: {
                "url": Routing.generate('api_retard', true),
                "type": "GET",
            },
            initComplete: () => {
                datatableLoading = false;
            },
            columns: [
                {"data": 'colis', 'name': 'colis', 'title': 'Colis'},
                {"data": 'date', 'name': 'date', 'title': 'Dépose'},
                {"data": 'time', 'name': 'delai', 'title': 'Délai'},
                {"data": 'emp', 'name': 'emp', 'title': 'Emplacement'},
            ]
        });
    }
}

function refreshIndicatorsReceptionDock() {
    $.get(Routing.generate('get_indicators_reception_dock'), function(data) {
        $('#enCoursDock').text(data.enCoursDock.count);
        $('#enCoursClearance').text(data.enCoursClearance ? data.enCoursClearance.count : '-');
        $('#enCoursCleared').text(data.enCoursCleared ? data.enCoursCleared.count : '-');
        $('#enCoursDropzone').text(data.enCoursDropzone ? data.enCoursDropzone.count : '-');
        $('#urgenceCount').text(data.urgenceCount ? data.urgenceCount.count : '-');
    });
}

function updateCarriers() {
    $.get(Routing.generate('get_daily_carriers_statistics'), function(data) {
        const $container = $('#statistics-arrival-carriers');
        $container.empty();
        $container.append(
            ...((data || []).map((carrier) => ($('<p/>', {text: carrier}))))
        );
    });
}
