const packsTableConfig = {
    responsive: true,
    serverSide: true,
    processing: true,
    order: [['packLastDate', "desc"]],
    ajax: {
        url: Routing.generate('pack_api', true),
        type: "POST",
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    rowConfig: {
        needsRowClickAction: true
    },
    columns: [
        {data: 'actions', name: 'actions', title: '', className: 'noVis', orderable: false},
        {data: 'pairing', name: 'pairing', title: '', className: 'pairing-row'},
        {data: 'packNum', name: 'packNum', title: Translation.of( 'Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', 'Numéro d\'UL')},
        {data: 'packNature', name: 'packNature', title: Translation.of( 'Traçabilité', 'Unités logistiques', 'Divers', 'Nature d\'unité logistique')},
        {data: "quantity", name: 'quantity',  'title': Translation.of( 'Traçabilité', 'Général', 'Quantité')},
        {data: 'packLastDate', name: 'packLastDate', title: Translation.of( 'Traçabilité', 'Général', 'Date dernier mouvement')},
        {data: "packOrigin", name: 'packOrigin', title: Translation.of( 'Traçabilité', 'Général', 'Issu de'), className: 'noVis', orderable: false},
        {data: "packLocation", name: 'packLocation', title: Translation.of( 'Traçabilité', 'Général', 'Emplacement')},
    ]
};

const groupsTableConfig = {
    responsive: true,
    serverSide: true,
    processing: true,
    order: [['actions', "desc"]],
    ajax: {
        url: Routing.generate('group_api', true),
        type: "POST",
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    rowConfig: {
        needsRowClickAction: true
    },
    columns: [
        {data: `actions`, name: `actions`, title: '', className: 'noVis', orderable: false, width: `10px`},
        {data: `details`, name: `details`, orderable: false},
    ],
};

const TAB_PACKS = 1;
const TAB_GROUPS = 2;

const HASH_PACKS = `#colis`;
const HASH_GROUPS = `#groupes`;

let selectedTab = TAB_PACKS;
let packsTable;
let groupsTable;

$(function() {
    $('.select2').select2();
    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';

    initDateTimePicker('#dateMin, #dateMax', DATE_FORMATS_TO_DISPLAY[format]);
    Select2Old.init($('.filter-select2[name="natures"]'), Translation.of('Traçabilité', 'Général', 'Natures', false));
    Select2Old.location($('.ajax-autocomplete-emplacements'), {}, Translation.of( 'Traçabilité', 'Général', 'Emplacement', false), 3);

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_PACK);
    $.post(path, params, function(data) {
        displayFiltersSup(data, true);
    }, 'json');

    switchPageBasedOnHash();
    $(window).on("hashchange", switchPageBasedOnHash);
});

function switchPageBasedOnHash() {
    let hash = window.location.hash;
    if (hash === HASH_PACKS) {
        switchPacks();
    } else if(hash === HASH_GROUPS) {
        switchGroups();
    } else {
        switchPacks();
        window.location.hash = HASH_PACKS;
    }

    $(`.pack-tabs a`).removeClass(`active`);
    $(`.pack-tabs a[href="${hash}"]`).addClass(`active`);
}

function switchPacks() {
    selectedTab = TAB_PACKS;
    window.location.hash = HASH_PACKS;

    if(!packsTable) {
        packsTable = initDataTable(`packsTable`, packsTableConfig);

        const $modalEditPack = $('#modalEditPack');
        const $submitEditPack = $('#submitEditPack');
        const urlEditPack = Routing.generate('pack_edit', true);
        InitModal($modalEditPack, $submitEditPack, urlEditPack, {tables: [packsTable]});

        let modalDeletePack = $("#modalDeletePack");
        let SubmitDeletePack = $("#submitDeletePack");
        let urlDeletePack = Routing.generate('pack_delete', true);
        InitModal(modalDeletePack, SubmitDeletePack, urlDeletePack, {tables: [packsTable], clearOnClose: true});
    } else {
        packsTable.ajax.reload();
    }

    $(`.packsTableContainer`).show();
    $(`.groupsTableContainer`).hide();
    $(`#packsTable_filter`).parent().show();
    $(`#groupsTable_filter`).parent().hide();
}

function switchGroups() {
    selectedTab = TAB_GROUPS;
    window.location.hash = HASH_GROUPS;

    if(!groupsTable) {
        groupsTable = initDataTable(`groupsTable`, groupsTableConfig);

        const $modalEditGroup = $('#modalEditGroup');
        const $submitEditGroup = $('#submitEditGroup');
        const urlEditGroup = Routing.generate('group_edit', true);
        InitModal($modalEditGroup, $submitEditGroup, urlEditGroup, {tables: [groupsTable]});

        const $modalUngroup = $('#modalUngroup');
        const $submitUngroup = $('#submitUngroup');
        const urlUngroup = Routing.generate('group_ungroup', true);
        InitModal($modalUngroup, $submitUngroup, urlUngroup, {tables: [groupsTable]});
    } else {
        groupsTable.ajax.reload();
    }

    $(`.packsTableContainer`).hide();
    $(`.groupsTableContainer`).show();
    $(`#packsTable_filter`).parent().hide();
    $(`#groupsTable_filter`).parent().show();
}

function toExport() {
    if(selectedTab === TAB_PACKS) {
        saveExportFile(
            `export_packs`,
            true,
            {},
            false,
            Translation.of('Général', null, 'Modale', 'Veuillez saisir des dates dans le filtre en haut de page.'),
            true
        );
    } else {
        saveExportFile(
            `export_groups`,
            true,
            {},
            false,
            Translation.of('Général', null, 'Modale', 'Veuillez saisir des dates dans le filtre en haut de page.'),
            true
        );
    }
}

function initializeGroupHistoryTable(packId) {
    initDataTable('groupHistoryTable', {
        serverSide: true,
        processing: true,
        order: [['date', "desc"]],
        ajax: {
            "url": Routing.generate('group_history_api', {pack: packId}, true),
            "type": "POST"
        },
        columns: [
            {data: 'group', name: 'group', title: Translation.of('Traçabilité', 'Mouvements', 'Groupe')},
            {data: 'date', name: 'date', title: Translation.of('Traçabilité', 'Général', 'Date')},
            {data: 'type', name: 'type', title: Translation.of('Traçabilité', 'Mouvements', 'Type')},
        ],
        domConfig: {
            needsPartialDomOverride: true,
        }
    });
}
