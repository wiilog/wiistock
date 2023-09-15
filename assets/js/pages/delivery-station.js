import '@styles/pages/kiosk.scss';
import '@styles/pages/delivery-station.scss';

let references = [];
const $modalGeneric = $(`.modal-generic`);

$(function () {
    $(`button.login`).on(`click`, function () {
        const mobileLoginKey = $(`[name=mobileLoginKey]`).val();
        const type = $(`[name=type]`).val();
        const visibilityGroup = $(`[name=visibilityGroup]`).val();

        if (mobileLoginKey) {
            wrapLoadingOnActionButton($(this), () => (
                AJAX.route(AJAX.POST, `delivery_station_login`, {mobileLoginKey})
                    .json()
                    .then(({success}) => {
                        if (success) {
                            window.location.href = Routing.generate(`delivery_station_form`, {mobileLoginKey, type, visibilityGroup});
                        } else {
                            toggleRequiredMobileLoginKeyModal();
                        }
                    })
            ));
        } else {
            toggleRequiredMobileLoginKeyModal();
        }
    });

    $('.button-next').on('click', function () {
        const $current = $(this).closest('.stock-exit-container').find('.active');
        const $timeline = $(`.timeline-container`);
        const $currentTimelineEvent = $timeline.find(`.current`);
        const $inputs = $current.find(`input[required]`);
        const $selects = $current.find(`select.needed`);

        $selects.each(function () {
            if ($(this).find('option:selected').length === 0) {
                $(this).parent().find('.select2-selection ').addClass('invalid');
            } else {
                $(this).parent().find('.select2-selection ').removeClass('invalid');
            }
        });

        $inputs.each(function () {
            if (!($(this).val())) {
                $(this).addClass('invalid');
            } else {
                $(this).removeClass('invalid');
            }
        });

        toggleSummaryButtons($current);

        if ($current.find(`.invalid`).length === 0) {
            if ($current.is(`.reference-choice-container`)) {
                //const reference = $(`[name=reference]`).val();
                const reference = $current.find(`[name=reference]`).val();

                if(reference) {
                    wrapLoadingOnActionButton($(this), () => (
                        AJAX.route(AJAX.GET, `delivery_station_get_informations`, {reference})
                            .json()
                            .then(({values}) => {
                                references.push(values);
                                pushNextPage($current);
                                updateTimeline($currentTimelineEvent);
                                updateReferenceInformations();
                            })
                    ));
                } else {
                    showGenericModal(`Vous devez sélectionner une référence pour pouvoir continuer.`);
                }

            }

            if ($current.is(`.quantity-choice-container`)) {
                const barcode = $current.find(`[name=barcode]`).val();
                const pickedQuantity = $current.find(`[name=pickedQuantity]`).val();

                if(barcode && pickedQuantity !== "") {
                    wrapLoadingOnActionButton($(this), () => (
                        AJAX.route(AJAX.GET, `delivery_station_get_free_fields`)
                            .json()
                            .then(({template}) => {
                                $current.find(`.suppliers ~ span`).removeClass(`d-none`); // todo vérifier pourquoi ça ne cache pas le message

                                if(references.length === 1) {
                                    pushNextPage($current);
                                    updateTimeline($currentTimelineEvent);

                                    template = template || `<div class="text-center">Il n'y a aucun champ libre pour ce type de demande de livraison.</div>`;
                                    $(`.other-informations-container`).html(template);
                                } else {
                                    $current
                                        .addClass(`d-none`)
                                        .removeClass(`active`);

                                    $(`.summary-container`)
                                        .removeClass(`d-none`)
                                        .addClass(`active`);
                                }
                            })
                    ));
                } else if(!barcode) {
                    showGenericModal(`Vous devez renseigner un code barre pour continuer.`);
                } else if(!pickedQuantity) {
                    showGenericModal(`Vous devez renseigner une quantité prise pour continuer.`);
                }
            }

            if ($current.is(`.other-informations-container`)) {
                let values = {};
                $current
                    .find(`.free-field`)
                    .each(function (index, freeField) {
                        values[$(freeField).find(`.field-label`).text().trim()] = $(freeField).find(`input, select`).val();
                    });

                pushNextPage($current);
                updateTimeline($currentTimelineEvent);
                updateSummaryTable();
                toggleSummaryButtons($current);

                const $freeFieldsContainer = $(`.summary-container`).find(`.free-fields-container`);
                $freeFieldsContainer.empty();
                for (const [label, value] of Object.entries(values)) {
                    renderFreeField($freeFieldsContainer, label, value);
                }
            }
        }
    });

    $(`.validate-stock-exit-button`).on(`click`, function () {
        const $current = $(this).closest('.stock-exit-container').find('.active');

        wrapLoadingOnActionButton($(this), () => (
            AJAX.route(AJAX.POST, `delivery_station_submit_request`, references)
                .json()
                .then(({success}) => {
                    if (success) {
                        pushNextPage($current);
                        updateTimeline(undefined, true);
                    }
                })
        ));
    });

    $('.return-or-give-up-button, .edit-stock-exit-button').on('click', function () {
        backPreviousPage();
    });

    $(`.give-up-button`).on(`click`, () => {
        // todo rediriger vers le login
        let list = [];
        references.forEach(({reference, location}) => list.push(`<li>${reference} à son emplacement d'origine ${location}</li>`));

        const message = `
            <div>
                Voulez-vous abandonner cette demande de livraison ?<br>
                Si oui, pensez à ranger les références et les articles:
                <ul>
                    ${list}
                </ul>
            </div>
        `;

        showGenericModal(message);
    });

    $(`#submitGiveUpStockExit`).on('click', () => {
        window.location.href = Routing.generate('delivery_station_index', true);
    });

    $(`.quantity-choice-container [name=barcode]`).on(`focusout`, function () {
        const barcode = $(this).val();
        const reference = references.slice(-1)[0]?.id;

        if(barcode !== ``) {
            wrapLoadingOnActionButton($(`body`), () => (
                AJAX.route(AJAX.GET, `delivery_station_get_informations`, {reference, barcode})
                    .json()
                    .then(({success, msg, values}) => {
                        if(success) {
                            const $quantityChoiceContainer = $(`.quantity-choice-container`);
                            const {location, suppliers} = values;

                            const orderedSuppliers = suppliers
                                .split(`,`)
                                .reduce((acc, supplier) => acc + `<li>${supplier}</li>`, ``);

                            $quantityChoiceContainer.find(`.location`).text(location);
                            $quantityChoiceContainer.find(`.suppliers ~ span`).addClass(`d-none`);
                            $quantityChoiceContainer.find(`.suppliers`).html(orderedSuppliers);
                        } else {
                            showGenericModal(msg);
                        }
                    })
            ));
        }
    });

    const $modalDeleteLine = $(`.modal-delete-line`);
    $(document).arrive('.delete-line', function () {
        $(this).on(`click`, function () {
            const lineIndex = $(this).data(`line-index`);
            $modalDeleteLine.find(`.submit`).attr(`data-line-index`, lineIndex);

            if (references.length === 1) {
                $modalDeleteLine.find(`.last-reference`).removeClass(`d-none`);
            }

            $modalDeleteLine.modal(`show`);
        });
    });

    $modalDeleteLine.find(`.submit`).on(`click`, function () {
        const lineIndex = $(this).data(`line-index`);
        references.splice(lineIndex, 1);
        $(`.summary-container tbody tr`)[lineIndex]
            .closest(`tr`)
            .remove();

        if (references.length === 0) {
            window.location.href = Routing.generate('delivery_station_form', true);
        } else {
            Flash.add(Flash.SUCCESS, `La référence a bien été supprimée de la demande`);
        }
    });

    $(`.add-reference-container`).on(`click`, () => {
        Array(3).fill(0).forEach(() => backPreviousPage());
    });

    $modalGeneric.on(`hidden.bs.modal`, function () {
        $(this).find(`.error-message`).empty();
    });
});


