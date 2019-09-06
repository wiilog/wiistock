$('.select2').select2();

let pathAlerteExpiry = Routing.generate('alerte_expiry_api', true);
let tableAlerteExpiry = $('#tableAlerteExpiry_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url":pathAlerteExpiry,
        "type": "POST"
    },
    'drawCallback': function () {
        tableAlerteExpiry.column('Active:name').visible(false);
    },
    initComplete: function() {
        // applique les filtres si pré-remplis
        let filterActive = $('#filter-active').hasClass('active');
        if (filterActive) {
            tableAlerteExpiry
                .columns('Alerte:name')
                .search('true')
                .draw();
        }
    },
    order: [[0, 'asc']],
    columns: [
        { "data": 'Référence', 'title': 'Référence' },
        { "data": 'Date péremption', 'title': 'Date péremption' },
        { "data": 'Délai alerte', 'title': 'Délai alerte' },
        { "data": 'Utilisateur', 'title': 'Créée par' },
        { "data": 'Active', 'name': 'Active', 'title': 'Active'},
        { "data": 'Actions', 'title': 'Actions' },
    ],
});


let modalNewAlerteExpiry = $("#modalNewAlerteExpiry");
let submitNewAlerteExpiry = $("#submitNewAlerteExpiry");
let urlNewAlerteExpiry = Routing.generate('alerte_expiry_new', true);
InitialiserModal(modalNewAlerteExpiry, submitNewAlerteExpiry, urlNewAlerteExpiry, tableAlerteExpiry);

let ModalDeleteAlerteExpiry = $("#modalDeleteAlerteExpiry");
let SubmitDeleteAlerteExpiry = $("#submitDeleteAlerteExpiry");
let urlDeleteAlerteExpiry = Routing.generate('alerte_expiry_delete', true)
InitialiserModal(ModalDeleteAlerteExpiry, SubmitDeleteAlerteExpiry, urlDeleteAlerteExpiry, tableAlerteExpiry);

let modalModifyAlerteExpiry = $('#modalEditAlerteExpiry');
let submitModifyAlerteExpiry = $('#submitEditAlerteExpiry');
let urlModifyAlerteExpiry = Routing.generate('alerte_expiry_edit', true);
InitialiserModal(modalModifyAlerteExpiry, submitModifyAlerteExpiry, urlModifyAlerteExpiry, tableAlerteExpiry);

function toggleNeededRef($checkbox)
{
    let $reference = $checkbox.closest('.modal-content').find("[name='reference']");

    if ($checkbox.is(':checked')) {
        $reference.removeClass('needed');
        $reference.val(null).trigger('change');
        $reference.removeClass('is-invalid');
        $reference.next().find('.select2-selection').removeClass('is-invalid');
    } else {
        $reference.addClass('needed');
    }
}

function checkboxOff($elem)
{
    let $checkbox = $elem.closest('.modal-content').find("[name='allRef']");

    if ($elem.val() !== '' && $elem.val() !== null) {
        $checkbox.prop('checked', false);
    }

    $elem.next().find('.select2-selection').removeClass('is-invalid');
}