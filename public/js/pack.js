$(function() {
    $('.select2').select2();
    initDateTimePicker();
    initSelect2($('.filter-select2[name="natures"]'), 'Natures');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_PACK);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');

    ajaxAutoCompleteEmplacementInit($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);

    const packsTable = initDataTable('packsTable', {
        responsive: true,
        serverSide: true,
        processing: true,
        order: [[3, "desc"]],
        ajax: {
            "url": Routing.generate('pack_api', true),
            "type": "POST",
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        rowConfig: {
            needsRowClickAction: true
        },
        columns: [
            {"data": 'actions', 'name': 'actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'packNum', 'name': 'packNum', 'title': $('#packCodeTranslation').val()},
            {"data": 'packNature', 'name': 'packNature', 'title': $('#packNatureTranslation').val()},
            {"data": "quantity", 'name': 'quantity', 'title': 'Quantité'},
            {"data": 'packLastDate', 'name': 'packLastDate', 'title': 'Date du dernier mouvement'},
            {"data": "packOrigin", 'name': 'packOrigin', 'title': 'Issu de', className: 'noVis'},
            {"data": "packLocation", 'name': 'packLocation', 'title': 'Emplacement'}
        ]
    });

    const $modalEditPack = $('#modalPack');
    const $submitEditPack = $('#submitEditPack');
    const urlEditPack = Routing.generate('pack_edit', true);
    InitModal($modalEditPack, $submitEditPack, urlEditPack, {tables: [packsTable]});
});
