const chartsLoading = {};
let datatableColis;
let datatableLoading = false;
let timeoutResize;

$(function () {
    // config chart js
    Chart.defaults.global.defaultFontFamily = 'Myriad';

    google.charts.load('current', {'packages':['corechart']});
    // google.charts.setOnLoadCallback(drawAllCharts);

    // loadRetards();
    // setSmallBoxContent();

    $(window).on('resize', () => {
        if (timeoutResize) {
            clearTimeout(timeoutResize);
        }
        timeoutResize = setTimeout(() => {
            // si aucun diagramme ne charge on relance le drawAll
            if (Object.keys(chartsLoading).every((key) => !chartsLoading[key])) {
                // drawAllCharts();
            }

            timeoutResize = undefined;
        });
    });

    let reloadFrequency = 1000 * 60 * 15;
    setInterval(reloadPage, reloadFrequency);

    let $indicators = $('#indicators');
    $('#btnIndicators').mouseenter(function () {
        $indicators.fadeIn();
    });
    $('#blocIndicators').mouseleave(function() {
        $indicators.fadeOut();
    });
});

function reloadPage() {
    // drawAllCharts();
    // reloadDashboardLinks();
    if (datatableColis) {
        datatableColis.ajax.reload();
    }
}

function drawChartArrival($button, path, fromStart = true, after = true) {
    let $dashboardBox = $button.closest('.dashboard-box');
    let $rangeBtns = $dashboardBox.find('.range-buttons');
    $rangeBtns.addClass('d-none');

    let $canvas = $dashboardBox.find('canvas');
    let params = {
        'firstDay': $('#chartArrivalUm + .range-buttons > .firstDay').data('day'),
        'lastDay': $('#chartArrivalUm + .range-buttons > .lastDay').data('day'),
        'after': (fromStart ? 'now' : after)
    };
    $.post(Routing.generate(path), params, function(data) {
        let labels = Object.keys(data);
        let datas = Object.values(data);
        let bgColors = [];
        for (let i = 0; i < labels.length - 1; i++) {
            bgColors.push('rgba(163,209,255, 1)');
        }
        bgColors.push('rgba(57,181,74, 1)');

        newChart($canvas, labels, datas, bgColors);
        $rangeBtns.removeClass('d-none');
    });
}

function drawChartDock($canvas, path) {
    $.get(Routing.generate(path), function (data) {
        let labels = Object.keys(data);
        let datas = Object.values(data);
        let bgColors = [];
        for (let i = 0; i < labels.length - 1; i++) {
            bgColors.push('rgba(163,209,255, 1)');
        }
        bgColors.push('rgba(57,181,74, 1)');
        newChart($canvas, labels, datas, bgColors);
    });
}

// function drawChartMonetary() {
//     if ($('#dashboard-monetary').length) {
//         $('#dashboard-monetary .spinner-border').show();
//         let path = Routing.generate('graph_monetaire', true);
//
//         $('#curve_chart').empty();
//
//         chartsLoading['monetary'] = true;
//         $.ajax({
//             url: path,
//             dataType: "json",
//             type: "GET",
//             contentType: "application/json; charset=utf-8",
//             success: function (data) {
//                 let tdata = new google.visualization.DataTable();
//
//                 tdata.addColumn('string', 'Month');
//                 tdata.addColumn('number', 'Fiabilité monétaire');
//
//                 $.each(data, function (index, value) {
//                     tdata.addRow([value.mois, value.nbr]);
//                 });
//
//                 let options = {
//                     curveType: 'function',
//                     backdropColor: 'transparent',
//                     legend: 'none',
//                     backgroundColor: 'transparent',
//                 };
//
//                 let chart = new google.visualization.LineChart($('#curve_chart')[0]);
//                 chart.draw(tdata, options);
//
//                 chartsLoading['monetary'] = false;
//
//                 $('#dashboard-monetary .spinner-border').hide();
//             }
//         });
//     }
// }

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

// function loadRetards() {
//     let routeForLate = Routing.generate('api_retard', true);
//
//     const $retardsTable = $('.retards-table');
//
//     if (!datatableLoading) {
//         const clientHeight = document.body.clientHeight;
//         datatableLoading = true;
//         if (datatableColis) {
//             datatableColis.destroy();
//         }
//         datatableColis = $retardsTable.DataTable({
//             responsive: true,
//             dom: 'tipr',
//             pagingType: 'simple',
//             pageLength: (
//                 clientHeight < 800 ? 2 :
//                 clientHeight < 900 ? 3 :
//                 clientHeight < 1000 ? 4 :
//                 6
//             ),
//             processing: true,
//             "language": {
//                 url: "/js/i18n/dataTableLanguage.json",
//             },
//             ajax: {
//                 "url": routeForLate,
//                 "type": "POST",
//             },
//             initComplete: () => {
//                 datatableLoading = false;
//             },
//             columns: [
//                 {"data": 'colis', 'name': 'colis', 'title': 'Colis'},
//                 {"data": 'date', 'name': 'date', 'title': 'Dépose'},
//                 {"data": 'time', 'name': 'delai', 'title': 'Délai'},
//                 {"data": 'emp', 'name': 'emp', 'title': 'Emplacement'},
//             ]
//         });
//     }
// }

//// charts monitoring réception arrivage
drawChartArrival($('#chartArrivalUm'), 'get_arrival_um_statistics', true);
drawChartArrival($('#chartAssocRecep'), 'get_asso_recep_statistics', true);

//// charts monitoring réception quai
drawChartDock($('#chartDailyArrival'), 'get_daily_arrivals_statistics')
drawChartDock($('#chartWeeklyArrival'), 'get_weekly_arrivals_statistics')
drawChartDock($('#chartColis'), 'get_daily_packs_statistics')

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
                let model = dataset._meta[key].data[i]._model;
                ctx.fillText(dataset.data[i], model.x, model.y + 5);
            }
        }
    });
}
