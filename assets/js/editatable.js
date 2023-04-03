import WysiwygManager from "./wysiwyg-manager";
import {forEach} from "core-js/stable/dom-collections";

export const MODE_NO_EDIT = 1;
export const MODE_CLICK_EDIT = 2;
export const MODE_ADD_ONLY = 3;
export const MODE_EDIT = 4;
export const MODE_CLICK_EDIT_AND_ADD = 5;

export const SAVE_FOCUS_OUT = 1;
export const SAVE_MANUALLY = 2;

export const STATE_VIEWING = 1;
export const STATE_EDIT = 2;
export const STATE_ADD = 3;

const datatables = {};

export default class EditableDatatable {

    static of(id) {
        return datatables[`#${typeof id === `string` ? id : $(id).attr(`id`)}`];
    }

    static create(id, config) {
        const $element = $(id);

        $element.closest(`.wii-box`).arrive(`.wii-one-line-wysiwyg`, function() {
            WysiwygManager.initializeOneLineWYSIWYG($(document));
        });

        if(config.name) {
            $element.attr(`data-table-processing`, config.name)
        }

        for(const column of config.columns) {
            if(column.required) {
                column.title += `<span class="d-none required-mark">*</span>`;
            }
        }

        const datatable = new EditableDatatable();
        datatable.element = $element;
        datatable.config = config;
        datatable.mode = config.mode;
        datatable.state = config.mode === MODE_EDIT ? STATE_EDIT : STATE_VIEWING;
        datatable.table = initEditatable(datatable);

        if(datatable.state !== STATE_VIEWING) {
            datatable.toggleEdit(STATE_EDIT);
        }

        $(window).on(`beforeunload.${id}`, () => {
            const $focus = $(`tr :focus`);
            if($focus.exists()) {
                if(datatable.save($focus.closest(`tr`), false)) {
                    return true;
                }
            }
        });

        datatables[id] = datatable;
        return datatable;
    }

    data() {
        const data = [];

        $(this.table.rows().nodes()).each(function() {
            const $row = $(this);
            if($row.find(`.add-row`).exists()) {
                return;
            }
            const result = Form.create($row).process();
            data.push(result instanceof FormData ? result.asObject() : result);
        });

        if(this.config.minimumRows > data.length) {
            throw `Minimum de ${this.config.minimumRows} ligne(s) nécessaire pour enregistrer`;
        }

        return data;
    }

    addRow(clear = false) {
        let row = this.config.columns.keymap(column => [column.data, ``]);
        row[Object.keys(row)[0]] = `<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>`;

        const drawNewRow = () => {
            const form = {};
            for(const [key, value] of Object.entries(this.config.form)) {
                form[key] = typeof value === `function` ? value() : value;
            }

            this.table.row.add(form);
            this.table.row.add(row);
            this.table.draw();

            const $beforeLastTableLine = $(`.add-row`).parents(`tr:first`).prev();
            const $focusableInput = $beforeLastTableLine.find(`input:first`);
            $focusableInput.focus();
        }

        if(clear && this.state !== STATE_ADD) {
            this.toggleEdit(STATE_ADD, true).then(() => {
                this.table.clear();
                drawNewRow();
            });
        } else {
            this.table.row(':last').remove();
            drawNewRow();
        }
    }

    setURL(url, load = true) {
        const ajaxUrl = this.table.ajax.url(url);
        if (load) {
            ajaxUrl.load();
        }
    }

    toggleEdit(state = this.state === STATE_VIEWING ? STATE_EDIT : STATE_VIEWING, reload = false, {params, rowIndex} = {}) {
        this.state = state;

        if(reload) {
            return new Promise((resolve) => {
                this.table = initEditatable(this, () => {
                    applyState(this, state, params, rowIndex);
                    resolve();
                });
            });
        }
        else {
            applyState(this, state, params, rowIndex);
            return new Promise((resolve) => resolve());
        }
    }

