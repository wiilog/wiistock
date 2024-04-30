const ORDER_ACTION = `order`;
const SEARCH_ACTION = `search`;

global.initDataTable = initDataTable;
global.extendsDateSort = extendsDateSort;
global.moveSearchInputToHeader = moveSearchInputToHeader;
global.initSearchDate = initSearchDate;

$(function () {
    $.fn.dataTable.ext.errMode = () => {
        showBSAlert(`La requête n'est pas parvenue au serveur. Veuillez contacter le support si cela se reproduit.`, `danger`);
    };

    $(window).on(`shown.bs.collapse shown.bs.modal`, (event) => {
        const $collapse = $(event.target);
        $collapse.find(`.wii-table`).each(function () {
            const $table = $(this);
            $table.DataTable().columns.adjust().draw();
        });
    });

    onToggleInputRadioOnRow();
});

function extendsDateSort(name, format = ``) {
    $.extend($.fn.dataTableExt.oSort, {
        [name + `-pre`]: function (date) {
            if (format) {
                return moment(date, format).unix();
            } else {
                const dateSplitted = date.split(` `);
                const dateDaysParts = dateSplitted[0].split(`/`);
                const year = parseInt(dateDaysParts[2]);
                const month = parseInt(dateDaysParts[1]);
                const day = parseInt(dateDaysParts[0]);

                const dateHoursParts = dateSplitted.length > 1 ? dateSplitted[1].split(`:`) : [];
                const hours = dateHoursParts.length > 0 ? parseInt(dateHoursParts[0]) : 0;
                const minutes = dateHoursParts.length > 1 ? parseInt(dateHoursParts[1]) : 0;
                const seconds = dateHoursParts.length > 2 ? parseInt(dateHoursParts[2]) : 0;

                const madeDate = new Date(year, month - 1, day, hours, minutes, seconds);
                return madeDate.getTime() || 0;
            }
        },
        [name + `-asc`]: function (a, b) {
            return ((a < b) ? -1 : ((a > b) ? 1 : 0));
        },
        [name + `-desc`]: function (a, b) {
            return ((a < b) ? 1 : ((a > b) ? -1 : 0));
        }
    });
}

function initActionOnRow(row) {
    $(row).addClass(`pointer`);
    if ($(row).find(`.action-on-click`).get(0)) {
        $(row)
            .off(`mouseup.wiitable`)
            .on(`mouseup.wiitable`, `td:not(.noVis)`, function (event) {
                const highlightedText = window.getSelection
                    ? window.getSelection().toString()
                    : undefined;

                if (!highlightedText) {
                    const {which} = event || {};
                    let $anchor = $(row).find(`.action-on-click`);
                    const href = $anchor.attr(`href`);
                    if (href) {
                        if($anchor.attr(`target`) === `_blank` || which === 2) {
                            event.stopPropagation();
                            window.open(href, `_blank`);
                        } else if(which === 1) {
                            window.location.href = href;
                        }
                    } else {
                        if (which === 1) {
                            $anchor.trigger(`click`);
                        }
                    }
                }
            });
    }
}

function onToggleInputRadioOnRow() {
    const $modal = $(`#modalColumnVisible`);
    const $checkboxes = $modal.find(`input[type=checkbox]`);

    $checkboxes.closest(`tr`).on(`click`, function () {
        const $checkbox = $(this).find(`input[type=checkbox]`);
        $checkbox.toggleClass(`data`);
        $checkbox.prop(`checked`, !$checkbox.is(`:checked`));
    });
}

function createDatatableDomFooter({information, length, pagination}) {
    return (information || length || pagination)
        ? (
            `<"row mt-2 align-items-center datatable-paging"
                ${length ? `<"col-auto"l>` : ``}
                ${information ? `<"col-auto"i>` : ``}
                ${pagination ? `<"col"p>` : ``}
            >`
        )
        : ``
}

function getAppropriateDom({needsFullDomOverride, needsPartialDomOverride, needsMinimalDomOverride, needsPaginationRemoval, removeInfo, removeLength, removeTableHeader}) {

    const domHeader = removeTableHeader
        ? ``
        : (
            `<"row mb-2"` +
                `<"col-auto d-none"f>` +
            `>`
        );
    const domFooter = createDatatableDomFooter({
        information: !removeInfo,
        length: !removeLength,
        pagination: !needsPaginationRemoval
    });
    let dtDefaultValue = (
        domHeader +
        `t` +
        domFooter +
        `r`
    );
    return needsFullDomOverride
        ? dtDefaultValue
        : needsPartialDomOverride
            ? (
                `r` +
                `t` +
                createDatatableDomFooter({length: true, pagination: true})
            )
            : needsMinimalDomOverride
                ? `tr`
                : dtDefaultValue;
}

