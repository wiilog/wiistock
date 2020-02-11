const chartsLoading = {};
let datatableColis;
let datatableLoading = false;
let timeoutResize;

$(function () {

    google.charts.load('current', {'packages':['corechart']});
    google.charts.setOnLoadCallback(drawAllCharts);

    loadRetards();
    setSmallBoxContent();

    $(window).on('resize', () => {
        if (timeoutResize) {
            clearTimeout(timeoutResize);
        }
        timeoutResize = setTimeout(() => {
            // si aucun diagramme ne charge on relance le drawAll
            if (Object.keys(chartsLoading).every((key) => !chartsLoading[key])) {
                drawAllCharts();
            }

            loadRetards();
            setSmallBoxContent();
            timeoutResize = undefined;
        });
    });

    let reloadFrequency = 1000 * 60 * 15;
    setInterval(reloadPage, reloadFrequency);
});

function setSmallBoxContent() {
    const $dashboardBoxContent = $('.dashboard-box-content');
    const clientHeight = document.body.clientHeight;
    if (clientHeight < 800) {
        $dashboardBoxContent.addClass('dashboard-box-content-small');
    }
    else {
        $dashboardBoxContent.removeClass('dashboard-box-content-small');
    }
}
function drawAllCharts() {
    drawChart('dashboard-assoc');
    drawChart('dashboard-arrival');
    drawChartMonetary();
    reloadDashboardLinks();
}

function reloadPage() {
    drawAllCharts();
    reloadDashboardLinks();
    if (datatableColis) {
        datatableColis.ajax.reload();
    }
}

function reloadDashboardLinks() {
    $.post(Routing.generate('get_dashboard', true), function(resp) {
        $('#dashboardLinks').html(resp);
    });
}

function drawChart(parent, after = true, fromStart = true) {
    if ($('#' + parent).length) {
        $('#' + parent + ' > .range-buttons').hide();
        $('#' + parent + ' .spinner-container').show();
        let data = new google.visualization.DataTable();
        let currentWeekRoute = Routing.generate(parent, true);
        let params = {
            'firstDay': $('#' + parent + ' > .range-buttons > .firstDay').data('day'),
            'lastDay': $('#' + parent + ' > .range-buttons > .lastDay').data('day'),
            'after': (fromStart ? 'now' : after)
        };

        $('#' + parent + ' > .chart').empty();

        chartsLoading[parent] = true;
        $.post(currentWeekRoute, JSON.stringify(params), function (chartData) {
            chartData.columns.forEach(column => {
                if (column.annotation) {
                    data.addColumn({type: column.type, role: column.role});
                } else {
                    data.addColumn(column.type, column.value);
                }
            });
            for (const [key, value] of Object.entries(chartData.rows)) {
                if (value.conform !== undefined) {
                    data.addRow([
                        key,
                        Number(value.count) !== 0 ? Number(value.count) : null,
                        value.conform,
                        key + ' : ' + String(value.conform) + '%'
                    ]);
                } else {
                    data.addRow([key, Number(value.count) !== 0 ? Number(value.count) : null]);
                }
            }
            let options = {
                vAxes: {
                    0: {
                        minValue: 1,
                        format: '#',
                        textStyle: {
                            color: 'black',
                            fontName: 'Montserrat',
                        }
                    },
                    1: {
                        maxValue: 100,
                        minValue: 0,
                        format: '#',
                        gridlines: {color: 'transparent'},
                        textStyle: {
                            color: 'black',
                            fontName: 'Montserrat',
                        }
                    },
                },
                hAxis: {
                    textStyle: {
                        color: 'black',
                        fontName: 'Montserrat',
                    }
                },
                interpolateNulls: true,
                seriesType: 'bars',
                pointSize: 5,
                series: {
                    1: {
                        pointShape: 'circle',
                        type: 'line',
                        targetAxisIndex: 1,
                    }
                },
                legend: {
                    position: 'top',
                    textStyle: {
                        color: 'black',
                        fontName: 'Montserrat',
                    }
                },
                colors: ['#130078', 'Red'],
                backgroundColor: {
                    fill: 'transparent'
                }
            };
            let chart = new google.visualization.ColumnChart($('#' + parent + ' > .chart')[0]);
            chart.draw(data, options);

            $('#' + parent + ' > .range-buttons > .firstDay').data('day', chartData.firstDayData);
            $('#' + parent + ' > .range-buttons > .firstDay').text(chartData.firstDay + ' - ');
            $('#' + parent + ' > .range-buttons > .lastDay').data('day', chartData.lastDayData);
            $('#' + parent + ' > .range-buttons > .lastDay').text(chartData.lastDay);
            $('#' + parent + ' > .range-buttons').show();
            $('#' + parent + ' .spinner-container').hide();

            chartsLoading[parent] = false;
        }, 'json');
    }
}

function drawChartMonetary() {
    if ($('#dashboard-monetary').length) {
        $('#dashboard-monetary .spinner-border').show();
        let path = Routing.generate('graph_monetaire', true);

        $('#curve_chart').empty();

        chartsLoading['monetary'] = true;
        $.ajax({
            url: path,
            dataType: "json",
            type: "GET",
            contentType: "application/json; charset=utf-8",
            success: function (data) {
                let tdata = new google.visualization.DataTable();

                tdata.addColumn('string', 'Month');
                tdata.addColumn('number', 'Fiabilité monétaire');

                $.each(data, function (index, value) {
                    tdata.addRow([value.mois, value.nbr]);
                });

                let options = {
                    curveType: 'function',
                    backdropColor: 'transparent',
                    legend: 'none',
                    backgroundColor: 'transparent',
                };

                let chart = new google.visualization.LineChart($('#curve_chart')[0]);
                chart.draw(tdata, options);

                chartsLoading['monetary'] = false;

                $('#dashboard-monetary .spinner-border').hide();
            }
        });
    }
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

function loadRetards() {
    let routeForLate = Routing.generate('api_retard', true);

    const $retardsTable = $('.retards-table');

    if (!datatableLoading) {
        const clientHeight = document.body.clientHeight;
        datatableLoading = true;
        if (datatableColis) {
            datatableColis.destroy();
        }
        datatableColis = $retardsTable.DataTable({
            responsive: true,
            dom: 'tipr',
            pagingType: 'simple',
            pageLength: (
                clientHeight < 800 ? 2 :
                clientHeight < 900 ? 3 :
                clientHeight < 1000 ? 4 :
                6
            ),
            processing: true,
            "language": {
                url: "/js/i18n/dataTableLanguage.json",
            },
            ajax: {
                "url": routeForLate,
                "type": "POST",
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