    save($row, async = true) {
        let data = Form.process($row);
        data = data instanceof FormData ? data.asObject() : data;

        if(data) {
            if(!jQuery.deepEquals(data, JSON.parse($row.data(`data`)))) {
                this.config.onSave(data, $row, async);
                return true;
            }
        } else {
            $row.find('.is-invalid').first().trigger('focus');
            return true;
        }

        return false;
    }
}

function applyState(datatable, state, params, rowIndex) {
    const {config, element: $element} = datatable;
    const $datatableWrapper = $element.closest(`.dataTables_wrapper`);
    const $datatablePaging = $datatableWrapper.find(`.datatable-paging`);
    const $requiredMarks = $datatableWrapper.find('.required-mark');

    $element.data('needs-processing', state !== STATE_VIEWING);

    if (state !== STATE_VIEWING) {
        $requiredMarks.removeClass('d-none');
        $datatablePaging.addClass('d-none');
        $datatableWrapper.addClass(`current-editing`);

        if (config.onEditStart) {
            config.onEditStart();
        }

        if (rowIndex !== undefined) {
            $datatableWrapper
                .find(`.subentities-table tbody tr`)
                .eq(rowIndex)
                .find(`input:not([type=checkbox], [type=hidden]):first`)
                .focus();
        }
    } else {
        $requiredMarks.addClass('d-none');
        $datatablePaging.removeClass('d-none');
        $datatableWrapper.removeClass(`current-editing`);

        if (config.onEditStop) {
            config.onEditStop(params);
        }
    }

}

