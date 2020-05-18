$.fn.dataTable.ext.errMode = () => {
    alert('La requête n\'est pas parvenue au serveur. Veuillez contacter le support si cela se reproduit.');
};

function hideColumns(table, data) {
    data.forEach(function (col) {
        table.column(col + ':name').visible(false);
    })
}

function showColumns(table, data) {
    table.columns().visible(false);
    data.forEach(function (col) {
        table.columns(col + ':name').visible(true);
    })
}

/**
 * Transform milliseconds to 'X h X min' or 'X min' or '< 1 min'
 */
function renderMillisecondsToDelayDatatable(milliseconds, type) {
    let res;

    if (type === 'display') {
        const hours = Math.floor(milliseconds / 1000 / 60 / 60);
        const minutes = Math.floor(milliseconds / 1000 / 60) % 60;
        res = (
                (hours > 0)
                    ? `${hours < 10 ? '0' : ''}${hours} h `
                    : '') +
            ((minutes === 0 && hours < 1)
                ? '< 1 min'
                : `${(hours > 0 && minutes < 10) ? '0' : ''}${minutes} min`)
    } else {
        res = milliseconds;
    }

    return res;
}

function extendsDateSort(name) {
    $.extend($.fn.dataTableExt.oSort, {
        [name + "-pre"]: function (date) {
            const dateSplitted = date.split(' ');
            const dateDaysParts = dateSplitted[0].split('/');
            const year = parseInt(dateDaysParts[2]);
            const month = parseInt(dateDaysParts[1]);
            const day = parseInt(dateDaysParts[0]);

            const dateHoursParts = dateSplitted.length > 1 ? dateSplitted[1].split(':') : [];
            const hours = dateHoursParts.length > 0 ? parseInt(dateHoursParts[0]) : 0;
            const minutes = dateHoursParts.length > 1 ? parseInt(dateHoursParts[1]) : 0;
            const seconds = dateHoursParts.length > 2 ? parseInt(dateHoursParts[2]) : 0;

            const madeDate = new Date(year, month - 1, day, hours, minutes, seconds);
            return madeDate.getTime() || 0;
        },
        [name + "-asc"]: function (a, b) {
            return ((a < b) ? -1 : ((a > b) ? 1 : 0));
        },
        [name + "-desc"]: function (a, b) {
            return ((a < b) ? 1 : ((a > b) ? -1 : 0));
        }
    });
}

function initActionOnRow(row) {
    if ($(row).find('.action-on-click').get(0)) {
        $(row).addClass('pointer');
        $(row).find('td:not(.noVis)').click(function () {
            $(row).find('.action-on-click').get(0).click();
        })
    }
}

function initActionOnCell(cell) {
    $(cell).click(function () {
        $cell.parent('tr').find('.action-on-click').get(0).click();
    });
}


function showOrHideColumn(check, concernedTable, concernedTableColumns) {
    let columnName = check.data('name');

    let column = concernedTable.column(columnName + ':name');
    column.visible(!column.visible());

    concernedTableColumns.find('th, td').removeClass('hide');
    concernedTableColumns.find('th, td').addClass('display');
    check.toggleClass('data');
    initActionOnCell(column);
}

function manageArticleAndRefSearch($input, $printButton) {
    if ($input.val() === '' && $('#filters').find('.filter').length <= 0) {
        if ($printButton.is('button')) {
            $printButton
                .addClass('btn-disabled')
                .removeClass('btn-primary');
            managePrintButtonTooltip(true, $printButton.parent());
        } else {
            $printButton
                .removeClass('pointer')
                .addClass('disabled')
                .addClass('has-tooltip');
            managePrintButtonTooltip(true, $printButton);
        }

        managePrintButtonTooltip(true, $printButton);
    } else {

        if ($printButton.is('button')) {
            $printButton
                .addClass('btn-primary')
                .removeClass('btn-disabled');
            managePrintButtonTooltip(false, $printButton.parent());
        } else {
            $printButton
                .removeClass('disabled')
                .addClass('pointer')
                .removeClass('has-tooltip');
            managePrintButtonTooltip(false, $printButton);
        }
    }
}

function toggleInputRadioOnRow(tr) {
    const $row = $(tr);
    $row.find('input[type="checkbox"]').trigger('click');
}

function createDatatableDomFooter({information, length, pagination}) {
    return (information || length || pagination)
        ? (
            `<"row mt-2 align-items-center"
                ${length ? '<"col-auto"l>' : ''}
                ${information ? '<"col-auto"i>' : ''}
                ${pagination ? '<"col"p>' : ''}
            >`
        )
        : ''
}

