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

google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(drawChart);

function drawChart() {
    let path = Routing.generate('graph_monetaire', true);
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

            let chart = new google.visualization.LineChart(document.getElementById('curve_chart'));
            chart.draw(tdata, options);
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            // alert('Got an Error');
        }
    });
}

google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(drawChart_reference);

function drawChart_reference() {
    let path = Routing.generate('graph_ref', true);
    $.ajax({
        url: path,
        dataType: "json",
        type: "GET",
        contentType: "application/json; charset=utf-8",
        success: function (data) {
            let tdata = new google.visualization.DataTable();

            tdata.addColumn('string', 'Month');
            tdata.addColumn('number', 'Fiabilité réference en %');

            $.each(data, function (index, value) {
                tdata.addRow([value.mois, value.nbr]);
            });

            let options = {
                curveType: 'function',
                backdropColor: 'transparent',
                legend: 'none',
                backgroundColor: 'transparent',
                axisFontSize: 0,
                hAxis: {
                    gridlines: {
                        count: 0,
                        color: 'transparent'
                    },
                    scaleType: 'log',
                    minValue: 0,
                    baselineColor: 'transparent'
                },
                vAxis: {
                    gridlines: {
                        color: 'transparent'
                    },
                    scaleType: 'log',
                    minValue: 0,
                    baselineColor: 'transparent'
                }
            };

            let chart = new google.visualization.LineChart(document.getElementById('curve_chart_reference'));
            chart.draw(tdata, options);
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            // alert('Got an Error');
        }
    });
}
