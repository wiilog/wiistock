$('.range-buttons').hide();
google.charts.load('current', {packages: ['corechart']});
google.charts.setOnLoadCallback(function () {
    drawAnnotations('dashboard-assoc');
    drawAnnotations('dashboard-arrival');
});

function drawAnnotations(parent) {
    let data = new google.visualization.DataTable();
    let currentWeekRoute = Routing.generate(parent, true);
    let params = {
        'firstDay': $('#' + parent + ' > .range-buttons > .firstDay').data('day'),
        'lastDay': $('#' + parent + ' > .range-buttons > .lastDay').data('day'),
    };
    $.post(currentWeekRoute, JSON.stringify(params), function (chartData) {
        chartData.columns.forEach(column => {
            if (column.annotation) {
                data.addColumn({type: column.type, role: column.role});
            } else {
                data.addColumn(column.type, column.value);
            }
        });
        for (const [key, value] of Object.entries(chartData.rows)) {
            if (value.conform !== undefined) data.addRow(
                [
                    key,
                    Number(value.count),
                    value.conform,
                    key + ' : ' + String(value.conform) + '%']);
            else data.addRow([key, Number(value.count)]);
        }
        let options = {
            vAxes: {
                0: {
                    minValue: 1,
                    format: '#',
                },
                1: {
                    maxValue: 100,
                    minValue: 0,
                    format: '#',
                    gridlines: {color: 'transparent'},
                },
            },
            seriesType: 'bars',
            pointSize: 5,
            series: {
                1: {
                    pointShape: 'circle',
                    type: 'line',
                    targetAxisIndex: 1
                }
            },
            legend: {position: 'top'}
        };
        console.log(data);
        let chart = new google.visualization.ColumnChart($('#' + parent + ' > .chart')[0]);
        chart.draw(data, options);
        $('#' + parent + ' > .range-buttons').show();
        $('#' + parent + ' > .spinner-border').hide();
    }, 'json');
}

let changeCurrentWeek = function (after, parent) {
    $('#' + parent + ' > .range-buttons').hide();
    $('#' + parent + ' > .spinner-border').show();
    let data = new google.visualization.DataTable();
    let currentWeekRoute = Routing.generate(parent, true);
    let params = {
        'firstDay': $('#' + parent + ' > .range-buttons > .firstDay').data('day'),
        'lastDay': $('#' + parent + ' > .range-buttons > .lastDay').data('day'),
        'after': after
    };
    $.post(currentWeekRoute, JSON.stringify(params), function (chartData) {
        chartData.columns.forEach(column => {
            if (column.annotation) {
                data.addColumn({type: column.type, role: column.role});
            } else {
                data.addColumn(column.type, column.value);
            }
        });
        for (const [key, value] of Object.entries(chartData.rows)) {
            if (value.conform !== undefined) data.addRow(
                [
                    key,
                    Number(value.count),
                    value.conform,
                    key + ' : ' + String(value.conform) + '%']);
            else data.addRow([key, Number(value.count)]);
        }
        let options = {
            vAxes: {
                0: {
                    minValue: 1,
                    format: '#',
                },
                1: {
                    maxValue: 100,
                    minValue: 0,
                    format: '#',
                    gridlines: {color: 'transparent'},
                },
            },
            seriesType: 'bars',
            pointSize: 5,
            series: {
                1: {
                    pointShape: 'circle',
                    type: 'line',
                    targetAxisIndex: 1
                }
            },
            legend: {position: 'top'}
        };
        let chart = new google.visualization.ColumnChart($('#' + parent + ' > .chart')[0]);
        chart.draw(data, options);
        $('#' + parent + ' > .range-buttons > .firstDay').data('day', chartData.firstDay);
        $('#' + parent + ' > .range-buttons > .firstDay').text(chartData.firstDay + ' - ');
        $('#' + parent + ' > .range-buttons > .lastDay').data('day', chartData.lastDay);
        $('#' + parent + ' > .range-buttons > .lastDay').text(chartData.lastDay);
        $('#' + parent + ' > .range-buttons').show();
        $('#' + parent + ' > .spinner-border').hide();
    }, 'json');
};

let routeForLate = Routing.generate('api_retard', true);

$('.retards-table').DataTable({
    dom: 'ftipr',
    pageLength: 5,
    processing: true,
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": routeForLate,
        "type": "POST",
    },
    columns: [
        {"data": 'colis', 'name': 'colis', 'title': 'Colis'},
        {"data": 'date', 'name': 'date', 'title': 'Date de dépose'},
        {"data": 'time', 'name': 'delai', 'title': 'Délai'},
        {"data": 'emp', 'name': 'emp', 'title': 'Emplacement'},
    ]
});