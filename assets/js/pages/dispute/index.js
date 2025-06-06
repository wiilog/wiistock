import {initCommentHistoryForm, initTableArticleLitige} from "@app/pages/dispute/common";

let tableLitiges;
let modalEditLitige = $('#modalEditLitige');
let submitEditLitige = $('#submitEditLitige');
let urlEditLitige = Routing.generate('dispute_edit', true);
let ModalDeleteLitige = $("#modalDeleteLitige");
let SubmitDeleteLitige = $("#submitDeleteLitige");
let urlDeleteLitige = Routing.generate('dispute_delete', true);

let tableHistoLitige;
let tableArticleLitige;

global.editRowLitige = editRowLitige;
global.openTableHisto = openTableHisto;

$(function () {
    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';
    initDateTimePicker('#dateMin, #dateMax', DATE_FORMATS_TO_DISPLAY[format]);

    const $filtersContainer = $(`.filters-container`);
    const $disputeTypeFilter = $filtersContainer.find(`select[name=multipleTypes]`);

    Select2Old.init($('#carriers'), 'Transporteurs');
    Select2Old.init($('#litigeOrigin'), Translation.of(`Qualité`, `Litiges`, `Origines`, false));
    Select2Old.user($('.ajax-autocomplete-user:eq(0)'), Translation.of(`Qualité`, `Litiges`, `Acheteurs`, false));
    Select2Old.user($('.ajax-autocomplete-user:eq(1)'), Translation.of(`Qualité`, `Litiges`, `Déclarant`, false));
    Select2Old.dispute($('.ajax-autocomplete-dispute'), Translation.of(`Qualité`, `Litiges`, `Numéro de litige`, false));
    Select2Old.init($disputeTypeFilter, Translation.of(`Qualité`, `Litiges`, `Types`, false));


    const fromDashboard = $('.filters-container [name="fromDashboard"]').val() === '1';
    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_LITIGE_ARR);
    $.post(path, params, function (data) {
        if (!fromDashboard) {
            displayFiltersSup(data, true);
        }
    }, 'json');

    initDatatableLitiges();

    InitModal(modalEditLitige, submitEditLitige, urlEditLitige, {tables: [tableLitiges]});
    InitModal(ModalDeleteLitige, SubmitDeleteLitige, urlDeleteLitige, {tables: [tableLitiges]});
});

function initDatatableLitiges() {
    const $filtersContainer = $(".filters-container");
    const fromDashboard = $filtersContainer.find('[name="fromDashboard"]').val();
    const $statutFilter = $filtersContainer.find(`select[name=statut]`);
    const $typeFilter = $filtersContainer.find(`select[name=multipleTypes]`);
    const $disputeEmergency = $filtersContainer.find(`input[name=emergency]:checked`);

    let pathLitiges = Routing.generate('dispute_api', {
        fromDashboard,
        preFilledTypes: $typeFilter.val(),
        preFilledStatuses: $statutFilter.val(),
        disputeEmergency: $disputeEmergency.length > 0,
    }, true);

    let tableLitigesConfig = {
        serverSide: true,
        processing: true,
        order: [['creationDate', 'desc']],
        ajax: {
            "url": pathLitiges,
            "type": "POST",
        },
        drawConfig: {
            needsColumnShow: true
        },
        domConfig: {
            needsFullDomOverride: true
        },
        buttons: [
            {
                extend: 'colvis',
                columns: ':not(.noVis)',
                className: 'dt-btn d-none'
            },
        ],
        rowConfig: {
            needsColor: true,
            color: 'danger',
            needsRowClickAction: true,
            dataToCheck: 'urgence'
        },
        page: 'dispute'
    };
    tableLitiges = initDataTable('tableLitiges', tableLitigesConfig);
}

function editRowLitige(button, afterLoadingEditModal = () => {}, isArrivage, arrivageOrReceptionId, disputeId, disputeNumber) {
    let route = isArrivage ? 'arrival_dispute_api_edit' : 'litige_api_edit_reception';
    let path = Routing.generate(route, {dispute: disputeId});
    let $modal = $('#modalEditLitige');
    let $submit = $modal.find('#submitEditLitige');
    let params = {};
    if (isArrivage) {
        params.arrivageId = arrivageOrReceptionId;
    } else {
        params.reception = arrivageOrReceptionId;
    }

    $.get(path, JSON.stringify(params), function (data) {
        $modal.find('.error-msg').html('');
        $modal.find('.modal-body').html(data.html);
        if (!isArrivage) {
            Select2Old.articleReception($modal.find('.select2-autocomplete-articles'), arrivageOrReceptionId);
            let values = [];
            data.packs.forEach(val => {
                values.push({
                    id: val.id,
                    text: val.text
                })
            });
            values.forEach(value => {
                $('#packEditLitige').select2("trigger", "select", {
                    data: value
                });
            });

            $modal.find('#acheteursLitigeEdit').val(data.acheteurs).select2();
            fillDemandeurField($modal);
        }

        Camera.init(
            $modal.find(`.take-picture-modal-button`),
            $modal.find(`[name="files[]"]`)
        );

        $modal.append('<input hidden class="data" name="isArrivage" value="' + isArrivage + '">');
        afterLoadingEditModal();

    }, 'json');

    $modal.find($submit).attr('value', disputeId);
    $('#disputeNumber').text(disputeNumber);
}

function openTableHisto() {
    let pathHistoLitige = Routing.generate('dispute_histo_api', {dispute: $('[name="disputeId"]').val()}, true);
    let tableHistoLitigeConfig = {
        ajax: {
            "url": pathHistoLitige,
            "type": AJAX.POST
        },
        serverSide: true,
        columns: [
            {data: 'user', name: 'Utilisateur', title: Translation.of('Traçabilité', 'Général', 'Utilisateur')},
            {data: 'date', name: 'date', title: Translation.of('Traçabilité', 'Général', 'Date')},
            {data: 'comment', name: 'commentaire', title: Translation.of('Général', '', 'Modale', 'Commentaire')},
            {data: 'statusLabel', name: 'status', title: Translation.of('Qualité', 'Litiges', 'Statut')},
            {data: 'typeLabel', name: 'type', title: Translation.of('Qualité', 'Litiges', 'Type')},
        ],
        domConfig: {
            needsPartialDomOverride: true,
        }
    };
    tableHistoLitige = initDataTable('tableHistoLitige', tableHistoLitigeConfig);
    tableArticleLitige = initTableArticleLitige();

    initCommentHistoryForm(modalEditLitige, tableHistoLitige);
}
