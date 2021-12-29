export const MODE_NO_EDIT = 1;
export const MODE_DOUBLE_CLICK = 2;
export const MODE_ADD_ONLY = 3;
export const MODE_EDIT = 4;

export const SAVE_FOCUS_OUT = 1;
export const SAVE_MANUALLY = 2;

const STATE_VIEWING = 1;
const STATE_EDIT = 2;
const STATE_ADD = 2;

const datatables = {};

export default class EditableDatatable {

    static of(id) {
        return datatables[`#${typeof id === `string` ? id : $(id).attr(`id`)}`];
    }

    static create(id, config) {
        const $element = $(id);
        const $parent = $element.parent();

        const datatable = new EditableDatatable();
        datatable.element = $element;
        datatable.config = config;
        datatable.state = config.edit === MODE_EDIT ? STATE_EDIT : STATE_VIEWING;
        datatable.editable = config.edit === MODE_EDIT;
        datatable.table = initDataTable(datatable.element, {
            serverSide: false,
            ajax: {
                type: `GET`,
                url: config.route,
                data: data => {
                    data.edit = datatable.editable;
                },
            },
            rowConfig: {
                needsRowClickAction: true,
            },
            domConfig: {
                removeInfo: true,
            },
            ordering: datatable.editable,
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
                });

                if(config.edit === MODE_DOUBLE_CLICK) {
                    $rows.off(`dblclick.${id}.startEdit`).on(`dblclick.${id}.startEdit`, function() {
                        if(!datatable.editable) {
                            datatable.editable = true;
                            datatable.switchEdit(true);

                            datatable.table.ajax.reload();
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

                        config.onEditStop();
                        datatable.save($row);
                    });
                }
            },
            initComplete: () => {
                console.log($element, $element.parents('.dataTables_wrapper').find('.dataTables_filter'));
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

        if(datatable.editable) {
            datatable.switchEdit(true);
        }

        if(config.edit === MODE_ADD_ONLY) {
            console.log('XD', $element);
            $element.on(`click`, `tr`, function() {
                const $row = $(this);
                if(!$row.find(`.add-row`).exists()) {
                    return;
                }

                const row = datatable.table.row($row);
                const data = row.data();

                row.remove();
                datatable.table.row.add(Object.assign({}, config.form));
                datatable.table.row.add(data);
                datatable.table.draw();
            });
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
            const result = Form.process($(this), {
                ignoreErrors: true,
            });

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
            this.state = STATE_ADD;

            this.switchEdit(true);
            this.table.clear();
        } else {
            this.table.row(':last').remove();
        }

        this.table.row.add(Object.assign({}, this.config.form));
        this.table.row.add(row);
        this.table.draw();
    }

    switchEdit(edit) {
        if(edit) {
            if(this.config.onEditStart) {
                this.config.onEditStart();
            }

            this.element.closest(`.dataTables_wrapper`).find(`.datatable-paging`).hide();
        } else {
            if(this.config.onEditStop) {
                this.config.onEditStop();
            }

            this.element.closest(`.dataTables_wrapper`).find(`.datatable-paging`).show();
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
