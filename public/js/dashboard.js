let datatableColis;

let chartArrivalUm;
let chartAssoRecep;
let chartDailyArrival;
let chartWeeklyArrival;
let chartColis;

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

    let reloadFrequency = 1000 * 60 * 15;
    setInterval(reloadDashboards, reloadFrequency);

    let $indicators = $('#indicators');
    $('#btnIndicators').mouseenter(function () {
        $indicators.fadeIn();
    });
    $('#blocIndicators').mouseleave(function() {
        $indicators.fadeOut();
    });
});

function reloadDashboards() {
    if (datatableColis) {
        datatableColis.ajax.reload();
    }
    updateCharts();
    let now = new Date();
    $('.refreshDate').text(('0' + (now.getDate() + 1)).slice(-2) + '/' + ('0' + (now.getMonth() + 1)).slice(-2) + '/' + now.getFullYear() + ' à ' + now.getHours() + ':' + now.getMinutes());
}

function updateCharts() {
    drawChartWithHisto($('#chartArrivalUm'), 'get_arrival_um_statistics', 'now', chartArrivalUm);
    drawChartWithHisto($('#chartAssocRecep'), 'get_asso_recep_statistics', 'now', chartAssoRecep);
    drawSimpleChart($('#chartDailyArrival'), 'get_daily_arrivals_statistics', 'now', chartDailyArrival);
    drawSimpleChart($('#chartWeeklyArrival'), 'get_weekly_arrivals_statistics', 'now', chartWeeklyArrival);
    drawSimpleChart($('#chartColis'), 'get_daily_packs_statistics', 'now', chartColis);
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
            'beforeAfter' : beforeAfter
        };
        $.post(Routing.generate(path), params, function(data) {
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
    chart.data.labels = Object.keys(data.data);
    chart.data.datasets[0].data = Object.values(data.data).map((value) => {
        if (typeof value == 'object' && 'count' in value) {
            return value['count'];
        } else {
            return value;
        }
    });
    chart.update();
}

function drawSimpleChart($canvas, path) {
    return new Promise(function (resolve) {
        $.get(Routing.generate(path), function (data) {
            let labels = Object.keys(data);
            let datas = Object.values(data);
            let bgColors = [];
            for (let i = 0; i < labels.length - 1; i++) {
                bgColors.push('rgba(163,209,255, 1)');
            }
            bgColors.push('rgba(57,181,74, 1)');
            resolve(newChart($canvas, labels, datas, bgColors));
        });
    });
}

function goToFilteredDemande(type, filter){
    let path = '';
    if (type === 'livraison'){
        path = 'demande_index';
    } else if (type === 'collecte') {
        path = 'collecte_index';
    } else if (type === 'manutention'){
        path = 'manutention_index';
    }

    let params = {
        reception: 0,
        filter: filter
    };
    let route = Routing.generate(path, params);
    window.location.href = route;
}

function newChart($canvasId, labels, data, bgColors) {
    if ($canvasId.length) {
        return new Chart($canvasId, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: bgColors
                }]
            },
            options: {
                tooltips: false,
                responsive: true,
                legend: {
                    display: false,
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

    this.data.datasets.forEach(function (dataset)
    {
        for (let i = 0; i < dataset.data.length; i++) {
            for(let key in dataset._meta)
            {
                if (parseInt(dataset.data[i]) > 0){
                    let model = dataset._meta[key].data[i]._model;
                    ctx.fillText(dataset.data[i], model.x, model.y + 5);
                }
            }
        }
    });
}