function getAppropriateDom({needsFullDomOverride, needsPartialDomOverride, needsMinimalDomOverride, needsPaginationRemoval, removeInfo}) {
    let dtDefaultValue = (
        '<"row mb-2"' +
            '<"col-auto d-none"f>' +
        '>' +
        't' +
        createDatatableDomFooter({information: true, length: true, pagination: !needsPaginationRemoval}) +
        'r'
    );
    let dtDefaultValueWithoutInfos = '<"row mb-2"<"col-auto d-none"f>>t<"row mt-2 justify-content-between"<"col-2 mt-2"l><"col-8"p>>r';
    return needsFullDomOverride
        ? dtDefaultValue
        : needsPartialDomOverride
            ? (
                'r' +
                't' +
                createDatatableDomFooter({length: true, pagination: true})
            )
            : needsMinimalDomOverride
                ? 'tr'
                : removeInfo
                    ? dtDefaultValueWithoutInfos
                    : dtDefaultValue;
}

function getAppropriateRowCallback({needsColor, color, dataToCheck, needsRowClickAction, callback}) {
    return function (row, data) {
        if (needsColor
            && (data[dataToCheck] === true || data[dataToCheck] === 'oui')) {
            $(row).addClass('table-' + color);
        }
        if (needsRowClickAction) {
            initActionOnRow(row);
        }
        if (callback) {
            callback(row, data);
        }
    }
}

function overrideSearch($input, table, callback = null) {
    $input.off();
    $input.on('keyup', function (e) {
        if (e.key === 'Enter') {
            table.search(this.value).draw();
            if (callback) {
                callback($input);
            }
        }
    });
    $input.attr('placeholder', 'entrée pour valider');
}

function datatableDrawCallback({response, needsSearchOverride, needsColumnHide, needsColumnShow, needsResize, needsEmplacementSearchOverride, callback, table, $tableDom}) {
    let $searchInputContainer = $tableDom.parents('.dataTables_wrapper ').find('.dataTables_filter');
    let $searchInput = $searchInputContainer.find('input');

    if (needsSearchOverride && $searchInput.length > 0) {
        overrideSearch($searchInput, table);
    }
    if (needsColumnHide) {
        hideColumns(table, response.json.columnsToHide);
    }
    if (needsColumnShow) {
        showColumns(table, response.json.visible);
    }
    if (needsResize) {
        resizeTable(table);
    }
    if (needsEmplacementSearchOverride) {
        overrideSearchSpecifEmplacement($searchInput);
    }
    if (callback) {
        callback();
    }
    renderDtInfo($(table.table().container()));
}

function moveSearchInputToHeader($searchInputContainer) {
    const $datatableCard = $searchInputContainer.parents('.wii-page-card');
    const $searchInput = $searchInputContainer.find('input');
    const $searchInputContainerCol = $searchInputContainer.parent();
    if ($datatableCard.length > 0) {
        const $datatableCardHeader = $datatableCard.find('.wii-page-card-header');
        if ($datatableCardHeader.length > 0) {
            $searchInput.addClass('search-input');
            $datatableCardHeader.prepend($searchInputContainerCol);
            $searchInputContainerCol.removeClass('d-none');
        } else {
            $searchInputContainerCol.removeClass('d-none');
        }
    } else {
        $searchInputContainerCol.removeClass('d-none');
    }
}

function initDataTable(dtId, {domConfig, rowConfig, drawConfig, initCompleteCallback, isArticleOrRefSpecifConfig, ...config}) {
    let datatableToReturn = null;
    let $tableDom = $('#' + dtId);
    $tableDom
        .addClass('wii-table')
        .addClass('w-100');
    datatableToReturn = $tableDom
        .on('error.dt', function (e, settings, techNote, message) {
            console.log('An error has been reported by DataTables: ', message);
        })
        .DataTable({
            autoWidth: true,
            scrollX: true,
            language: {
                url: "/js/i18n/dataTableLanguage.json",
            },
            dom: getAppropriateDom(domConfig ?? {}),
            rowCallback: getAppropriateRowCallback(rowConfig ?? {}),
            drawCallback: (response) => {
                datatableDrawCallback({
                    table: datatableToReturn,
                    response,
                    $tableDom,
                    ...(drawConfig || {})
                });
            },
            initComplete: () => {
                let $searchInputContainer = $tableDom.parents('.dataTables_wrapper ').find('.dataTables_filter');
                moveSearchInputToHeader($searchInputContainer);
                articleAndRefTableCallback(isArticleOrRefSpecifConfig ?? {}, datatableToReturn);
                if (initCompleteCallback) {
                    initCompleteCallback();
                }
                attachDropdownToBodyOnDropdownOpening($tableDom);
            },
            ...config
        });
    return datatableToReturn;
}

