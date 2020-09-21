$(function () {

    initFreeSelect2($('select.select2-free'));

    $('.table').each(function () {
        const $table = $(this);
        initDataTable($table.attr('id'), {
            ajax: {
                "url": Routing.generate('fields_param_api', {entityCode: $table.parent().attr('id')}),
                "type": "POST"
            },
            columns: [
                {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
                {"data": 'displayed', 'title': 'Affiché'},
                {"data": 'mustCreate', 'title': 'Obligatoire à la création'},
                {"data": 'mustEdit', 'title': 'Obligatoire à la modification'},
                {"data": 'fieldCode', 'title': 'Champ fixe'},
            ],
            rowConfig: {
                needsRowClickAction: true,
            },
            order: [[4, "asc"]],
            info: false,
            filter: false,
            paging: false
        });
    });

    let $modalEditFields = $('#modalEditFields');
    let $submitEditFields = $('#submitEditFields');
    let urlEditFields = Routing.generate('fields_edit', true);
    InitModal($modalEditFields, $submitEditFields, urlEditFields, {success: displayErrorFields});
});

function displayErrorFields() {
    $('.table').each(function () {
        let table = $(this).DataTable();
        table.ajax.reload();
    });
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

function editBusinessUnit($select, paramName) {
    const val = $select.val() || [];
    const valStr = JSON.stringify(val);
    $.post(Routing.generate('toggle_params'), JSON.stringify({param: paramName, val: valStr})).then((resp) => {
        if (resp) {
            alertSuccessMsg("La liste business unit a bien été mise à jour.");
        } else {
            alertErrorMsg("Une erreur est survenue lors de la mise à jour de la liste business unit.");
        }
    })
}