function initEditatable(datatable, onDatatableInit = null) {
    const {config, state, element: $element} = datatable;
    const id = $element.attr('id');
    const $parent = $element.parent();

    let url;
    if ($.fn.DataTable.isDataTable($element)) {
        const datatable = $element.DataTable();
        url = datatable.ajax.url();
        datatable.clear().destroy();

        $element.closest(`.wii-box`)
            .find(`.dataTables_filter`)
            .closest(`.col-auto`)
            .remove();
    }
    else {
        url = config.route;
    }
    let ajax = url
        ? {
            type: `GET`,
            url,
            data: (data) => {
                data.edit = state !== STATE_VIEWING;
            },
        }
        : null;

    return initDataTable($element, {
        serverSide: false,
        ajax,
        data: generateDefaultData(ajax , config.columns),
        rowConfig: {
            needsRowClickAction: true,
        },
        domConfig: {
            removeInfo: true,
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        // replace undefined by false
        ordering: config.ordering && datatable.state === STATE_VIEWING || false,
        paging: config.paging && datatable.state === STATE_VIEWING || false,
        searching: config.search && datatable.state === STATE_VIEWING || false,
        scrollY: false,
        scrollX: true,
        autoWidth: false,
        drawCallback: () => {
            if (!datatable.table) {
                return;
            }

            const $parent = datatable.element.closest(`.wii-box`);

            $parent.find(`.dataTables_wrapper`)
                .css(`overflow-x`, `auto`);

            $parent.find(`.dataTables_scrollBody, .dataTables_scrollHead`)
                .css('overflow', `visible`)
                .css('overflow-y', 'visible')
                .css('position', 'relative');

            const $rows = $(datatable.table.rows().nodes());
            $rows.each(function() {
                const $row = $(this);
                const data = Form.process($row, {
                    ignoreErrors: true,
                });

                $row.data(`data`, JSON.stringify(data instanceof FormData ? data.asObject() : data));

                if ($row.find(`.add-row`).exists()) {
                    $row
                        .off(`click.${id}.addRow`)
                        .on(`click.${id}.addRow`, 'td', () => {
                            onAddRowClicked(datatable);
                            config.onAddRow && config.onAddRow(datatable);
                        });
                }

                $row
                    .off(`click.${id}.deleteRow`)
                    .on(`click.${id}.deleteRow`, `.delete-row`, function(event) {
                        onDeleteRowClicked(datatable, event, $(this));
                        config.onDeleteRow && config.onDeleteRow(datatable, event, $(this));
                    });
            });

            if(config.mode === MODE_CLICK_EDIT || config.mode === MODE_CLICK_EDIT_AND_ADD) {
                $rows
                    .off(`click.${id}.startEdit`)
                    .on(`click.${id}.startEdit`, 'td:not(.no-interaction)', function(event) {
                        if(event.handled) {
                            return;
                        }

                        if(datatable.state === STATE_VIEWING) {
                            const $row = $(this).parent();
                            const rowIndex = $rows.index($row);
                            datatable.toggleEdit(STATE_EDIT, true, {rowIndex});
                        }
                    });
            }

            if(config.save === SAVE_FOCUS_OUT) {
                $rows
                    .off(`focusout.${id}.keyboardNavigation`)
                    .on(`focusout.${id}.keyboardNavigation`, function(event) {
                        const $row = $(this);
                        const $target = $(event.target);
                        const $relatedTarget = $(event.relatedTarget);

                        //TODO: refacto ça lo
                        const wasPackSelect = $target.closest(`td`).find(`select[name="pack"]`).exists();
                        if((event.relatedTarget && $.contains(this, event.relatedTarget))
                            || $relatedTarget.is(`button.delete-pack-row`)
                            || wasPackSelect) {
                            return;
                        }

                        config.state = STATE_VIEWING;
                        config.onEditStop();
                        datatable.save($row);
                    });
            }
            else if(config.save === SAVE_MANUALLY) {
                $rows.addClass(`focus-free`);
            }

            // add .form-control on search input
            datatable.element
                .parents('.dataTables_wrapper ')
                .find('.dataTables_filter input')
                .addClass('form-control');

            const data = datatable.table.rows().count();
            setTimeout(() => $parent
                .find(`.datatable-paging, .dataTables_filter`)
                .toggleClass(`d-none`, data <= 10), 0)

        },
        initComplete: () => {
            let $searchInputContainer = $element.parents('.dataTables_wrapper').find('.dataTables_filter');
            moveSearchInputToHeader($searchInputContainer);

            if (onDatatableInit) {
                onDatatableInit();
            }

            if(config.onInit) {
                config.onInit();
            }
        },
        createdRow: (row, data) => {
            // we display only + td on this line
            if(data && data.createRow) {
                const $row = $(row);
                const $tds = $row.children();
                const $tdAction = $tds.first();
                const $tdOther = $tds.slice(1);

                $tdOther.addClass('d-none');
                $tdAction
                    .attr('colspan', $tds.length)
                    .addClass('add-pack-row');
            }
        },
        columnDefs: config.columnDefs,
        columns: config.columns,
    });
}

function onAddRowClicked(datatable) {
    if(datatable.mode === MODE_CLICK_EDIT_AND_ADD && datatable.state !== STATE_EDIT){
        datatable
            .toggleEdit(STATE_EDIT, true)
            .then(() => {
                datatable.addRow();
            });
    }
    else {
        datatable.addRow();
    }
}

function onDeleteRowClicked(datatable, event, $button) {
    const {config} = datatable;
    //don't send it up to the table so it doesn't toggle edit mode
    event.stopPropagation();

    const $row = $button.closest(`tr`);

    function deleteRow() {
        datatable.table.row($row).remove();
        datatable.table.draw();
    }

    if($button.is(`[data-id]`) && config.deleteRoute) {
        AJAX.route(`POST`, config.deleteRoute, {entity: $button.data(`id`)})
            .json()
            .then(result => result.success ? deleteRow() : null);
    } else {
        deleteRow();
    }
}

function generateDefaultData(ajax, columns) {
    if (!ajax) {
        let row = {};
        columns.forEach(column => {
            row[column.data] = '';
        });
        row["actions"] = `<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>`;
        return [row];
    } else {
        return null;
    }
}
