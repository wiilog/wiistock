let encoding;
let textEncoder;
    function initExport(button, type) {
    if (!button.attr('data-clicked') || button.attr('data-clicked') === "false") {
        button.attr('data-clicked', true);
        button.css('pointer-events', 'none');
        button.removeClass('btn-primary');
        button.addClass('btn-light');
        button.css('background', 'linear-gradient(to right, #00b31e 1%, grey 1%');
        button.html('Export CSV en cours ...');
        let path = '';
        switch (type) {
            case 'ref':
                path = 'get_total_and_headers_ref';
                break;
            case 'art':
                path = 'get_total_and_headers_art';
                break;
            default:
                return;
        }
        $.post(Routing.generate(path, true), function (response) {
            exportAll(type, response.total, response.headers.join(';'), button);
        });
    }
}

async function exportAll(type, total, headers, button) {
    let increment = 50;
    let csv = textEncoder.encode(headers) + '\n';
    for (let i = 0; i < total; i += increment) {
        let path = '';
        switch (type) {
            case 'ref':
                path = 'reference_article_export';
                break;
            case 'art':
                path = 'article_export';
                break;
            default:
                return;
        }

        let route = Routing.generate(path, {
            max: i + increment,
            min: i
        });
        let result = await exportWithBounds(route);
        let percent = i + increment > total ? 100 : Math.floor(((i + increment) / total) * 100);
        button.css('background', 'linear-gradient(to right, #00b31e ' + percent + '%, grey ' + percent + '%');
        button.html('Export CSV en cours... ' + percent + '%');
        $.each(result, function (index, value) {
            csv += textEncoder.encode(value);
            csv += '\n';
        });
    }
    button.removeClass('btn-light');
    button.css('background', '');
    button.addClass('btn-primary');
    button.css('pointer-events', '');
    button.attr('data-clicked', false);
    button.html('<i class="fa fa-print mr-2"></i>Exporter au format CSV')
    dlFileForArts(csv, type);
}

function exportWithBounds(path) {
    return new Promise(function (resolve) {
        $.post(path, function (data) {
            resolve(data.values);
        });
    });
}

$(function () {
    $.post(Routing.generate('get_encodage'), function (usesUTF8) {
        encoding = usesUTF8 ? 'utf-8' : 'windows-1252';
        textEncoder = new CustomTextEncoder(encoding, {NONSTANDARD_allowLegacyEncoding: true});
    });
});

let dlFileForArts = function (csv, type) {
    let d = new Date();
    let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
    date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
    let exportedFilenmae = type === "ref" ? 'export-referencesCEA-' + date + '.csv'
        : type === "art" ? 'export-articles-' + date + '.csv'
            : 'export-others-' + data + '.csv';
    let blob = new Blob([csv], {type: 'text/csv;charset=' + encoding + ';'});
    saveAs(blob, exportedFilenmae);
}