function getAppropriateRowCallback({needsColor, classField, color, dataToCheck, needsRowClickAction, callback}) {
    return function (row, data) {
        if (needsColor
            && (data[dataToCheck] === true || data[dataToCheck] && data[dataToCheck].toLowerCase() !== `non`)) {
            $(row).addClass(`table-` + color);
        }
        if (classField && data[classField]) {
            $(row).addClass(data[classField]);
        }
        if (needsRowClickAction) {
            initActionOnRow(row);
        }
        if (callback) {
            callback(row, data);
        }
    }
}

function overrideSearch($input, table, $table) {
    $input
        .off()
        .on(`keyup`, function (e) {
            if (e.key === `Enter`) {
                setPreviousAction($table);
                table.search(this.value.trim()).draw();
            }
        });

    $input.addClass(`form-control`);
}

function datatableDrawCallback({response, callback, table, $table, needsPagingHide, needsSearchHide, hidePaging}) {
    const recordsDisplay = response.fnRecordsDisplay();
    if(needsPagingHide && recordsDisplay !== undefined) {
        $table
            .parents(`.dataTables_wrapper`)
            .find(`.dataTables_paginate, .dataTables_length, .dataTables_info`)
            .parent()
            .toggleClass(`d-none`, recordsDisplay <= 10);
    }

    const recordsTotal = response.fnRecordsTotal();
    if(needsSearchHide && recordsTotal !== undefined) {
        $(`.dataTables_filter`).toggleClass(`d-none`, recordsTotal <= 10);
    }

    if(hidePaging) {
        $table.parents(`.dataTables_wrapper`).find(`.dataTables_paginate`).addClass(`d-none`);
    }

    if (callback) {
        callback();
    }

    renderDtInfo($(table.table().container()));
}

function moveSearchInputToHeader($searchInputContainer) {
    const $datatableCard = $searchInputContainer.parents(`.wii-page-card, .wii-box`);
    const $searchInputContainerCol = $searchInputContainer.parent();

    if ($datatableCard.length > 0) {
        let $datatableCardHeader = $datatableCard.find(`.wii-page-card-header, .wii-box-header`);
        $datatableCardHeader = ($datatableCardHeader.length > 1)
            ? $searchInputContainer.parents(`.dt-parent`).find(`.wii-page-card-header`)
            : $datatableCardHeader;
        if ($datatableCardHeader.length > 0) {
            $datatableCardHeader.prepend($searchInputContainerCol);
            $searchInputContainerCol.removeClass(`d-none`);
        } else {
            $searchInputContainerCol.removeClass(`d-none`);
        }
    } else {
        $searchInputContainerCol.removeClass(`d-none`);
    }
}

