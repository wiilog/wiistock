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
            var arrSales = [['Month', 'Fiabilité monétaire']];

            $.each(data, function (index, value) {
                arrSales.push([value.mois, value.nbr]);
            });

            var options = {
                title: 'Fiabilité Monétaire de ces 6 dernier mois',
                curveType: 'function',
                legend: { position: 'bottom', textStyle: { color: '#555', fontSize: 14} }
            };

            var figures = google.visualization.arrayToDataTable(arrSales)

            var chart = new google.visualization.LineChart(document.getElementById('curve_chart'));
            chart.draw(figures, options);      // Draw the chart with Options.
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            alert('Got an Error');
        }
    });
}