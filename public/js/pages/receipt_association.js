$('.select2').select2();

$(function () {
    $(document).on('hide.bs.modal', function () {
        $('.arrivalNb').not(':first').remove();
    });
    initDateTimePicker();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_RCPT_TRACA);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');

    Select2.user('Utilisateurs');
});

let pathRecep = Routing.generate('reception_traca_api', true);
let tableRecepConfig = {
    serverSide: true,
    "processing": true,
    "order": [[1, "desc"]],
    drawConfig: {
        needsSearchOverride: true,
    },
    rowConfig: {
        needsRowClickAction: true
    },
    headerCallback: function (thead) {
        $(thead).find('th').eq(2).attr('title', "arrivage");
        $(thead).find('th').eq(3).attr('title', "réception");
    },
    buttons: [
        {
            extend: 'csv',
            fieldSeparator: ';',
            exportOptions: {
                columns: [1, 2, 3, 4]
            }
        }
    ],
    ajax: {
        "url": pathRecep,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', 'name': 'Actions', 'title': '', className: ['noVis'], orderable: false},
        {"data": 'date', 'name': 'date', 'title': 'Date'},
        {"data": "Arrivage", 'name': 'Arrivage', 'title': 'arrivage.arrivage', translated: true},
        {"data": 'Réception', 'name': 'Réception', 'title': 'réception.réception', translated: true},
        {"data": 'Utilisateur', 'name': 'Utilisateur', 'title': 'Utilisateur'},
    ],
};
let tableRecep = initDataTable('tableRecepts', tableRecepConfig);

let modalDeleteReception = $('#modalDeleteRecepTraca');
let submitDeleteReception = $('#submitDeleteRecepTraca');
let urlDeleteArrivage = Routing.generate('reception_traca_delete', true);
InitModal(modalDeleteReception, submitDeleteReception, urlDeleteArrivage, {tables: [tableRecep]});

let modalNewReceiptAssociation = $('#modalNewAssociation');
let submitNewReceiptAssociation = $('#submitNewAssociation');
let urlNewReceiptAssociation = Routing.generate('reception_traca_new', true);
InitModal(modalNewReceiptAssociation, submitNewReceiptAssociation, urlNewReceiptAssociation, {tables: [tableRecep], clearOnClose: true});

let customExport = function () {
    tableRecep.button('.buttons-csv').trigger();
};

function toggleArrivage(button) {
    let $arrivageBlock = $('.arrivalNb').first().parent();
    const $arrivalNumber = $arrivageBlock.find('[name="numero_arrivage"]');
    if (button.data('arrivage')) {
        $arrivageBlock.find('input').each(function () {
            if ($(this).hasClass('arrivage-input')) {
                $(this).remove();
            } else {
                $(this).val('');
                $(this).removeClass('needed');
            }
        });
        $arrivageBlock.hide();
        button.text('Avec Arrivage');

        $arrivalNumber.removeClass('data-array');
    } else {
        $arrivageBlock.find('input').each(function () {
            $(this).addClass('needed');
        });
        $arrivageBlock.show();
        button.text('Sans Arrivage');
        $arrivalNumber.addClass('data-array');
    }
    button.data('arrivage', !button.data('arrivage'));
}

function addArrivalAssociation(span) {
    let $arrivalInput = span.parent().find('.arrivalNb').first();
    let $parent = $arrivalInput.parent();
    $arrivalInput.clone().appendTo($parent).find('input[type=text]').val("");
}
