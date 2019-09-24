let mission = $('#missionId').val();
let pathEntry = Routing.generate('entries_api', { id: mission}, true);
let tableEntries = $('#tableMissionEntries').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathEntry,
        "type": "POST"
    },
    columns:[
        { "data": 'Article', 'title' : 'Reférence ou  Article' },
        { "data": 'Operator', 'title' : 'Operator' },
        { "data": 'Location', 'title' : 'Emplacement' },
        { "data": 'Date', 'title' : 'Date de saisie' },
        { "data": 'Quantity', 'title' : 'Quantité' }
    ],
});

function generateCSVEntries () {
    let params = JSON.stringify(data);
    let path = Routing.generate('get_mouvements_stock_for_csv', true);

    $.post(path, params, function(response) {
        if (response) {
            $('.error-msg').empty();
            let csv = "";
            $.each(response, function (index, value) {
                csv += value.join(';');
                csv += '\n';
            });
            mFile(csv);
        }
    }, 'json');
}

let mFile = function (csv) {
    let d = new Date();
    let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
    date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
    let exportedFilename = 'export-mouvements-stock-' + date + '.csv';
    let blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, exportedFilename);
    } else {
        let link = document.createElement("a");
        if (link.download !== undefined) {
            let url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", exportedFilename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
}