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
            $("#body").append(
                "<label for=\"date-attendu\" id=\"ajouterElem\">Ajouter un élément</label>" +
                "<span class=\"input-group-btn\">" +
                "<button class=\"btn\" onclick=\"appendElem()\" type=\"button\">+</button>" +
                "</span>");
        } else {
            $("#list").hide();
            $("#noList").show();
            $("div").remove(".elem");
            $("#ajouterElem").remove();
        }
    });

});

function appendElem() {
    $("#body").append(
        "<div class=\"form-group elem\">" +
        "<input type=\"text\" class=\"form-control data\" name=\"elem\" onblur=\"addToSelect(this)\">" +
        "</div>");
}

function addToSelect(el) {
    let input = $(el).val();
    if (input !== "") {
        $("#valeur").append(new Option(input, input));
    }
}