const packsTableConfig = {
    responsive: true,
    serverSide: true,
    processing: true,
    order: [['quantity', "desc"]],
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
        {data: 'packNum', name: 'packNum', title: 'colis.Numéro colis', translated: true},
        {data: 'packNature', name: 'packNature', title: 'natures.Nature de colis', translated: true},
        {data: "quantity", name: 'quantity', title: 'Quantité'},
        {data: 'packLastDate', name: 'packLastDate', title: 'Date du dernier mouvement'},
        {data: "packOrigin", name: 'packOrigin', title: 'Issu de', className: 'noVis', orderable: false},
        {data: "packLocation", name: 'packLocation', title: 'Emplacement'},
        {data: "arrivageType", name: 'arrivageType', title: 'Type d\'arrivage'},

    ]
};

const groupsTableConfig = {
    responsive: true,
    serverSide: true,
    processing: true,
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
        {data: `actions`, name: `actions`, orderable: false, width: `10px`},
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

    $(`#to-packs`).click(switchPacks);
    $(`#to-groups`).click(switchGroups);
});

function switchPageBasedOnHash() {
    let hash = window.location.hash;
    if (hash === HASH_PACKS) {
        switchPacks();
    } else if(hash === HASH_GROUPS) {
        switchGroups();
    } else if(hash) {
        switchPacks();
        window.location.hash = HASH_PACKS;
    }
}

function switchPacks() {
    selectedTab = TAB_GROUPS;
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
    }

    $(`#to-packs`).addClass(`active`);
    $(`#to-groups`).removeClass(`active`);
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
    }

    $(`#to-packs`).removeClass(`active`);
    $(`#to-groups`).addClass(`active`);
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
