import '@styles/pages/pack/timeline.scss';
import AJAX, {POST, GET} from "@app/ajax";
import Flash from "@app/flash";
import {exportFile} from "@app/utils";
import {initEditPackModal, deletePack, getTrackingHistory, reloadLogisticUnitTrackingDelay, addToCart, initializeGroupContentTable, initUngroupModal} from "@app/pages/pack/common";

const packsTableConfig = {
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
        },
    },
    rowConfig: {
        needsRowClickAction: true
    },
    drawCallback: () => {
        //remove open logistic unit details pane
        const $logisticUnitPane = $(`.logistic-unit-content`);
        if ($logisticUnitPane.exists()) {
            const pack = $logisticUnitPane.data(`pack`);
            const $number = $(`.logistic-unit-number[data-pack="${pack}"]`);

            if (!$number.exists()) {
                $logisticUnitPane.remove();
                packsTable.columns.adjust();
            } else {
                $number.addClass(`active`);
            }
        }

        const codeUl = $('#lu-code').val();
        if (codeUl) {
            $(`.logistic-unit-number`).trigger(`mouseup`);
        }
    }
};

let packsTable;

$(function () {
    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';
    initDateTimePicker('#dateMin, #dateMax', DATE_FORMATS_TO_DISPLAY[format]);

    const codeUl = $('#lu-code').val();
    if (!codeUl || codeUl.length === 0) {
        const params = JSON.stringify(PAGE_PACK);
        let path = Routing.generate('filter_get_by_page');
        $.post(path, params, function(data) {
            displayFiltersSup(data, true);
        }, 'json');
    } else {
        displayFiltersSup([{field: 'UL', value: codeUl}], true);
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

    $(document).arrive(`.logistic-unit-number`, function () {
        const $number = $(this);

        let isLoading = false;

        // register the event directly on the element through arrive
        // to get the event before action-on-click and be able to
        // cancel modal openning through event.stopPropagation
        $number.on(`mouseup`, event => {
            event.stopPropagation();
            if (isLoading) {
                Flash.add(`info`, `Chargement du contenu de l'unité logistique en cours`)
                return;
            }

            isLoading = true;

            const $container = $(`.packsTableContainer`);

            if ($number.is(`.active`)) {
                $number.removeClass(`active`);
                $container.find(`.logistic-unit-content`).remove();
                isLoading = false;
            } else {
                const logisticUnitId = $number.data(`id`);
                AJAX.route(GET, `pack_content`, {pack: logisticUnitId})
                    .json()
                    .then(result => {
                        $container.find(`.logistic-unit-content`).remove();
                        $(`.logistic-unit-number.active`).removeClass(`active`);
                        $number.addClass(`active`);
                        $container.append(result.html);
                        packsTable.columns.adjust();

                        getTrackingHistory(logisticUnitId, false);
                        initializeGroupContentTable(logisticUnitId, false);
                        isLoading = false;
                    });
            }
        })
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
