import {POST} from "@app/ajax";

$(function () {
    const $modal = $(`#modalColumnVisible`);

    if (!$modal.exists()) {
        return;
    }

    const tables = $modal
        .find(`[name=tables]`)
        .val()
        .split(`;`)
        .filter(id => id);
    const reload = Boolean($modal.find(`[name=reload]`).val());
    const page = $modal.find(`[name=page]`).val();

    $(`[data-target="#modalColumnVisible"]`).on(`click`, function() {
        if(tables.length === 0) {
            return;
        }

        let success;
        if (reload) {
            success = () => {
                window.location.reload();
            };
        } else {
            success = () => {
                $modal.find(`input[type=checkbox]`).each((_, check) => {
                    const $check = $(check);
                    let columnName = $check.data('name');

                    tables.forEach((table) => {
                        const $table = $(`#${table}`);

                        if ($table.exists()) {
                            $table
                                .DataTable()
                                .column(`${columnName}:name`)
                                .visible($check.hasClass(`data`));
                        }
                    });
                });

                tables.forEach((table) => {
                    const $table = $(`#${table}`);

                    if ($table.exists()) {
                        $table.DataTable().ajax.reload();
                    }
                });
            };
        }

        Form.create($modal)
            .clearOpenListeners()
            .clearSubmitListeners()
            .submitTo(POST, `visible_column_save`, {
                routeParams: {page},
                success: () => {
                    success();
                },
            });
    });
});
