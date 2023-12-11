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
            if ($form.find('.logistic-unit-container [name=logisticUnit]').length === 0) {
                $form.find('.add-logistic-unit').trigger('click');
                errors.push({
                    elements: [$form.find('.logistic-unit-container [name=logisticUnit]')],
                    global: false,
                });
            }
        })
        .addProcessor((data, errors, $form) => {
            if ($form.find('.reception-number-container [name=receptionNumber]').length === 0) {
                $form.find('.add-reception-number').trigger('click');
                errors.push({
                    elements: [$form.find('.reception-number-container [name=receptionNumber]')],
                    global: false,
                });
            }
        })
        .submitTo(AJAX.POST, `receipt_association_form_submit`, {
            tables: [tableReceiptAssociation],
            success: () => $(`#beep`)[0].play(),
        })
        .onOpen(() => {
            $modalNewReceiptAssociation.find('.add-logistic-unit, .add-reception-number').trigger('click');
            $modalNewReceiptAssociation.find(`[name=existingLogisticUnits][data-init=checked]`).prop(`checked`, true).trigger(`change`);;
        })
        .onClose(() => {
            $modalNewReceiptAssociation.find('.delete-line').trigger('click');
        });
});

function initReceiptAssociationModal($modal) {
    $modal.find(`[name=logisticUnit]`).trigger(`focus`);

    const $logisticUnitContainerTemplate = $(`.logistic-unit-container-template`);
    const $receptionNumberContainerTemplate = $(`.reception-number-container-template`);

    $modal
        .find(`[name=existingLogisticUnits]`)
        .on(`change`, function() {
            const existingLogisticUnits = Number($(this).val());

            $modal
                .find(`[name=logisticUnit], .add-logistic-unit`)
                .prop(`disabled`, !existingLogisticUnits)
                .prop(`required`, existingLogisticUnits)
                .toggleClass(`needed`, existingLogisticUnits)
                .closest(`.row`)
                .find(`.required-mark`)
                .toggleClass(`d-none`, !existingLogisticUnits);

            $modal
                .find(existingLogisticUnits ? `[name=logisticUnit]` : `[name=receptionNumber]`)
                .first()
                .trigger(`focus`);
        });

    $modal
        .find(`.add-logistic-unit, .add-reception-number`)
        .on(`click`, function() {
            if($(this).is(`.add-logistic-unit`)) {
                $modal
                    .find(`.logistic-unit-container`)
                    .last()
                    .after($logisticUnitContainerTemplate.html());

                const index = $modal.find(`.logistic-unit-container`).length;
                $modal
                    .find(`.logistic-unit-container`)
                    .last()
                    .data(`multiple-object-index`, index)
                    .attr(`data-multiple-object-index`, index);

                $modal.find(`[name=logisticUnit]`).last().trigger(`focus`);
            } else {
                $modal
                    .find(`.reception-number-container`)
                    .last()
                    .after($receptionNumberContainerTemplate.html());

                const index = $modal.find(`.reception-number-container`).length;
                $modal
                    .find(`.reception-number-container`)
                    .last()
                    .data(`multiple-object-index`, index)
                    .attr(`data-multiple-object-index`, index);
            }
        });

    $(document).on(`click`, `.delete-line`, function () {
        const $parent = $(this).closest(`div`);
        const $previous = $parent.prev();

        $parent.remove();
        $modal
            .find($previous.is(`.logistic-unit-container`) ? `[name=logisticUnit]` : `[name=receptionNumber]`)
            .last()
            .trigger(`focus`);
    });

    $(document).on(`keypress`, `[name=logisticUnit], [name=receptionNumber]`, function (e) {
        if(e.originalEvent.key === `Enter`) {
            const $nextParent = $(this).parent().next();
            const existingLogisticUnits = Number($modal.find(`[name=existingLogisticUnits]`).val());

            if(existingLogisticUnits && $(this).is(`[name=logisticUnit]`)) {
                if($nextParent.exists()) {
                    $nextParent.find(`[name=logisticUnit]`).trigger(`focus`);
                } else {
                    $modal.find(`[name=receptionNumber]`).first().trigger(`focus`);
                }
            } else if(!existingLogisticUnits || $(this).is(`[name=receptionNumber]`)) {
                if($nextParent.exists()) {
                    $nextParent.find(`[name=receptionNumber]`).trigger(`focus`);
                } else {
                    $modal.find(`[type=submit]`).trigger(`click`);
                }
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
        drawConfig: {
            needsSearchOverride: true,
        },
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
            {data: `lastTrackingDate`, name: `lastTrackingDate`, title: Translation.of(`Traçabilité`, `Général`, `Date dernier mouvement`)},
            {data: `lastTrackingLocation`, name: `lastTrackingLocation`, title: Translation.of(`Traçabilité`, `Général`, `Dernier emplacement`)},
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