function toggleRequiredMobileLoginKeyModal() {
    $(`.modal-required-mobile-login-key`).modal(`show`);
}

function updateTimeline($currentTimelineEvent = undefined, hide = false) {
    $(`.timeline-container`).toggleClass(`d-none`, hide);
    if (!hide) {
        $currentTimelineEvent
            .removeClass(`current`);

        $currentTimelineEvent
            .next()
            .removeClass(`future`)
            .addClass(`current`);
    }
}

function pushNextPage($current) {
    $current
        .addClass(`d-none`)
        .removeClass(`active`);

    $current
        .next()
        .removeClass(`d-none`)
        .addClass(`active`);
}

function backPreviousPage() {
    const $current = $('.active')
    const $timeline = $('.timeline-container');
    const $currentTimelineEvent = $timeline.find('.current');
    const $modalGiveUpStockExit = $(`.modal-give-up-stock-exit`);

    toggleSummaryButtons();

    if ($current.prev().exists() && !$current.prev().first().is(`body`)) {
        $currentTimelineEvent.addClass('future').removeClass('current');
        $($currentTimelineEvent.prev()[0]).addClass('current').removeClass('future');
        $current.removeClass('active').addClass('d-none');
        $($current.prev()[0]).addClass('active').removeClass('d-none');
    } else {
        $modalGiveUpStockExit.modal('show');
        $modalGiveUpStockExit.find('.bookmark-icon').removeClass('d-none');
    }
}

