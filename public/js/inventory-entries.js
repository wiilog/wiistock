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
    let missionId = $('#missionId').val();
    let params = {
        missionId: missionId,
    };
    let path = Routing.generate('get_entries_for_csv', true);

    $.post(path, JSON.stringify(params), function(response) {
        if (response) {
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
    let exportedFilename = 'export-entries' + '.csv';
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