$(function () {
    Select2Old.initFree($('select.select2-free'));

    $('.table').each(function () {
        const $table = $(this);
        initDataTable($table.attr('id'), {
            ajax: {
                "url": Routing.generate('fields_param_api', {entityCode: $table.parent().attr('id')}),
                "type": "POST"
            },
            columns: [
                {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
                {"data": 'fieldCode', 'title': 'Champ fixe'},
                {"data": 'mustCreate', 'title': 'Obligatoire à la création'},
                {"data": 'mustEdit', 'title': 'Obligatoire à la modification'},
                {"data": 'displayedCreate', 'title': 'Affiché sur formulaires de création'},
                {"data": 'displayedEdit', 'title': 'Affiché sur formulaires d\'édition'},
                {"data": 'displayedFilters', 'title': 'Affiché sur les filtres'}
            ],
            rowConfig: {
                needsRowClickAction: true,
            },
            order: [['fieldCode', "asc"]],
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

function switchDisplay($checkbox) {
    if ($checkbox.attr('name') === 'displayedCreate'
        && !$checkbox.prop('checked')) {
        $('.checkbox[name="requiredCreate"]').prop('checked', false);
    } else if ($checkbox.attr('name') === 'displayedEdit'
        && !$checkbox.prop('checked')) {
        $('.checkbox[name="requiredEdit"]').prop('checked', false);
    }
}

function switchDisplayByMust($checkbox) {
    if ($checkbox.attr('name') === 'requiredCreate'
        && $checkbox.prop('checked')) {
        $('.checkbox[name="displayedCreate"]').prop('checked', true);
    } else if ($checkbox.attr('name') === 'requiredEdit'
        && $checkbox.prop('checked')) {
        $('.checkbox[name="displayedEdit"]').prop('checked', true);
    }
}