function updateReferenceInformations() {
    const $referenceInformations = $(`.reference-informations`);
    const reference = references.slice(-1)[0];
    for (const [index, value] of Object.entries(reference)) {
        if (index === `image`) {
            if(value) {
                $referenceInformations
                    .find(`.${index}`)
                    .attr(`src`, value)
                    .removeClass(`default-image`);
            } else {
                $referenceInformations
                    .find(`.${index}`)
                    .attr(`src`, ``)
                    .addClass(`default-image`);
            }
        } else {
            $referenceInformations.find(`.${index}`).text(value || '-');
        }
    }

    $(`.quantity-choice-container .location`).text(reference.location);
}

function updateSummaryTable() {
    const $summaryContainer = $(`.summary-container`);
    $summaryContainer.find(`tbody`).empty();
    references.forEach((reference, lineIndex) => {
        renderReferenceLine($summaryContainer, lineIndex, reference);
    });
}

function renderFreeField($freeFieldsContainer, label, value) {
    const $wrapper = `
        <div class="col-4 d-flex align-items-center mt-5">
            <span class="wii-icon wii-icon-document wii-icon-40px-primary mr-2"></span>
            <div class="d-flex flex-column">
                <span class="wii-field-name">${label}</span>
                <span class="wii-field-text">${value || '-'}</span>
            </div>
        </div>
    `;

    $freeFieldsContainer.append($wrapper);
}

function renderReferenceLine($summaryContainer, lineIndex, reference) {
    let line = `
        <tr>
            <td class="wii-body-text">@reference</td>
            <td class="wii-body-text">@label</td>
            <td class="wii-body-text">@barcode</td>
            <td class="wii-body-text">@stockQuantity</td>
            <td class="wii-body-text">
                <i class="wii-icon wii-icon-trash wii-icon-25px-danger delete-line" data-line-index="${lineIndex}"></i>
            </td>
        </tr>
    `;

    for (const [index, value] of Object.entries(reference)) {
        line = line.replace(`@${index}`, value);
    }

    $summaryContainer.find(`tbody`).append(line);
}

function toggleSummaryButtons() {
    const $current = $('.stock-exit-container').find('.active');
    const $stockExitContainer = $(`.stock-exit-container`);
    const $giveUpButtonContainer = $(`.give-up-button-container`);

    if ($current.is(`.summary-container`)) {
        $giveUpButtonContainer.find(`.give-up-button`).removeClass(`d-none`);
        $giveUpButtonContainer.find(`.return-or-give-up-button`).addClass(`d-none`);
        $stockExitContainer.find(`.edit-stock-exit-button`).removeClass(`d-none`);
        $stockExitContainer.find(`.button-next`).addClass(`d-none`);
        $stockExitContainer.find(`.validate-stock-exit-button`).removeClass(`d-none`);
    } else {
        $giveUpButtonContainer.find(`.give-up-button`).addClass(`d-none`);
        $giveUpButtonContainer.find(`.return-or-give-up-button`).removeClass(`d-none`);
        $stockExitContainer.find(`.edit-stock-exit-button`).addClass(`d-none`);
        $stockExitContainer.find(`.button-next`).removeClass(`d-none`);
        $stockExitContainer.find(`.validate-stock-exit-button`).addClass(`d-none`);
    }
}

function showGenericModal(message) {
    $modalGeneric.find(`.error-message`).text(message);
    $modalGeneric.modal(`show`);
}
