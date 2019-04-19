$('.select2').select2();

//TYPE

const urlApiType = Routing.generate('type_api', true);
let tableType = $('#tableType_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": urlApiType,
        "type": "POST"
    },
    columns: [
        { "data": 'Label' },
        { "data": 'Catégorie' },
        { "data": 'Actions' },
    ],
});

let dataModalTypeNew = $("#modalNewType");
let ButtonSubmitTypeNew = $("#submitTypeNew");
let urlTypeNew = Routing.generate('type_new', true);
InitialiserModal(dataModalTypeNew, ButtonSubmitTypeNew, urlTypeNew, tableType);

let dataModalTypeDelete = $("#modalDeleteType");
let ButtonSubmitTypeDelete = $("#submitDeleteType");
let urlTypeDelete = Routing.generate('type_delete', true);
InitialiserModal(dataModalTypeDelete, ButtonSubmitTypeDelete, urlTypeDelete, tableType, askForDeleteConfirmation, false);

let dataModalEditType = $("#modalEditType");
let ButtonSubmitEditType = $("#submitEditType");
let urlEditType = Routing.generate('type_edit', true);
InitialiserModal(dataModalEditType, ButtonSubmitEditType, urlEditType, tableType);


//CHAMPS LIBRE

const urlApiChampsLibre = Routing.generate('champ_libre_api', { 'id': id }, true);
let tableChampsLibre = $('#tableChampslibre_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": urlApiChampsLibre,
        "type": "POST"
    },
    columns: [
        { "data": 'Label' },
        { "data": 'Typage' },
        { "data": 'Valeur par défaut' },
        { "data": 'Elements' },
        { "data": 'Obligatoire à la création' },
        { "data": 'Obligatoire à la modification' },
        { "data": 'Actions' },
    ],
});

let dataModalChampsLibreNew = $("#modalNewChampsLibre");
let ButtonSubmitChampsLibreNew = $("#submitChampsLibreNew");
let urlChampsLibreNew = Routing.generate('champ_libre_new', true);
InitialiserModal(dataModalChampsLibreNew, ButtonSubmitChampsLibreNew, urlChampsLibreNew, tableChampsLibre);

let dataModalChampsLibreDelete = $("#modalDeleteChampsLibre");
let ButtonSubmitChampsLibreDelete = $("#submitChampsLibreDelete");
let urlChampsLibreDelete = Routing.generate('champ_libre_delete', true);
InitialiserModal(dataModalChampsLibreDelete, ButtonSubmitChampsLibreDelete, urlChampsLibreDelete, tableChampsLibre);

let dataModalEditChampsLibre = $("#modalEditChampLibre");
let ButtonSubmitEditChampsLibre = $("#submitEditChampsLibre");
let urlEditChampsLibre = Routing.generate('champ_libre_edit', true);
InitialiserModal(dataModalEditChampsLibre, ButtonSubmitEditChampsLibre, urlEditChampsLibre, tableChampsLibre);

function askForDeleteConfirmation(data) {
    let modal = $('#modalDeleteType');

    if (data !== true) {
        modal.find('.modal-body').html(data);
        let submit = $('#submitDeleteType');

        let typeId = submit.val();
        let params = JSON.stringify({ force: true, type: typeId });

        submit.on('click', function () {
            $.post(Routing.generate('type_delete'), params, function () {
                tableChampsLibre.ajax.reload();
            }, 'json');
        });
    } else {
        modal.find('.close').click();
    }
}

$(document).ready(function () {
    $('#typage').change(function () {
        if ($(this).val() === 'list') {
            $("#list").show();
            $("#noList").hide();
        } else {
            $("#list").hide();
            $("#noList").show();
            $("div").remove(".elem");
            $("#ajouterElem").remove();
        }
    });
});

function changeType(select) {
    if ($(select).val() === 'list') {
        $('#defaultValue').hide();
        $('#isList').show();
    } else {
        $('#isList').hide();
        $('#defaultValue').show();
    }
}
