import AJAX, {POST, DELETE} from "@app/ajax";
import Form from "@app/form";
import Modal from "@app/modal";
import Routing from '@app/fos-routing';
import {initDataTable} from "@app/datatable";
import {togglePrintButton} from "@app/utils";

global.printLocationsBarCodes = printLocationsBarCodes;
global.editZone = editZone;
global.deleteZone = deleteZone;

const locationsTableConfig = {
    processing: true,
    serverSide: true,
    lengthMenu: [10, 25, 50, 100, 1000],
    order: [['name', 'desc']],
    ajax: {
        url: Routing.generate("emplacement_api", true),
        type: POST,
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
    drawCallback: () => {
        const datatable = $(`#locationsTable`).DataTable();
        togglePrintButton(datatable, $(`.printButton`), () => datatable.search());
    }
};

const groupsTableConfig = {
    processing: true,
    serverSide: true,
    lengthMenu: [10, 25, 50, 100, 1000],
    order: [['label', 'desc']],
    ajax: {
        url: Routing.generate("location_group_api", true),
        type: POST,
    },
    rowConfig: {
        needsRowClickAction: true,
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
        url: Routing.generate("zone_api", true),
        type: POST,
    },
    rowConfig: {
        needsRowClickAction: true,
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

$(function() {
    $('.select2').select2();

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

        const $locationTable = $(`#locationsTable`);
        locationsTable = initDataTable($locationTable, locationsTableConfig);

        $(document)
            .on('click', '.edit-location', function() {
                const locationId = $(this).data('id');
                Modal.load('location_get_form', {location: locationId }, $('#modalEditLocation'), $(this));
            })
            .on('click', '.delete-location', function() {
                let id = $(this).data('id');
                Modal.confirm({
                    ajax: {
                        method: DELETE,
                        route: 'emplacement_delete',
                        params: {
                            location: id,
                        },
                    },
                    message: 'Voulez-vous réellement supprimer cet emplacement ?',
                    title: 'Supprimer l\'emplacement',
                    validateButton: {
                        color: 'danger',
                        label: 'Supprimer'
                    },
                    table: locationsTable,
                });
            });

        const $newLocationButton = $('.new-location');
        const $modalNewLocation = $('#modalNewLocation');
        Form
            .create($modalNewLocation)
            .onOpen(() => {
                Modal.load('location_get_form', {}, $modalNewLocation, $newLocationButton)
            })
            .submitTo(
                POST,
                "emplacement_new",
                {
                    tables: [locationsTable],
                }
            )

        Form
            .create('#modalEditLocation')
            .submitTo(
                POST,
                "emplacement_edit",
                {
                    tables: [locationsTable],
                }
            )

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
                        AJAX.route(POST, 'zone_new')
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
            method: DELETE,
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
                AJAX.route(POST, 'zone_edit')
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