function initDataTable($table, options) {
    const domConfig = options.domConfig;
    const rowConfig = options.rowConfig;
    const drawConfig = options.drawConfig;
    const initCompleteCallback = options.initCompleteCallback;

    let config = Object.assign({}, options);
    delete config.domConfig;
    delete config.rowConfig;
    delete config.drawConfig;
    delete config.initCompleteCallback;
    delete config.drawCallback;
    delete config.initComplete;

    $table = typeof $table === `string` ? $(`#` + $table) : $table;
    if($table.data(`initial-visible`)) {
        config.columns = $table.data(`initial-visible`);
        $table
            .removeAttr(`data-initial-visible`)
            .removeData(`initial-visible`);
    }

    let tooltips = [];
    (config.columns || []).forEach((column, id) => {
        if (column.tooltip) {
            tooltips.push({id, text: column.tooltip});
        }

        if(!column.name) {
            column.name = column.data;
        }

        const requiredMark = `<span class="required-mark">*</span>`;
        if (column.required && column.title && !column.title.includes(requiredMark)) {
            column.title += requiredMark;
        }

        if (config.order && Array.isArray(config.order)) {
            const newOrder = [];
            for (let [name, order] of config.order) {
                if (name === column.data || name === column.name) {
                    name = id;
                }

                newOrder.push([name, order]);
            }

            config.order = newOrder;
        }

        if (column.isColumnVisible !== undefined) {
            column.visible = column.isColumnVisible;
        }
    });

    let existingHeaderCallback = config.headerCallback;
    config.headerCallback = (thead) => {
        let $ths = $(thead).find(`th`);

        for (let data of tooltips) {
            $ths.eq(data.id).attr(`title`, data.text)
        }

        if (existingHeaderCallback) {
            return existingHeaderCallback();
        }
    }

    let datatableToReturn = null;
    $table
        .addClass(`wii-table`)
        .addClass(`w-100`);

    const colReorderActivated = config.page
        ? {
            colReorder: {
                enable: !config.disabledRealtimeReorder,
                realtime: false,
            }
        }
        : {};

    const drawCallback = options.drawCallback
        ? options.drawCallback
        : (response) => {
            datatableDrawCallback(Object.assign({
                table: datatableToReturn,
                response,
                $table
            }, drawConfig || {}));
        };

    const initComplete = options.initComplete
        ? options.initComplete
        : () => {
            let $searchInputContainer = $table.parents(`.dataTables_wrapper`).find(`.dataTables_filter`);
            moveSearchInputToHeader($searchInputContainer);
            if (initCompleteCallback) {
                initCompleteCallback();
            }
            attachDropdownToBodyOnDropdownOpening($table);
            if (config.page && config.page !== ``) {
                getAndApplyOrder(config, datatableToReturn);
            } else {
                datatableToReturn.off(`column-reorder`);
            }
        };

    const initial = $table.data(`initial-data`);

    if(initial && typeof initial === `object`) {
        config = {
            ...config,
            data: initial.data,
            deferLoading: [initial.recordsFiltered || 0, initial.recordsTotal || 0],
        };
        $table
            .removeAttr(`data-initial-data`)
            .removeData(`initial-data`);
    }

    datatableToReturn = $table
        .on(`error.dt`, function (e, settings, techNote, message) {
            console.error(`An error has been reported by DataTables: `, message, e, $table.attr(`id`));
        })
        .DataTable(Object.assign({
            fixedColumns: {
                heightMatch: `auto`
            },
            autoWidth: true,
            scrollX: true,
            language: {
                sProcessing: Translation.of(`Général`, ``, `Zone liste`, `Traitement en cours`, false),
                searchPlaceholder: ``,
                sSearch: Translation.of(`Général`, ``, `Zone liste`, `Rechercher : `, false),
                sLengthMenu: Translation.of(`Général`, ``, `Zone liste`, `Afficher {1} éléments`, {1: `_MENU_`}, false),
                sInfo: Translation.of(`Général`, ``, `Zone liste`, `{1} à {2} sur {3}`, {1: `_START_`, 2: `_END_`, 3: `_TOTAL_`}, false),
                sInfoEmpty: Translation.of(`Général`, ``, `Zone liste`, `Aucun élément à afficher`, false),
                sInfoFiltered: Translation.of(`Général`, ``, `Zone liste`, `(filtré de {1} éléments au total)`, {1: `_MAX_`}, false),
                sInfoPostFix: ``,
                sLoadingRecords: Translation.of(`Général`, ``, `Zone liste`, `Chargement en cours`, false),
                sZeroRecords: Translation.of(`Général`, ``, `Zone liste`, `Aucun élément à afficher`, false),
                sEmptyTable: Translation.of(`Général`, ``, `Zone liste`, `Aucune donnée disponible`, false),
                oPaginate: {
                    sFirst: `Premier`,
                    sPrevious: Translation.of(`Général`, ``, `Zone liste`, `Précédent`, false),
                    sNext: Translation.of(`Général`, ``, `Zone liste`, `Suivant`, false),
                    sLast: `Dernier`
                },
                oAria: {
                    sSortAscending: `: activer pour trier la colonne par ordre croissant`,
                    sSortDescending: `: activer pour trier la colonne par ordre d&eacute;croissant`
                }
            },
            dom: getAppropriateDom(domConfig || {}),
            rowCallback: getAppropriateRowCallback(rowConfig || {}),
            drawCallback: (response) => {
                const $searchInput = $table.parents(`.dataTables_wrapper `).find(`.dataTables_filter input[type=search]`);
                overrideSearch($searchInput, datatableToReturn, $table);
                setTimeout(() => {
                    drawCallback(response);
                });

                //remove any ghost tooltip that could be caused by
                //datatable refresh while a tooltip is open
                $(`body > [role=tooltip]`).remove();
            },
            initComplete: () => {
                setTimeout(() => {
                    initComplete();
                });
            },
        }, colReorderActivated, config));

    const $datatableContainer = $(datatableToReturn.table().container());
    $datatableContainer.on(`click`, `th.sorting, th.sorting_asc, th.sorting_desc`, () => {
        setPreviousAction($table, ORDER_ACTION);
    });

    datatableToReturn.on(`preXhr.dt`, function (e, settings, data) {
        const previousAction = $table.data(`previous-action`);
        if (previousAction) {
            data.previousAction = previousAction;

            return data;
        }
    });

    return datatableToReturn;
}

