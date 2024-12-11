import AJAX from "@app/ajax";
import Form from "@app/form";
import Routing from '@app/fos-routing';
import {initDataTable} from "@app/datatable";

$(function () {
    $(`.select2`).select2();
    const tableReceiptAssociation = initDatatable();

    const $userFormat = $(`#userDateFormat`);
    const format = $userFormat.val() ? $userFormat.val() : `d/m/Y`;

    initDateTimePicker(`#dateMin, #dateMax`, DATE_FORMATS_TO_DISPLAY[format]);
    initModals(tableReceiptAssociation);

    let path = Routing.generate(`filter_get_by_page`);
    let params = JSON.stringify(PAGE_RECEIPT_ASSOCIATION);
    $.post(path, params, function (data) {
        displayFiltersSup(data, true);
    }, `json`);

    const $modalNewReceiptAssociation = $(`#modalNewReceiptAssociation`);
    initReceiptAssociationModal($modalNewReceiptAssociation)
    Form.create($modalNewReceiptAssociation)
        .addProcessor((data, errors, $form) => {
            if ($form.find('[name=logisticUnit]').length === 0) {
                $form.find('.add-logistic-unit').trigger('click');
                errors.push({
                    elements: [$form.find('.logistic-unit-wrapper [name=logisticUnit]')],
                    global: false,
                });
            }
        })
        .addProcessor((data, errors, $form) => {
            if ($form.find('[name=receptionNumber]').length === 0) {
                $form.find('.add-reception-number').trigger('click');
                errors.push({
                    elements: [$form.find('.reception-number-container [name=receptionNumber]')],
                    global: false,
                });
            }
        })
        .addProcessor((data, errors, $form) => {
            // find if there is duplicate [name=receptionNumber] values
            const $receptionNumbers = $form.find('[name=receptionNumber]');
            const values = $receptionNumbers.map((index, element) => $(element).val()).toArray();

            const duplicateValues = values.filter((value, index) => values.indexOf(value) !== index);
            if (duplicateValues.length > 0) {
                const $errorElement = $receptionNumbers.filter((index, element) => {
                    const $element = $(element);
                    return duplicateValues.includes($element.val());
                });

                errors.push({
                    elements: [$errorElement],
                    global: false,
                    message: "Les numéros doivent être uniques",
                });
            }
        })
        .submitTo(AJAX.POST, `receipt_association_form_submit`, {
            tables: [tableReceiptAssociation],
            success: function () {
                $(`#beep`)[0].play();
                setTimeout(function () {
                    $modalNewReceiptAssociation.modal('show');
                }, 500);
            },
        })
        .onOpen(() => {
            $modalNewReceiptAssociation.find('.add-logistic-unit, .add-reception-number').trigger('click');
            $modalNewReceiptAssociation.find(`[name=existingLogisticUnits][data-init=checked]`).prop(`checked`, true).trigger(`change`);
        })
        .onClose(() => {
            $modalNewReceiptAssociation.find('.delete-line').trigger('click');
        });
});

function initReceiptAssociationModal($modal) {
    $modal.find(`[name=logisticUnit]`).trigger(`focus`);

    $modal
        .find(`[name=existingLogisticUnits]`)
        .on(`change`, function() {
            const existingLogisticUnits = Boolean(Number($(this).val()));

            const $logisticUnitField = $modal.find(`[name=logisticUnit]`);
            const $logisticUnitsContainer = $modal.find(`.logistic-units-container`);
            $logisticUnitField
                .prop(`required`, existingLogisticUnits)
                .toggleClass(`needed`, existingLogisticUnits)
                .toggleClass(`data`, existingLogisticUnits);

            $logisticUnitsContainer
                .toggleClass(`d-none`, !existingLogisticUnits);

            $modal
                .find(existingLogisticUnits ? `[name=logisticUnit]` : `[name=receptionNumber]`)
                .first()
                .trigger(`focus`);
        });

    $modal
        .find(`.add-logistic-unit, .add-reception-number`)
        .on(`click`, function() {
            const $button = $(this);
            const $fieldContainer = $modal.find($button.data('container-selector'));
            const $template = $modal.find($button.data('template-selector'));

            const $newElement = $($template.html());
            $fieldContainer.append($newElement);

            resetDataMultipleKey($fieldContainer);
            $newElement.trigger('focus');
        });

    $(document).on(`click`, `.delete-line`, function () {
        const $button = $(this);
        const $field = $button.closest($button.data('field-selector'));
        const $container = $field.parent();

        $field.remove();
        resetDataMultipleKey($container);
    });

    $(document).on(`keypress`, `[name=logisticUnit], [name=receptionNumber]`, function (e) {
        if(e.originalEvent.key === `Enter`) {
            const $input = $(this);
            const $fields = $modal.find(`[name=logisticUnit], [name=receptionNumber]`);
            let indexToFocus;

            $fields.each((index, current) => {
                const $current = $(current);
                if ($current.is($input)) {
                    indexToFocus = index + 1;
                    return false; // break each
                }
            });

            if (indexToFocus !== undefined
                && indexToFocus < $fields.length) {
                const $inputToFocus = $($fields.get(indexToFocus));
                $inputToFocus.trigger('focus');
            }
            else { // the last field
                $modal.find(`[type=submit]`).trigger(`click`);
            }
        }
    });
}

function initDatatable() {
    let pathReceiptAssociation = Routing.generate(`receipt_association_api`, true);
    let tableReceiptAssociationConfig = {
        serverSide: true,
        processing: true,
        order: [[1, `desc`]],
        rowConfig: {
            needsRowClickAction: true
        },
        ajax: {
            url: pathReceiptAssociation,
            type: AJAX.POST,
        },
        columns: [
            {data: `Actions`, name: `Actions`, title: ``, className: `noVis`, orderable: false},
            {data: `creationDate`, name: `creationDate`, title: Translation.of(`Traçabilité`, `Général`, `Date`)},
            {data: `logisticUnit`, name: `logisticUnit`, title: Translation.of(`Traçabilité`, `Général`, `Unité logistique`)},
            {data: `lastActionDate`, name: `lastActionDate`, title: Translation.of(`Traçabilité`, `Général`, `Date dernier mouvement`)},
            {data: `lastActionLocation`, name: `lastActionLocation`, title: Translation.of(`Traçabilité`, `Général`, `Dernier emplacement`)},
            {data: `receptionNumber`, name: `receptionNumber`, title: Translation.of(`Traçabilité`, `Association BR`, `Réception`)},
            {data: `user`, name: `user`, title: Translation.of(`Traçabilité`, `Général`, `Utilisateur`)},
        ],
    };
    return initDataTable(`receiptAssociationTable`, tableReceiptAssociationConfig)
}

function initModals(tableReceiptAssociation) {
    let modalDeleteReceiptAssociation = $(`#modalDeleteReceiptAssociation`);
    let submitDeleteReceiptAssociation = $(`#submitDeleteReceiptAssociation`);
    let urlDeleteReceiptAssociation = Routing.generate(`receipt_association_delete`, true);
    InitModal(modalDeleteReceiptAssociation, submitDeleteReceiptAssociation, urlDeleteReceiptAssociation, {tables: [tableReceiptAssociation]});
}

function resetDataMultipleKey($container) {
    $container.find('[data-multiple-key]')
        .each((index, input) => {
            $(input)
                .data(`multiple-object-index`, index)
                .attr(`data-multiple-object-index`, index)
        });
}
