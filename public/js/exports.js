function initExport(button) {
    if (!button.attr('data-clicked') || button.attr('data-clicked') === false) {
        button.attr('data-clicked', true);
        button.css('pointer-events', 'none');
        button.removeClass('btn-primary');
        button.addClass('btn-light');
        button.css('background', 'linear-gradient(to right, #00b31e 1%, grey 1%');
        button.html('Export CSV des articles en cours d\'initialisation...');
        $.post(Routing.generate('get_total_and_headers'), true, function (response) {
            exportAll(response.total, response.headers.join(';'), button);
        });
    }
}

async function exportAll(total, headers, button) {
    let increment = 100;
    let csv = headers + '\n';
    for (i = 0; i < total; i += increment) {
        console.log('retrieving articles from ' + i + ' to ' + (i + increment));
        var result = await exportWithBounds(i + increment, i);
        console.log('retrieved articles from ' + i + ' to ' + (i + increment));
        let percent = i+increment > total ? 100 : Math.floor(((i+increment)/total)*100);
        button.css('background', 'linear-gradient(to right, #00b31e ' + percent + '%, grey ' + percent + '%');
        button.html('Export CSV des articles en cours... ' + percent + '%');
        $.each(result, function (index, value) {
            csv += value;
            csv += '\n';
        });
    }
    button.removeClass('btn-light'); 
    button.css('background', '');
    button.addClass('btn-primary');
    button.css('pointer-events', '');
    button.attr('data-clicked', false);
    button.html('<i class="fa fa-print mr-2"></i>Exporter au format CSV')
    dlFile(csv);
}

function exportWithBounds(max, min) {
    return new Promise(function (resolve, reject) {
        xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                let response = JSON.parse(this.responseText);
                if (response) {
                    resolve(response.values);
                } else {
                    //TODO gérer erreur
                }
            }
        }
        let path = Routing.generate('reference_article_export', {
            max: max,
            min: min
        });
        xhttp.open("POST", path);
        xhttp.send();
    });
}

let dlFile = function (csv) {
    let d = new Date();
    let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
    date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
    var exportedFilenmae = 'export-articles-' + date + '.csv';
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, exportedFilenmae);
    } else {
        var link = document.createElement("a");
        if (link.download !== undefined) {
            var url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", exportedFilenmae);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
}

function checkZero(data) {
    if (data.length == 1) {
        data = "0" + data;
    }
    return data;
}