function setPreviousAction($table, type = SEARCH_ACTION) {
    if (type) {
        $table
            .attr(`previous-action`, type)
            .data(`previous-action`, type);
    } else {
        $table
            .removeAttr(`previous-action`)
            .removeData(`previous-action`);
    }
}

function renderDtInfo($table) {
    $table
        .find(`.dataTables_info`)
        .addClass(`pt-0`);
}

function initSearchDate(table, columnName = `date`) {
    $.fn.dataTable.ext.search.push(
        function (settings, data) {
            let dateMin = $(`#dateMin`).val();
            let dateMax = $(`#dateMax`).val();
            let indexDate = table.column(columnName + `:name`).index();

            if (typeof indexDate === `undefined`) return true;

            let dateInit = (data[indexDate]).split(`/`).reverse().join(`-`) || 0;

            return (
                (dateMin === `` && dateMax === ``)
                || (dateMin === `` && moment(dateInit).isSameOrBefore(dateMax))
                || (moment(dateInit).isSameOrAfter(dateMin) && dateMax === ``)
                || (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax)))
        }
    );
}

function attachDropdownToBodyOnDropdownOpening($table) {
    let dropdownMenu;

    $table.on(`show.bs.dropdown`, function (e) {
        const $target = $(e.target);
        dropdownMenu = $target.find(`.dropdown-menu`);
        let parentModal = $target.parents(`.modal`);
        dropdownMenu = $target.find(`.dropdown-menu`);
        $(`body`).append(dropdownMenu.detach());
        dropdownMenu.css(`display`, `block`);
        dropdownMenu.position({
            my: `right top`,
            at: `right bottom`,
            of: $(e.relatedTarget)
        });
        if (parentModal.length > 0) {
            dropdownMenu.css(`z-index`, (parentModal.first().css(`z-index`) || 0) + 1)
        }
    });

    $table.on(`hide.bs.dropdown`, function (e) {
        const $target = $(e.target);
        $target.append(dropdownMenu.detach());
        dropdownMenu.hide();
    });
}

function hash(str, seed = 0) {
    let h1 = 0xdeadbeef ^ seed, h2 = 0x41c6ce57 ^ seed;
    for (let i = 0, ch; i < str.length; i++) {
        ch = str.charCodeAt(i);
        h1 = Math.imul(h1 ^ ch, 2654435761);
        h2 = Math.imul(h2 ^ ch, 1597334677);
    }
    h1 = Math.imul(h1 ^ (h1>>>16), 2246822507) ^ Math.imul(h2 ^ (h2>>>13), 3266489909);
    h2 = Math.imul(h2 ^ (h2>>>16), 2246822507) ^ Math.imul(h1 ^ (h1>>>13), 3266489909);
    return 4294967296 * (2097151 & h2) + (h1>>>0);
}

function getAndApplyOrder(config, datatable) {
    const params = {
        page: config.page,
    };

    datatable.off(`column-reorder`);

    return $.get(Routing.generate(`get_columns_order`), params)
        .then((result) => {
            if (result.order.length > 0) {
                datatable.colReorder.order(result.order);
            }
        })
        .then(() => {
            if (!config.disabledRealtimeReorder) {
                datatable
                    .on(`column-reorder`, function () {
                        params.order = datatable.colReorder.order();
                        $.post(Routing.generate(`set_columns_order`), params).then(() => {
                            showBSAlert( Translation.of(`Général`, ``, `Zone liste`, `Vos préférences d'ordre de colonnes ont bien été enregistrées`), `success`);
                        });
                    });
            }
        });
}
