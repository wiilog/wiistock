$(function () {
    $('.table').each(function () {
        $(this).DataTable({
            "language": {
                url: "/js/i18n/dataTableLanguage.json",
            },
            ajax: {
                "url": Routing.generate('fields_param_api', {entityCode: $(this).parent().attr('id')}),
                "type": "POST"
            },
            columns: [
                {"data": 'Actions', 'title': '', className: 'noVis'},
                {"data": 'displayed', 'title': 'Affiché'},
                {"data": 'mustCreate', 'title': 'Obligatoire à la création'},
                {"data": 'mustEdit', 'title': 'Obligatoire à la modification'},
                {"data": 'fieldCode', 'title': 'Champ fixe'},
            ],
            rowCallback: function (row, data) {
                initActionOnRow(row);
            },
            order: [[4, "asc"]],
            info: false,
            filter: false,
            paging: false,
            columnDefs: [
                {orderable: false, targets: 0}
            ]
        });
    });
});

let modalEditFields = $('#modalEditFields');
let submitEditFields = $('#submitEditFields');
let urlEditFields = Routing.generate('fields_edit', true);
InitialiserModal(modalEditFields, submitEditFields, urlEditFields, null, displayErrorFields);

function displayErrorFields(data) {
    let modal = $("#modalEditFields");
    if (data.success === false) {
        displayError(modal, data.msg, data.success);
    } else {
        modal.find('.close').click();
        $('.table').each(function () {
            let table = $(this).DataTable();
            table.ajax.reload();
        });
        alertSuccessMsg(data.msg);
    }
}

function switchDisplay(checkbox) {
    if (!checkbox.is(':checked')) {
        $('.checkbox').prop('checked', false);
    }
}

function switchDisplayByMust(checkbox) {
    if (checkbox.is(':checked')) {
        $('.checkbox[name="displayed"]').prop('checked', true);
    }
}
