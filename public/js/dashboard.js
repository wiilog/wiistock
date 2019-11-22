$('#range-buttons-assoc').hide();
google.charts.load('current', {packages: ['corechart', 'bar']});
google.charts.setOnLoadCallback(drawAnnotations);

function drawAnnotations() {
    let data = new google.visualization.DataTable();
    let currentWeekRoute = Routing.generate('dashboard_assoc', true);
    let params = {
        'firstDay': $('#dashboard-assoc > #range-buttons-assoc > .firstDay').data('day'),
        'lastDay': $('#dashboard-assoc > #range-buttons-assoc > .lastDay').data('day'),
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
            data.addRow([key, Number(value), String(value)]);
        }
        let options = {
            annotations: {
                alwaysOutside: true,
                textStyle: {
                    fontSize: 14,
                    color: '#000',
                    auraColor: 'none'
                }
            },
            vAxis: {
                minValue: 1,
                format: '#'
            }
        };
        let chart = new google.visualization.ColumnChart(document.getElementById('chart-assoc'));
        chart.draw(data, options);
        $('#range-buttons-assoc').show();
        $('.spinner-border').hide();
    }, 'json');
}

let changeCurrentWeek = function (after) {
    $('#range-buttons-assoc').hide();
    $('.spinner-border').show();
    let data = new google.visualization.DataTable();
    let currentWeekRoute = Routing.generate('dashboard_assoc', true);
    let params = {
        'firstDay': $('#dashboard-assoc > #range-buttons-assoc > .firstDay').data('day'),
        'lastDay': $('#dashboard-assoc > #range-buttons-assoc > .lastDay').data('day'),
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
            data.addRow([key, Number(value), String(value)]);
        }
        let options = {
            title: 'Réceptions (Nombre d\'associations)',
            annotations: {
                alwaysOutside: true,
                textStyle: {
                    fontSize: 14,
                    color: '#000',
                    auraColor: 'none'
                }
            },
            vAxis: {
                minValue: 1
            }
        };
        let chart = new google.visualization.ColumnChart(document.getElementById('chart-assoc'));
        chart.draw(data, options);
        $('#dashboard-assoc > #range-buttons-assoc > .firstDay').data('day', chartData.firstDay);
        $('#dashboard-assoc > #range-buttons-assoc > .firstDay').text(chartData.firstDay + ' - ');
        $('#dashboard-assoc > #range-buttons-assoc > .lastDay').data('day', chartData.lastDay);
        $('#dashboard-assoc > #range-buttons-assoc > .lastDay').text(chartData.lastDay);
        $('.spinner-border').hide();
        $('#range-buttons-assoc').show();
    }, 'json');
};