import {POST} from "@app/ajax";
import Form from "@app/form";

export function initFieldModes() {
    const $modal = $(`#modalFieldModes`);

    if (!$modal.exists()) {
        return;
    }

    const tables = $modal
        .find(`[name=tables]`)
        .val()
        .split(`;`)
        .filter(id => id);

    const planning = $modal
        .find(`[name=planning]`)
        .val();

    const reload = Boolean($modal.find(`[name=reload]`).val());
    const page = $modal.find(`[name=page]`).val();
    const id = $modal.find(`[name=id]`).val();

    $(`[data-target="#modalFieldModes"]`).on(`click`, function() {
        let success;
        if (reload) {
            success = () => {
                window.location.reload();
            };
        } else {
            success = () => {
                $modal.find(`input[type=checkbox]`).each((_, check) => {
                    const $check = $(check);
                    let columnName = $check.closest('tr').data('field-name');
                    tables?.forEach((table) => {
                        const $table = $(`#${table}`);

                        if ($table.exists()) {
                            $table
                                .DataTable()
                                .column(`${columnName}:name`)
                                .visible($check.prop('checked'));
                        }
                    });
                });

                tables?.forEach((table) => {
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
            .clearProcessors()
            .addProcessor((data, errors, $form) => {
                $form.find('tr[data-field-name]').each((index, tr) => {
                    const $ligne = $(tr);
                    let fieldData = [];

                    $ligne.find('input[type=checkbox]').each((index, checkbox) => {
                        const $checkbox = $(checkbox);
                        if ($checkbox.prop('checked')) {
                            fieldData.push($checkbox.data('name'));
                        }
                        data.delete($checkbox.attr('name'));
                    });

                    $ligne.find('input[type=radio]').each((index, radio) => {
                        const $radio = $(radio);
                        if ($radio.prop('checked')) {
                            fieldData.push($radio.val());
                        }
                        data.delete($radio.attr('name'));
                    });

                    data.append($ligne.data('field-name'), fieldData.join(','));
                })
            })
            .submitTo(POST, `field_modes_save`, {
                routeParams: {page, id},
                success: () => {
                    success();
                },
            });
    });
}
