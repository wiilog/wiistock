import '@styles/pages/pack/timeline.scss';
import AJAX, {POST, GET} from "@app/ajax";
import Flash from "@app/flash";
import {exportFile, getUserFiltersByPage} from "@app/utils";
import {initEditPackModal, deletePack, getTrackingHistory, reloadLogisticUnitTrackingDelay, addToCart, initializeGroupContentTable, initUngroupModal} from "@app/pages/pack/common";

let packsTableConfig = {
    responsive: true,
    serverSide: true,
    processing: true,
    page: 'packList',
    order: [['lastMovementDate', "desc"]],
    ajax: {
        url: Routing.generate('pack_api', true),
        type: POST,
        data: {
            codeUl: $('#lu-code').val(),
            natures: $('[name="natures"]').val(),
            locations: $('[name="emplacement"]').val(),
            isPackWithTracking: $('[name="packWithTracking"]').is(':checked'),
            fromDashboard: $('[name="fromDashboard"]').val(),
        },
    },
    rowConfig: {
        needsRowClickAction: true
    },
    drawCallback: () => {
        // remove open logistic unit details pane if pack is not in the list
        // for example on page changing it is triggered
        const $container = $(`.packsTableContainer`);
        const $logisticUnitPane = $container.find(`.logistic-unit-content`);
        if ($logisticUnitPane.exists()) {
            const pack = $logisticUnitPane.data(`pack`);
            const $activeButton = $container.find(`.open-content-button[data-pack="${pack}"]`);

            if (!$activeButton.exists()) {
                closeContentContainer($container);
            } else {
                const $line = $activeButton.closest('tr');
                $line.addClass('active');
            }
        }

        const codeUl = $('#lu-code').val();
        if (codeUl) {
            $(`.open-content-button`).trigger(`mouseup`);
        }
    }
};
let packsTable;

global.callbackSaveFilter = callbackSaveFilter;

$(function () {
    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';
    initDateTimePicker('#dateMin, #dateMax', DATE_FORMATS_TO_DISPLAY[format]);

    const requestQuery = GetRequestQuery();

    const codeUl = $('#lu-code').val();
    if (!requestQuery.dashboardcomponentid) {
        if ((!codeUl || codeUl.length === 0)) {
            getUserFiltersByPage(PAGE_PACK);
        } else {
            displayFiltersSup([{field: 'UL', value: codeUl}], true);
        }
    }

    $(document).arrive(`.add-cart`, function () {
        const $buttons = $(this);

        // register the event directly on the element through arrive
        // to get the event before action-on-click and be able to
        // cancel modal openning through event.stopPropagation
        $buttons.on(`mouseup`, event => {
            const id = $(this).data(`id`);
            event.stopPropagation();
            addToCart([id]);
        })
    });

    $(document).on('click', `.add-all-cart`, function () {
        const ids = $('.add-cart')
            .map(function () {
                return $(this).data(`id`);
            })
            .toArray();
        addToCart(ids);
    })

    $(document).arrive('.origin', function () {
        const $origin = $(this);

        // register the event directly on the element through arrive
        // to get the event before action-on-click and be able to
        // cancel modal openning through event.stopPropagation
        $origin.on(`mouseup`, event => {
            event.stopPropagation();
        })
    });

    $(document).arrive(`.open-content-button`, function () {
        fireContentButtonClick($(this));
    });

    $(document).on(`click`, `.logistic-unit-tab`, function () {
        const $tab = $(this);
        const $parent = $tab.closest(`.logistic-unit-content`);

        $(`.logistic-unit-tab`).removeClass(`active`);
        $tab.addClass(`active`);

        $parent.find(`.content`).addClass(`d-none`);
        $parent.find(`.content${$tab.data(`target`)}`).removeClass(`d-none`);
    })

    if (!packsTable) {
        const $packTable = $('#packsTable');
        packsTable = initDataTable($packTable, packsTableConfig);
        initEditPackModal({
            tables: [packsTable],
        });
        initUngroupModal({
            tables: [packsTable],
        });
    }

    $(document)
        .on('click', '.delete-pack', function () {
            deletePack({ 'pack' : $(this).data('id') }, packsTable);
        })
        .on('click', '.reload-tracking-delay', function () {
            reloadLogisticUnitTrackingDelay($(this).data('id'), () => {
                packsTable.ajax?.reload();
            });
        });

    $('.exportPacks').on('click', function () {
        exportFile(
            `pack_export`,
            {},
            {
                needsAllFilters: true,
                needsDateFormatting: true,
            }
        )

    });
});

function fireContentButtonClick($openContentButton){
    let isLoading = false;

    // register the event directly on the element through arrive
    // to get the event before action-on-click and be able to
    // cancel modal openning through event.stopPropagation
    $openContentButton.on(`mouseup`, event => {
        event.stopPropagation();
        if (isLoading) {
            Flash.add(`info`, `Chargement du contenu de l'unité logistique en cours`)
            return;
        }

        isLoading = true;

        const $container = $(`.packsTableContainer`);
        const $line = $openContentButton.closest('tr');

        if ($line.is(`.active`)) {
            closeContentContainer($container);
            isLoading = false;
        } else {
            const logisticUnitId = $openContentButton.data(`id`);
            AJAX.route(GET, `pack_content`, {pack: logisticUnitId})
                .json()
                .then(result => {
                    closeContentContainer($container, false);

                    $line.addClass('active');
                    $container.append(result.html);

                    packsTable.columns.adjust();

                    $container
                        .find('.logistic-unit-content button.close')
                        .off('click.fireContentButtonClick')
                        .on('click.fireContentButtonClick', function () {
                            closeContentContainer($container);
                            isLoading = false;
                        });

                    getTrackingHistory(logisticUnitId, false);
                    initializeGroupContentTable(logisticUnitId, false);
                    isLoading = false;
                });
        }
    });
}

/**
 * @param {jQuery} $tableContainer
 * @param {boolean} adjustTable
 */
function closeContentContainer($tableContainer, adjustTable = true) {
    $tableContainer.find('.active').removeClass('active');
    $tableContainer.find(`.logistic-unit-content`).remove();

    if (adjustTable) {
        packsTable.columns.adjust();
    }
}

function callbackSaveFilter() {
    window.location.href = Routing.generate('pack_index');
}
