import {getTrackingHistory} from "./show";
import '@styles/pages/pack/timeline.scss';
import AJAX, {POST} from "@app/ajax";
import Flash, {ERROR, SUCCESS} from "@app/flash";

global.toExport = toExport;
global.reloadLogisticUnitTrackingDelay = reloadLogisticUnitTrackingDelay;

const packsTableConfig = {
    responsive: true,
    serverSide: true,
    processing: true,
    page: 'packList',
    order: [['lastMovementDate', "desc"]],
    ajax: {
        url: Routing.generate('pack_api', true),
        type: "POST",
        data: {
            codeUl: $('#lu-code').val(),
        },
    },
    rowConfig: {
        needsRowClickAction: true
    },
    drawCallback: () => {
        toggleAddAllToCartButton();

        //remove open logistic unit details pane
        const $logisticUnitPane = $(`.logistic-unit-content`);
        if($logisticUnitPane.exists()) {
            const pack = $logisticUnitPane.data(`pack`);
            const $number = $(`.logistic-unit-number[data-pack="${pack}"]`);

            if(!$number.exists()) {
                $logisticUnitPane.remove();
                packsTable.columns.adjust();
            } else {
                $number.addClass(`active`);
            }
        }

        const codeUl = $('#lu-code').val();
        if(codeUl) {
            $(`.logistic-unit-number`).trigger(`mouseup`);
        }
    }
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

const HASH_PACKS = `#unites-logistiques`;
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

    const codeUl = $('#lu-code').val();
    if(codeUl) {
        displayFiltersSup([{field: 'LU', value: codeUl}], true);
    }
    else {
        $.post(path, params, function(data) {
            displayFiltersSup(data, true);
        }, 'json');
    }

    switchPageBasedOnHash();
    $(window).on("hashchange", switchPageBasedOnHash);

    $(document).arrive(`.add-cart`, function() {
        $(this).on("mouseup", function(event) {
            event.stopPropagation();

            const id = [$(this).data(`id`)];
            addToCart(id);
        });
    });

    $(document).on('click', `.add-all-cart`, function() {
        const ids = $('.add-cart')
            .map(function() {
                return $(this).data(`id`);
            })
            .toArray();
        addToCart(ids);
    })

    $(document).arrive('.origin', function() {
        const $origin = $(this);

        // register the event directly on the element through arrive
        // to get the event before action-on-click and be able to
        // cancel modal openning through event.stopPropagation
        $origin.on(`mouseup`, event => {
            event.stopPropagation();
        })
    });

    $(document).arrive(`.logistic-unit-number`, function() {
        const $number = $(this);

        let isLoading = false;

        // register the event directly on the element through arrive
        // to get the event before action-on-click and be able to
        // cancel modal openning through event.stopPropagation
        $number.on(`mouseup`, event => {
            event.stopPropagation();
            if(isLoading) {
                Flash.add(`info`, `Chargement du contenu de l'unité logistique en cours`)
                return;
            }

            isLoading = true;

            const $container = $(`.packsTableContainer`);

            if($number.is(`.active`)) {
                $number.removeClass(`active`);
                $container.find(`.logistic-unit-content`).remove();
                isLoading = false;
            } else {
                const logisticUnitId = $number.data(`id`);
                AJAX.route(`GET`, `pack_content`, {pack: logisticUnitId})
                    .json()
                    .then(result => {
                        $container.find(`.logistic-unit-content`).remove();
                        $(`.logistic-unit-number.active`).removeClass(`active`);
                        $number.addClass(`active`);
                        $container.append(result.html);
                        packsTable.columns.adjust();

                        getTrackingHistory(logisticUnitId, false);
                        isLoading = false;
                    });
            }
        })
    });

    $(document).on(`click`, `.logistic-unit-tab`, function() {
        const $tab = $(this);
        const $parent = $tab.closest(`.logistic-unit-content`);

        $(`.logistic-unit-tab`).removeClass(`active`);
        $tab.addClass(`active`);

        $parent.find(`.content`).addClass(`d-none`);
        $parent.find(`.content${$tab.data(`target`)}`).removeClass(`d-none`);
    })
});

function addToCart(ids) {
    AJAX.route(`POST`, `cart_add_logistic_units`, {ids: ids.join(`,`)})
        .json()
        .then(({messages, cartQuantity}) => {
            messages.forEach(({success, msg}) => {
                Flash.add(success ? `success` : `danger`, msg);
            });

            if (cartQuantity !== undefined) {
                $('.header-icon.cart .icon-figure.small').removeClass(`d-none`).text(cartQuantity);
            }
        });
}

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
        const $packTable = $('#packsTable');

        packsTable = initDataTable($packTable, packsTableConfig);

        const $modalEditPack = $('#modalEditPack');
        const $submitEditPack = $('#submitEditPack');
        const urlEditPack = Routing.generate('pack_edit', true);
        InitModal($modalEditPack, $submitEditPack, urlEditPack, {tables: [packsTable]});

        let modalDeletePack = $("#modalDeletePack");
        let SubmitDeletePack = $("#submitDeletePack");
        let urlDeletePack = Routing.generate('pack_delete', true);
        InitModal(modalDeletePack, SubmitDeletePack, urlDeletePack, {tables: [packsTable], clearOnClose: true, keepModal: false});
    } else {
        packsTable.ajax.reload();
    }

    $(`.packsTableContainer`).addClass('d-flex').removeClass('d-none');
    $(`.groupsTableContainer`).addClass('d-none');
    $(`#packsTable_filter`).parent().removeClass('d-none');
    $(`#groupsTable_filter`).parent().addClass('d-none');
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

    $(`.packsTableContainer`).removeClass('d-flex').addClass('d-none');
    $(`.groupsTableContainer`).removeClass('d-none');
    $(`#packsTable_filter`).parent().addClass('d-none');
    $(`#groupsTable_filter`).parent().removeClass('d-none');
}

function toExport() {
    if(selectedTab === TAB_PACKS) {
        saveExportFile(
            `pack_export`,
            true,
            {},
            false,
            Translation.of('Général', null, 'Modale', 'Veuillez saisir des dates dans le filtre en haut de page.'),
            true
        );
    } else {
        saveExportFile(
            `group_export`,
            true,
            {},
            false,
            Translation.of('Général', null, 'Modale', 'Veuillez saisir des dates dans le filtre en haut de page.'),
            true
        );
    }
}

function toggleAddAllToCartButton() {
    const $addAllCart = $('.add-all-cart');
    if ($('.add-cart').length === 0) {
        $addAllCart.addClass(`d-none`);
    }
    else {
        $addAllCart.removeClass(`d-none`);
    }
}

function reloadLogisticUnitTrackingDelay(logisticUnitId) {
    AJAX
        .route(POST, "pack_force_tracking_delay_calculation", {logisticUnit: logisticUnitId})
        .json()
        .then(({success}) => {
            if (success) {
                Flash.add(SUCCESS, "Le délai de traitement de l'unité logistique a bien été recalculé", true, true);
                packsTable.ajax.reload();
            }
            else {
                Flash.add(ERROR, "Une erreur est survenu lors du calcul du délai de traitement de l'unité logistique", true, true);
            }
        });
}
