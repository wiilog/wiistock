export const EDIT_NEVER = 1;
export const EDIT_DOUBLE_CLICK = 2;
export const EDIT_ALWAYS = 3;

export const SAVE_FOCUS_OUT = 1;
export const SAVE_MANUALLY = 2;

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
        datatable.editable = config.edit === EDIT_ALWAYS;
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
            paging: false,
            searching: false,
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

                if(config.edit === EDIT_DOUBLE_CLICK) {
                    $rows.off(`dblclick.${id}.startEdit`).on(`dblclick.${id}.startEdit`, function() {
                        if(!datatable.editable) {
                            datatable.editable = true;
                            datatable.table.ajax.reload();

                            config.onEditStart();
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

                if(config.editable) {
                    scrollToBottom();
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
            config.onEditStart();
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