function renderDtInfo($table) {
    let $blocInfo = $table
        .find('.dataTables_info')
        .addClass('pt-0');
    $blocInfo.html(' -  &nbsp;' + $blocInfo.html());
}

function resizeTable(table) {
    table
        .columns.adjust()
        .responsive.recalc();
}

function overrideSearchSpecifEmplacement($input) {
    $input.off();
    $input.on('keyup', function (e) {
        let $printButton = $('.printButton');

        if (e.key === 'Enter') {
            if ($input.val() === '') {
                $printButton
                    .addClass('user-select-none')
                    .addClass('disabled')
                    .addClass('has-tooltip')
                    .removeClass('pointer');
                managePrintButtonTooltip(true, $printButton);
            } else {
                $printButton
                    .removeClass('user-select-none')
                    .removeClass('disabled')
                    .removeClass('has-tooltip')
                    .addClass('pointer');
                managePrintButtonTooltip(false, $printButton);
            }
            tableEmplacement.search(this.value).draw();
        } else if (e.key === 'Backspace' && $input.val() === '') {
            $printButton
                .addClass('user-select-none')
                .addClass('disabled')
                .addClass('has-tooltip')
                .removeClass('pointer');
            managePrintButtonTooltip(true, $printButton);
        }
    });
    $input.attr('placeholder', 'entrée pour valider');
}

function toggleActiveButton($button, table) {
    $button.toggleClass('active');
    $button.toggleClass('not-active');

    let value = $button.hasClass('active') ? 'true' : '';
    table
        .columns('Active:name')
        .search(value)
        .draw();
}

function initSearchDate(table) {
    $.fn.dataTable.ext.search.push(
        function (settings, data) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let indexDate = table.column('date:name').index();

            if (typeof indexDate === "undefined") return true;

            let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;

            if (
                (dateMin === "" && dateMax === "")
                ||
                (dateMin === "" && moment(dateInit).isSameOrBefore(dateMax))
                ||
                (moment(dateInit).isSameOrAfter(dateMin) && dateMax === "")
                ||
                (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))

            ) {
                return true;
            }
            return false;
        }
    );
}


function addToRapidSearch(checkbox) {
    let alreadySearched = [];
    $('#rapidSearch tbody td').each(function () {
        alreadySearched.push($(this).html());
    });
    if (!alreadySearched.includes(checkbox.data('name'))) {
        let tr = '<tr><td>' + checkbox.data('name') + '</td></tr>';
        $('#rapidSearch tbody').append(tr);
    } else {
        $('#rapidSearch tbody tr').each(function () {
            if ($(this).find('td').html() === checkbox.data('name')) {
                if ($('#rapidSearch tbody tr').length > 1) {
                    $(this).remove();
                } else {
                    checkbox.prop("checked", true);
                }
            }
        });
    }
}

function hideAndShowColumns(columns, table) {
    table.columns().every(function (index) {
        this.visible(columns[index].class !== 'hide');
    });
}

function articleAndRefTableCallback({columns, tableFilter}, table) {
    if (columns) {
        hideSpinner($('#spinner'));
        hideAndShowColumns(columns, table);
        overrideSearch($('#' + tableFilter + ' input'), table, function ($input) {
            manageArticleAndRefSearch($input, $('#printTag'));
        });
    }
}

function attachDropdownToBodyOnDropdownOpening($table) {
    let dropdownMenu;

    $table.on('show.bs.dropdown', function (e) {
        const $target = $(e.target);
        dropdownMenu = $target.find('.dropdown-menu');
        let parentModal = $target.parents('.modal');
        dropdownMenu = $target.find('.dropdown-menu');
        $('body').append(dropdownMenu.detach());
        dropdownMenu.css('display', 'block');
        dropdownMenu.position({
            'my': 'right top',
            'at': 'right bottom',
            'of': $(e.relatedTarget)
        });
        if (parentModal.length > 0) {
            dropdownMenu.css('z-index', (parentModal.first().css('z-index') || 0) + 1)
        }
    });

    $table.on('hide.bs.dropdown', function (e) {
        const $target = $(e.target);
        $target.append(dropdownMenu.detach());
        dropdownMenu.hide();
    });
}

