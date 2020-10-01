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
                {"data": 'fieldCode', 'title': 'Champ fixe'},
                {"data": 'mustCreate', 'title': 'Obligatoire à la création'},
                {"data": 'mustEdit', 'title': 'Obligatoire à la modification'},
                {"data": 'displayedFormsCreate', 'title': 'Affiché sur formulaires de création'},
                {"data": 'displayedFormsEdit', 'title': 'Affiché sur formulaires d\'édition'},
                {"data": 'displayedFilters', 'title': 'Affiché sur les filtres'}
            ],
            rowConfig: {
                needsRowClickAction: true,
            },
            order: [[1, "asc"]],
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

function switchDisplay($displayedCheckbox) {
    let $requiredCheckbox;
    if ($displayedCheckbox.attr('name') === 'displayed-forms-create') {
        $requiredCheckbox = $('.checkbox[name="mustToCreate"]');
    } else if ($displayedCheckbox.attr('name') === 'displayed-forms-edit') {
        $requiredCheckbox = $('.checkbox[name="mustToModify"]');
    }

    if (!$displayedCheckbox.prop('checked')) {
        $requiredCheckbox.prop('checked', false);
    } else if (!$displayedCheckbox.prop('checked')) {
        $requiredCheckbox.prop('checked', false);
    }
}

function switchDisplayByMust($requiredCheckbox) {
    let $displayedCheckbox;
    if ($requiredCheckbox.attr('name') === 'mustToCreate') {
        $displayedCheckbox = $('.checkbox[name="displayed-forms-create"]');
    } else if ($requiredCheckbox.attr('name') === 'mustToModify') {
        $displayedCheckbox = $('.checkbox[name="displayed-forms-edit"]');
    }

    if (!$displayedCheckbox.prop('checked')) {
        $requiredCheckbox.prop('checked', false);
    } else if (!$displayedCheckbox.prop('checked')) {
        $requiredCheckbox.prop('checked', false);
    }
}

function editDispatchEmergencies() {
    $.post(Routing.generate('set_dispatch_emergencies'), {value: $(this).val()}, (resp) => {
        if (resp) {
            showBSAlert("La liste urgences d'acheminements a bien été mise à jour.");
        } else {
            showBSAlert("Une erreur est survenue lors de la mise à jour de la liste urgences d'acheminements.");
        }
    });
}

function editBusinessUnit($select, paramName) {
    const val = $select.val() || [];
    const valStr = JSON.stringify(val);
    $.post(Routing.generate('toggle_params'), JSON.stringify({param: paramName, val: valStr})).then((resp) => {
        if (resp) {
            showBSAlert("La liste business unit a bien été mise à jour.");
        } else {
            showBSAlert("Une erreur est survenue lors de la mise à jour de la liste business unit.");
        }
    })
}
