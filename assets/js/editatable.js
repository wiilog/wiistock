export const MODE_NO_EDIT = 1;
export const MODE_MANUAL = 2;
export const MODE_DOUBLE_CLICK = 3;
export const MODE_ADD_ONLY = 4;
export const MODE_EDIT = 5;
export const MODE_EDIT_AND_ADD = 6;

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
        const $parent = $element.parent();

        if(config.name) {
            $element.attr(`data-table-processing`, config.name)
        }

        const datatable = new EditableDatatable();
        datatable.element = $element;
        datatable.config = config;
        datatable.mode = config.mode;
        datatable.state = config.mode === MODE_EDIT ? STATE_EDIT : STATE_VIEWING;
        datatable.table = initDataTable(datatable.element, {
            serverSide: false,
            ajax: {
                type: `GET`,
                url: config.route,
                data: data => {
                    data.edit = datatable.state !== STATE_VIEWING;
                },
            },
            rowConfig: {
                needsRowClickAction: true,
            },
            domConfig: {
                removeInfo: true,
            },
            ordering: config.ordering !== undefined ? config.ordering : datatable.state !== STATE_VIEWING,
            paging: config.paginate,
            searching: config.search ?? false,
            scrollY: false,
            scrollX: true,
            drawCallback: () => {
                $parent.find(`.dataTables_wrapper`).css(`overflow-x`, `scroll`);
                $parent.find(`.dataTables_scrollBody, .dataTables_scrollHead`)
                    .css(`overflow`, `visible`)
                    .css(`overflow-y`, `visible`);

                const $rows = $(datatable.table.rows().nodes());
                $rows.each(function() {
                    const $row = $(this);
                    const data = Form.process($row, {
                        ignoreErrors: true,
                    });

                    $row.data(`data`, JSON.stringify(data instanceof FormData ? data.asObject() : data));

                    if ($row.find(`.add-row`).exists()) {
                        $row.addClass('pointer');
                    }
                });

                if(config.mode === MODE_DOUBLE_CLICK) {
                    $rows.off(`dblclick.${id}.startEdit`).on(`dblclick.${id}.startEdit`, function() {
                        if(datatable.state === STATE_VIEWING) {
                            datatable.toggleEdit(STATE_EDIT, true);
                        }
                    });
                }

                if(config.save === SAVE_FOCUS_OUT) {
                    $rows.off(`focusout.${id}.keyboardNavigation`).on(`focusout.${id}.keyboardNavigation`, function(event) {
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
            },
            initComplete: () => {
                let $searchInputContainer = $element.parents('.dataTables_wrapper').find('.dataTables_filter');
                moveSearchInputToHeader($searchInputContainer);

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
                    $tdAction.attr('colspan', $tds.length)
                        .addClass('add-pack-row');
                }
            },
            columnDefs: config.columnDefs,
            columns: config.columns,
        });

        if(datatable.state !== STATE_VIEWING) {
            datatable.toggleEdit(STATE_EDIT);
        }

        $element.on(`click`, `tr`, function() {
            const $row = $(this);
            if(!$row.find(`.add-row`).exists()) {
                return;
            }

            if(datatable.mode === MODE_EDIT_AND_ADD && datatable.state !== STATE_EDIT){
                datatable
                    .toggleEdit(STATE_EDIT, true)
                    .then(() => {
                        createNewForm(datatable);
                    });
            }
            else {
                createNewForm(datatable);
            }
        });

        $element.on(`click`, `.delete-row`, function() {
            const $button = $(this);
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
        })

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
            const result = Form.process($(this));

            if(result) {
                data.push(result.asObject());
            }
        });

        return data;
    }

    addRow() {
        let row = this.config.columns.keymap(column => [column.data, ``]);
        row[Object.keys(row)[0]] = `<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>`;

        if(this.state !== STATE_ADD) {
            this.toggleEdit(STATE_ADD);
            this.table.clear();
        } else {
            this.table.row(':last').remove();
        }

        this.table.row.add(Object.assign({}, this.config.form));
        this.table.row.add(row);
        this.table.draw();
    }

    setURL(url) {
        this.table.ajax.url(url).load();
    }

    toggleEdit(state = this.state === STATE_VIEWING ? STATE_EDIT : STATE_VIEWING, reload = false) {
        this.state = state;

        if(reload) {
            this.table.clear();
            this.table.draw();
            return new Promise((resolve) => {
                this.table.ajax.reload(() => {
                    applyState(this, state);
                    resolve()
                });
            });
        }
        else {
            applyState(this, state);
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

function applyState(datatable, state) {
    const {config, element: $element} = datatable;
    if (state !== STATE_VIEWING) {
        if (config.onEditStart) {
            config.onEditStart();
        }

        $element.closest(`.dataTables_wrapper`).find(`.datatable-paging`).hide();
    } else {
        if (config.onEditStop) {
            config.onEditStop();
        }

        $element.closest(`.dataTables_wrapper`).find(`.datatable-paging`).show();
    }
}

function createNewForm(datatable) {
    const {config, table, element: $element} = datatable;
    const $addRow = $element.find(`.add-row`).closest('tr');
    const row = table.row($addRow);
    const data = row.data();

    row.remove();
    table.row.add(Object.assign({}, config.form));
    table.row.add(data);
    table.draw();
}
