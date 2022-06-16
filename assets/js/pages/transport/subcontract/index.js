import '@styles/pages/transport/common.scss';
import '@styles/pages/transport/subcontract.scss';
import {$document} from "@app/app";
import {GET, POST} from "@app/ajax";
import {initializeFilters} from "@app/pages/transport/common";

global.editStatusChange = editStatusChange;

const subcontractStatus = "Sous-traitée";
const onGoingStatus = "En cours";
const finishedStatus = "Terminée";
const notDeliveredStatus = "Non livrée";

$(function () {

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_SUBCONTRACT_ORDERS);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');

    initializeFilters(PAGE_SUBCONTRACT_ORDERS);

    let table = initDataTable('tableSubcontractOrders', {
        processing: true,
        serverSide: true,
        ordering: false,
        searching: false,
        pageLength: 24,
        lengthMenu: [24, 48, 72, 96],
        ajax: {
            url: Routing.generate(`transport_subcontract_api`),
            type: "POST",
            data: data => {
                data.dateMin = $(`.filters [name="dateMin"]`).val();
                data.dateMax = $(`.filters [name="dateMax"]`).val();
            },
        },
        domConfig: {
            removeInfo: true
        },
        //remove empty div with mb-2 that leaves a blank space
        drawCallback: () => $(`.row.mb-2 .col-auto.d-none`).parent().remove(),
        rowConfig: {
            needsRowClickAction: true
        },
        columns: [
            {data: 'content', name: 'content', orderable: false},
        ],
    });

    $document.arrive('.accept-request, .subcontract-request', function () {
        $(this).on('click', function () {
            const requestId = $(this).siblings('[name=requestId]').val();
            const buttonType = $(this).data('type') ;
            wrapLoadingOnActionButton($(this), () =>
                AJAX.route(POST, 'transport_request_treat', {requestId, buttonType}).json().then(() => table.ajax.reload())
            )
        });
    });

    $document.arrive('.modal-edit-subcontracted-request', function (){
        const requestStatusCode = $('select[name=status]').find(":selected").text();
        manageModal(requestStatusCode);
    });

    let modalModifySubcontractedRequest = $('#modalEditSubcontractedRequest');
    let submitModifySubcontractedRequest = $('#submitEditSubcontractedRequest');
    let urlModifySubcontractedRequest = Routing.generate('subcontract_request_edit', true);
    InitModal(modalModifySubcontractedRequest, submitModifySubcontractedRequest, urlModifySubcontractedRequest, {tables: [table], error : (data) => {
        if( data.success === false ) {
            for (const [attr , error] of Object.entries(data.errors)) {
                const $input = $(`[name=${attr}]`)
                $input.addClass('is-invalid');
                if ( ! $input.parent().find('.invalid-feedback').text() ) {
                    $input.parent().append(`<span class="invalid-feedback">${error}</span>`)
                }
            }
        }
    }});
});

function editStatusChange($select){
    manageModal($select.find(":selected").text());
}

function manageModal(requestStatusCode){
    const startDateDiv = $('.startDateDiv');
    const inputStartDate = startDateDiv.find('[name=delivery-start-date]');
    const endDateDiv = $('.endDateDiv');
    const inputEndDate = endDateDiv.find('[name=delivery-end-date]');
    const comment = $('[name=commentaire]');
    const labelComment = $('.label-'+comment.attr('name'));
    switch (requestStatusCode){
        case subcontractStatus:
            toggleSubcontractStatus(startDateDiv, inputStartDate, endDateDiv, inputEndDate, comment, labelComment);
            break;
        case onGoingStatus:
            toggleOnGoingStatus(startDateDiv, inputStartDate, endDateDiv, inputEndDate, comment, labelComment)
            break;
        case finishedStatus:
            toggleFinishedStatus(startDateDiv, inputStartDate, endDateDiv, inputEndDate, comment, labelComment)
            break;
        case notDeliveredStatus:
            toggleNotDeliveredStatus(startDateDiv, inputStartDate, endDateDiv, inputEndDate, comment, labelComment)
            break;
    }
}

function toggleSubcontractStatus(startDateDiv, inputStartDate, endDateDiv, inputEndDate, comment, labelComment){
    startDateDiv.addClass('d-none');
    inputStartDate.removeClass('needed');
    endDateDiv.addClass('d-none');
    inputEndDate.removeClass('needed');
    labelComment.html(labelComment.text().replace(" *", ""));
    comment.removeClass('needed');
}

function toggleOnGoingStatus(startDateDiv, inputStartDate, endDateDiv, inputEndDate, comment, labelComment){
    startDateDiv.removeClass('d-none');
    inputStartDate.addClass('needed');
    endDateDiv.addClass('d-none');
    inputEndDate.removeClass('needed');
    labelComment.html(labelComment.text().replace(" *", ""));
    comment.removeClass('needed');
}

function toggleFinishedStatus(startDateDiv, inputStartDate, endDateDiv, inputEndDate, comment, labelComment){
    startDateDiv.removeClass('d-none');
    inputStartDate.addClass('needed');
    endDateDiv.removeClass('d-none');
    inputEndDate.addClass('needed');
    labelComment.html(labelComment.text().replace(" *", ""));
    comment.removeClass('needed');
}

function toggleNotDeliveredStatus(startDateDiv, inputStartDate, endDateDiv, inputEndDate, comment, labelComment){
    startDateDiv.removeClass('d-none');
    inputStartDate.addClass('needed');
    endDateDiv.removeClass('d-none');
    inputEndDate.addClass('needed');
    labelComment.html(labelComment.text().concat(" *"));
    comment.addClass('needed');
}
