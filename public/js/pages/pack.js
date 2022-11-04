const packsTableConfig = {
    responsive: true,
    serverSide: true,
    processing: true,
    order: [['packLastDate', "desc"]],
    ajax: {
        url: Routing.generate('pack_api', true),
        type: "POST",
        data: {
            codeUl: $('#lu-code').val(),
        },
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    rowConfig: {
        needsRowClickAction: true
    },
    columns: [
        {data: 'actions', name: 'actions', title: '<div class="w-100 text-right"><span class="wii-icon wii-icon-cart add-all-cart pointer"></span></div>', className: 'noVis', orderable: false},
        {data: 'pairing', name: 'pairing', title: '', className: 'pairing-row'},
        {data: 'packNum', name: 'packNum', title: Translation.of('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', 'Numéro d\'UL')},
        {data: 'packNature', name: 'packNature', title: Translation.of('Traçabilité', 'Général', 'Nature')},
        {data: `quantity`, name: 'quantity',  'title': Translation.of('Traçabilité', 'Général', 'Quantité')},
        {data: `project`, name: 'project',  'title': Translation.of('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Projet')},
        {data: 'packLastDate', name: 'packLastDate', title: Translation.of('Traçabilité', 'Général', 'Date dernier mouvement')},
        {data: "packOrigin", name: 'packOrigin', title: Translation.of('Traçabilité', 'Général', 'Issu de'), className: 'noVis', orderable: false},
        {data: "packLocation", name: 'packLocation', title: Translation.of('Traçabilité', 'Général', 'Emplacement')},
    ],
    drawCallback: () => {
        toggleAddAllToCartButton();
        const codeUl = $('#lu-code').val();
        if(codeUl) {
            const $icon = $(`.logistic-unit-number .wii-icon`).first();
            const $container = $(`.packsTableContainer`);
            const $number = $icon.closest(`.logistic-unit-number`);
            $icon.trigger('mouseover');

            AJAX.route(`GET`, `logistic_unit_content`, {pack: $number.data(`id`)})
                .json()
                .then(result => {
                    $(`.logistic-unit-number`).removeClass(`.active`);
                    $number.addClass(`active`);
                    $container.append(result.html);
                    packsTable.columns.adjust();
                });
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

    const codeUl = $('#lu-code').val();
    if(codeUl) {
        displayFiltersSup([{field: 'colis', value: codeUl}], true);
    }
    else {
        $.post(path, params, function(data) {
            displayFiltersSup(data, true);
        }, 'json');
    }

    switchPageBasedOnHash();
    $(window).on("hashchange", switchPageBasedOnHash);

    $(document).arrive(`.add-cart`, function() {
        $(this).on("click", function() {
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

    $(document).arrive(`.logistic-unit-number .wii-icon`, function() {
        const $icon = $(this);
        const $number = $icon.closest(`.logistic-unit-number`);

        // register the event directly on the element through arrive
        // to get the event before action-on-click and be able to
        // cancel modal openning through event.stopPropagation
        $icon.on(`mouseup`, event => {
            event.stopPropagation();

            const $container = $(`.packsTableContainer`);
            $container.find(`.logistic-unit-content`).remove();

            if($number.is(`.active`)) {
                $number.removeClass(`active`);
                packsTable.columns.adjust().draw();
            } else {
                AJAX.route(`GET`, `logistic_unit_content`, {pack: $number.data(`id`)})
                    .json()
                    .then(result => {
                        $(`.logistic-unit-number`).removeClass(`.active`);
                        $number.addClass(`active`);
                        $container.append(result.html);
                        packsTable.columns.adjust();
                    });
            }
        })
    });
});

function addToCart(ids) {
    const path = Routing.generate('cart_add_ul',true);
    $.post(
        path,
        JSON.stringify({id : ids}),
        function (data) {
            data.messages.forEach((response) => {
                if (response.success) {
                    Flash.add('success', response.msg);
                }
                else {
                    Flash.add('danger', response.msg);
                }
            });

            if (data.cartQuantity !== undefined) {
                $('.header-icon.cart .icon-figure.small').removeClass(`d-none`).text(data.cartQuantity);
            }
        }
    );
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

function toggleAddAllToCartButton() {
    const $addAllCart = $('.add-all-cart').parent();
    if ($('.add-cart').length === 0) {
        console.log('== 1')
        $addAllCart.addClass('d-none');
    }
    else {
        console.log('== 2')
        $addAllCart.removeClass('d-none');
    }
}
