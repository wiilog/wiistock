$('.select2').select2();
let modalDeleteEmplacement = $('#modalDeleteEmplacement');
let submitDeleteEmplacement = $('#submitDeleteEmplacement');
let urlDeleteEmplacement = Routing.generate('emplacement_delete', true);

const locationsTableConfig = {
    processing: true,
    serverSide: true,
    lengthMenu: [10, 25, 50, 100, 1000],
    order: [['name', 'desc']],
    ajax: {
        url: Routing.generate("emplacement_api", true),
        type: "POST",
        dataSrc: function (json) {
            $('#listEmplacementIdToPrint').val(json.listId);
            return json.data;
        }
    },
    drawConfig: {
        needsEmplacementSearchOverride: true,
    },
    rowConfig: {
        needsRowClickAction: true,
    },
    columns: [
        {data: 'actions', title: '', className: 'noVis', orderable: false},
        {data: 'pairing', title: '', className: 'pairing-row'},
        {data: 'name', title: 'Nom'},
        {data: 'description', title: 'Description'},
        {data: 'deliveryPoint', title: 'Point de livraison'},
        {data: 'ongoingVisibleOnMobile', title: 'Encours visible'},
        {data: 'maxDelay', title: 'Délai maximum'},
        {data: 'active', title: 'Actif / Inactif'},
        {data: 'allowedNatures', title: `Natures autorisées`, orderable: false},
        {data: 'allowedTemperatures', title: 'Températures autorisées', orderable: false},
        {data: 'signatories', title: 'Signataires', orderable: false},
        {data: 'email', title: 'Email'},
    ]
};

const groupsTableConfig = {
    processing: true,
    serverSide: true,
    lengthMenu: [10, 25, 50, 100, 1000],
    order: [['label', 'desc']],
    ajax: {
        url: Routing.generate("location_group_api", true),
        type: "POST",
    },
    rowConfig: {
        needsRowClickAction: true,
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    columns: [
        {data: 'actions', title: '', className: 'noVis', orderable: false},
        {data: 'pairing', title: '', className: 'pairing-row'},
        {data: 'label', title: 'Nom'},
        {data: 'description', title: 'Description'},
        {data: 'active', title: 'Actif / Inactif'},
        {data: 'locations', title: 'Nombre emplacements', orderable: false},
    ]
};

const TAB_LOCATIONS = 1;
const TAB_GROUPS = 2;

const HASH_LOCATIONS = `#emplacements`;
const HASH_GROUPS = `#groupes`;

let selectedTab = TAB_LOCATIONS;
let locationsTable;
let groupsTable;

$(document).ready(() => {
    managePrintButtonTooltip(true, $('#btnPrint'));

    switchPageBasedOnHash();
    $(window).on("hashchange", switchPageBasedOnHash);
})


function switchPageBasedOnHash() {
    let hash = window.location.hash;
    if (hash === HASH_LOCATIONS) {
        switchLocations();
    } else if(hash === HASH_GROUPS) {
        switchGroups();
    } else {
        switchLocations();
        window.location.hash = HASH_LOCATIONS;
    }

    $(`.location-tabs a`).removeClass(`active`);
    $(`.location-tabs a[href="${hash}"]`).addClass(`active`);
}

function switchLocations() {
    selectedTab = TAB_LOCATIONS;
    window.location.hash = HASH_LOCATIONS;

    if(!locationsTable) {
        locationsTable = initDataTable(`locationsTable`, locationsTableConfig);

        let $modalNewEmplacement = $("#modalNewEmplacement");
        let $submitNewEmplacement = $("#submitNewEmplacement");
        let urlNewEmplacement = Routing.generate('emplacement_new', true);
        InitModal($modalNewEmplacement, $submitNewEmplacement, urlNewEmplacement, {tables: [locationsTable]});

        InitModal(modalDeleteEmplacement, submitDeleteEmplacement, urlDeleteEmplacement, {tables: [locationsTable]});

        let $modalModifyEmplacement = $('#modalEditEmplacement');
        let $submitModifyEmplacement = $('#submitEditEmplacement');
        let urlModifyEmplacement = Routing.generate('emplacement_edit', true);
        InitModal($modalModifyEmplacement, $submitModifyEmplacement, urlModifyEmplacement, {tables: [locationsTable]});
    } else {
        locationsTable.ajax.reload();
    }

    $(`.locationsTableContainer, [data-target="#modalNewEmplacement"]`).removeClass('d-none');
    $(`.action-button`).removeClass('d-none');
    $(`.groupsTableContainer, [data-target="#modalNewLocationGroup"]`).addClass('d-none');
    $(`#locationsTable_filter`).parent().show();
    $(`#groupsTable_filter`).parent().hide();
}

function switchGroups() {
    selectedTab = TAB_GROUPS;
    window.location.hash = HASH_GROUPS;

    if(!groupsTable) {
        groupsTable = initDataTable(`groupsTable`, groupsTableConfig);

        let $modalNewEmplacement = $("#modalNewLocationGroup");
        let $submitNewEmplacement = $("#submitNewLocationGroup");
        let urlNewEmplacement = Routing.generate('location_group_new', true);
        InitModal($modalNewEmplacement, $submitNewEmplacement, urlNewEmplacement, {tables: [groupsTable]});

        const modalDeleteEmplacement = $('#modalDeleteLocationGroup');
        const urlDeleteEmplacement = Routing.generate('location_group_delete', true);
        InitModal(modalDeleteEmplacement, '#submitDeleteLocationGroup', urlDeleteEmplacement, {tables: [groupsTable]});

        const $modalModifyEmplacement = $('#modalEditLocationGroup');
        const $submitModifyEmplacement = $('#submitEditLocationGroup');
        const urlModifyEmplacement = Routing.generate('location_group_edit', true);
        InitModal($modalModifyEmplacement, $submitModifyEmplacement, urlModifyEmplacement, {tables: [groupsTable]});
    } else {
        groupsTable.ajax.reload();
    }

    $(`.locationsTableContainer, [data-target="#modalNewEmplacement"]`).addClass('d-none');
    $(`.action-button`).addClass('d-none');
    $(`.groupsTableContainer, [data-target="#modalNewLocationGroup"]`).removeClass('d-none');
    $(`#locationsTable_filter`).parent().hide();
    $(`#groupsTable_filter`).parent().show();
}

function checkAndDeleteRowEmplacement(icon) {
    let modalBody = modalDeleteEmplacement.find('.modal-body');
    let id = icon.data('id');
    let param = JSON.stringify(id);
    $.post(Routing.generate('emplacement_check_delete'), param, function (resp) {
        modalBody.html(resp.html);
        submitDeleteEmplacement.attr('value', id);

        if (resp.delete == false) {
            submitDeleteEmplacement.text('Désactiver');
        } else {
            submitDeleteEmplacement.text('Supprimer');
        }
    });
}

function printLocationsBarCodes($button, event) {
    if (!$button.hasClass('disabled')) {
        window.location.href = Routing.generate('print_locations_bar_codes', {
            listEmplacements: $("#listEmplacementIdToPrint").val()
        }, true);
    }
    else {
        event.stopPropagation();
    }
}
