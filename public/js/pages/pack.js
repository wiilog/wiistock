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
        {data: 'packNum', name: 'packNum', title: 'colis.Numéro colis', translated: true},
        {data: 'packNature', name: 'packNature', title: 'natures.Nature de colis', translated: true},
        {data: "quantity", name: 'quantity', title: 'Quantité'},
        {data: 'packLastDate', name: 'packLastDate', title: 'Date du dernier mouvement'},
        {data: "packOrigin", name: 'packOrigin', title: 'Issu de', className: 'noVis', orderable: false},
        {data: "packLocation", name: 'packLocation', title: 'Emplacement'},
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
    initDateTimePicker();
    Select2Old.init($('.filter-select2[name="natures"]'), 'Natures');
    Select2Old.location($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_PACK);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
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
        saveExportFile(`export_packs`);
    } else {
        saveExportFile(`export_groups`);
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
            {data: 'group', name: 'group', title: 'Groupe'},
            {data: 'date', name: 'date', title: 'Date'},
            {data: 'type', name: 'type', title: 'Type'},
        ],
        domConfig: {
            needsPartialDomOverride: true,
        }
    });
}
