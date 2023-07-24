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
        {data: 'deliveryPoint', title: 'Point de ' + Translation.of('Demande', 'Livraison', 'Livraison', false).toLowerCase()},
        {data: 'ongoingVisibleOnMobile', title: 'Encours visible'},
        {data: 'maxDelay', title: 'Délai maximum'},
        {data: 'active', title: 'Actif / Inactif'},
        {data: 'allowedNatures', title: `Natures autorisées`, orderable: false},
        {data: 'allowedTemperatures', title: 'Températures autorisées', orderable: false},
        {data: 'signatories', title: 'Signataires', orderable: false},
        {data: 'email', title: 'Email'},
        {data: 'zone', title: 'Zone'},
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

const zonesTableConfig = {
    processing: true,
    serverSide: true,
    paging: true,
    lengthMenu: [10, 25, 50, 100],
    order: [['name', 'desc']],
    ajax: {
        url: Routing.generate("zones_api", true),
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
        {data: 'name', title: 'Nom'},
        {data: 'description', title: 'Description'},
        {data: 'active', title: 'Actif'},
    ]
};

const TAB_LOCATIONS = 1;
const TAB_GROUPS = 2;
const TAB_ZONES = 3;

const HASH_LOCATIONS = `#emplacements`;
const HASH_GROUPS = `#groupes`;
const HASH_ZONES = `#zones`;

let selectedTab = TAB_LOCATIONS;
let locationsTable;
let groupsTable;
let zonesTable;

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
    } else if(hash === HASH_ZONES) {
        switchZones();
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

        $(`.new-location`).on(`click`, function() {
            wrapLoadingOnActionButton($(this), () => (
                AJAX.route(`GET`, `location_api_new`)
                    .json()
                    .then(({content}) => {
                        const $modal = $(`#modalNewEmplacement`);
                        $modal.find(`.modal-body`).html(content);
                        $('.select2').select2();

                        $modal.modal(`show`);
                    })
            ))
        });

        let $modalNewEmplacement = $("#modalNewEmplacement");
        let $submitNewEmplacement = $("#submitNewEmplacement");
        let urlNewEmplacement = Routing.generate('emplacement_new', true);
        InitModal($modalNewEmplacement, $submitNewEmplacement, urlNewEmplacement, {tables: [locationsTable]});

        InitModal(modalDeleteEmplacement, submitDeleteEmplacement, urlDeleteEmplacement, {
            tables: [locationsTable],
            success: () => {},
            error: (response) => {
                showBSAlert(response.msg, 'danger');
            }
        });

        let $modalModifyEmplacement = $('#modalEditEmplacement');
        let $submitModifyEmplacement = $('#submitEditEmplacement');
        let urlModifyEmplacement = Routing.generate('emplacement_edit', true);
        InitModal($modalModifyEmplacement, $submitModifyEmplacement, urlModifyEmplacement, {tables: [locationsTable]});
    } else {
        locationsTable.ajax.reload();
    }

    $(`.locationsTableContainer, .new-location`).removeClass('d-none');
    $(`.action-button`).removeClass('d-none');
    $(`.groupsTableContainer, [data-target="#modalNewLocationGroup"]`).addClass('d-none');
    $(`.zonesTableContainer, [data-target="#modalNewZone"]`).addClass('d-none');
    $(`#locationsTable_filter`).parent().show();
    $(`#groupsTable_filter`).parent().hide();
    $(`#zonesTable_filter`).parent().hide();
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

    $(`.locationsTableContainer, .new-location`).addClass('d-none');
    $(`.action-button`).addClass('d-none');
    $(`.groupsTableContainer, [data-target="#modalNewLocationGroup"]`).removeClass('d-none');
    $(`.zonesTableContainer, [data-target="#modalNewZone"]`).addClass('d-none');
    $(`#locationsTable_filter`).parent().hide();
    $(`#groupsTable_filter`).parent().show();
    $(`#zonesTable_filter`).parent().hide();

}

function switchZones() {
    selectedTab = TAB_ZONES;
    window.location.hash = HASH_ZONES;

    if(!zonesTable) {
        zonesTable = initDataTable(`zonesTable`, zonesTableConfig);

        $('.newZoneButton').on('click', function(){
            let $modalNewZone = $("#modalNewZone");
            Form.create($modalNewZone)
                .clearSubmitListeners()
                .onSubmit((data, form) => {
                    form.loading(() => (
                        AJAX.route(AJAX.POST, 'zone_new')
                            .json(data)
                            .then(({success}) => {
                                if(success){
                                    $modalNewZone.modal(`hide`);
                                    zonesTable.ajax.reload();
                                }
                            })
                    ), false)
                });
            $modalNewZone.modal(`show`);
        });
    } else {
        zonesTable.ajax.reload();
    }

    $(`.locationsTableContainer, .new-location`).addClass('d-none');
    $(`.groupsTableContainer, [data-target="#modalNewLocationGroup"]`).addClass('d-none');
    $(`.action-button`).addClass('d-none');
    $(`.zonesTableContainer, [data-target="#modalNewZone"]`).removeClass('d-none');
    $(`#locationsTable_filter`).parent().hide();
    $(`#groupsTable_filter`).parent().hide();
    $(`#zonesTable_filter`).parent().show();
}

function deleteZone($button){
    const zone = $button.data('id');
    Modal.confirm({
        ajax: {
            method: AJAX.DELETE,
            route: 'zone_delete',
            params: {zone},
        },
        message: 'Voulez-vous réellement supprimer cette zone ?',
        title: 'Supprimer la zone',
        validateButton: {
            color: 'danger',
            label: 'Supprimer',
        },
        cancelButton: {
            label: 'Annuler',
        },
        table: zonesTable,
    });
}

function editZone(zoneId){
    let $modalEditZone = $("#modalEditZone");
    Form.create($modalEditZone)
        .clearSubmitListeners()
        .onSubmit((data, form) => {
            form.loading(() => (
                AJAX.route(AJAX.POST, 'zone_edit')
                    .json(data)
                    .then(({success}) => {
                        if(success){
                            $modalEditZone.modal(`hide`);
                            zonesTable.ajax.reload();
                        }
                    })
            ), false)
        });
    $modalEditZone.modal(`show`);
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
