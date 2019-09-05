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
        tableAlerteExpiry.column('Alerte:name').visible(false);
    },
    // 'rowCallback': function(row, data) {
    //     if (data.Alerte === true) {
    //         $(row).addClass('bg-warning');
    //     }
    // },
    order: [[1, 'asc']],
    columns: [
        { "data": 'Code', 'title': 'Code' },
        { "data": 'Référence', 'title': 'Référence' },
        { "data": 'Date péremption', 'title': 'Date péremption' },
        { "data": 'Délai alerte', 'title': 'Délai alerte' },
        { "data": 'Utilisateur', 'title': 'Créée par' },
        { "data": 'Actions', 'title': 'Actions' },
        { "data" : 'Alerte', 'name': 'Alerte', 'title': 'Alerte'}
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

function toggleActiveButton($button) {
    $button.toggleClass('active');
    $button.toggleClass('not-active');

    let value = $button.hasClass('active') ? 'true' : '';
    tableAlerteExpiry
        .columns('Alerte:name')
        .search(value)
        .draw();
}