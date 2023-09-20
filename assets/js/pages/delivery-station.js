import '@styles/pages/kiosk.scss';
import '@styles/pages/delivery-station.scss';

const REDIRECT_TO_HOME_DELAY = 15000;

let references = [];
let freeFields = [];
let referenceValues = {};

const $timeline = $(`.timeline-container`);

const $homeContainer = $(`.home`);
const $stockExitContainer = $(`.stock-exit-container`);
const $referenceChoiceContainer = $(`.reference-choice-container`);
const $quantityChoiceContainer = $(`.quantity-choice-container`);
const $summaryContainer = $(`.summary-container`);
const $addReferenceContainer = $(`.add-reference-container`);
const $otherInformationsContainer = $(`.other-informations-container`);
const $treatedDeliveryContainer = $(`.treated-delivery-container`);

const $loginButton = $(`button.login`);
const $validateStockEntryButton = $(`.validate-stock-exit-button`);
const $submitGiveUpStockExit = $(`#submitGiveUpStockExit`);
const $giveUpButtonContainer = $(`.give-up-button-container`);
const $giveUpButton = $(`.give-up-button`);
const $informationButton = $(`#information-button`);
const $nextButton = $(`.next-button`);
const $searchButton = $(`.button-search`);
const $backButton = $(`.back-button`);
const $backToHomeButton = $(`.back-to-home-button`);
const $editStockExitButton = $(`.edit-stock-exit-button`);

const $modalGeneric = $(`.modal-generic`);
const $modalInformation = $(`#modal-information`);
const $modalDeleteLine = $(`.modal-delete-line`);

const $referenceInformations = $(`.reference-informations`);

$(function () {
    toggleAutofocus();
    $treatedDeliveryContainer.find(`.delay`).text(REDIRECT_TO_HOME_DELAY / 1000);

    $loginButton.on(`click`, function () {
        processLogin($(this));
    });

    // catching DataWedge barcode event
    $(document).on(`keypress`, (event) => {
        if (event.originalEvent.key === 'Enter') {
            const $target = $(event.target);
            if ($target.is(`.login`)) {
                processLogin();
            } else if ($target.is(`[name=barcode]`)) {
                processBarcodeEntering($target.val());
            }
        }
    });

    $nextButton
        .add($searchButton)
        .on(`click`, function () {
            const $current = $(this).closest(`.stock-exit-container`).find(`.active`);
            const $currentTimelineEvent = $timeline.find(`.current`);

            if ($current.find(`.invalid`).length === 0) {
                if ($current.is(`.reference-choice-container`)) {
                    processReferenceChoice($current, $searchButton, $currentTimelineEvent);
                }

                if ($current.is(`.quantity-choice-container`)) {
                    const barcode = $current.find(`[name=barcode]`).val();
                    const pickedQuantity = $current.find(`[name=pickedQuantity]`).val();

                    if (barcode && pickedQuantity !== "") {
                        if(references.findIndex(({barcode}) => barcode === barcode) !== -1) {
                            showGenericModal(`Le code barre <strong>${barcode}</strong> est déjà présent dans cette demande de livraison.`)
                        } else {
                            references.push(referenceValues);
                            const index = references.length - 1;
                            references[index].barcode = barcode;
                            references[index].pickedQuantity = pickedQuantity;

                            wrapLoadingOnActionButton($(this), () => (
                                AJAX.route(AJAX.GET, `delivery_station_get_free_fields`)
                                    .json()
                                    .then(({template}) => {
                                        if (references.length === 1) {
                                            pushNextPage($current);
                                            updateTimeline($currentTimelineEvent);

                                            template = template || `<div class="text-center">Il n'y a aucun champ libre pour ce type de demande de livraison.</div>`;
                                            $otherInformationsContainer.html(template);

                                            toggleAutofocus();
                                        } else {
                                            $current
                                                .addClass(`d-none`)
                                                .removeClass(`active`);

                                            $summaryContainer
                                                .removeClass(`d-none`)
                                                .addClass(`active`);

                                            updateSummaryTable();
                                        }
                                    })
                            ));
                        }
                    } else if (!barcode) {
                        showGenericModal(`Vous devez renseigner un code barre pour continuer.`);
                    } else if (!pickedQuantity) {
                        showGenericModal(`Vous devez renseigner une quantité prise pour continuer.`);
                    }
                }

                if ($current.is(`.other-informations-container`)) {
                    const $freeFields = $current.find(`.free-field`);
                    let values = {};
                    $freeFields
                        .each(function (index, element) {
                            const $element = $(element);

                            const label = $element.find(`.field-label`).text().trim();
                            const value = $element.find(`input, select`).val();
                            values[label] = value;

                            let freeField = {};
                            const id = $element.find(`input, select`).attr(`name`);
                            freeField[id] = value;
                            freeFields.push(freeField);
                        });

                    $current
                        .find(`.free-field`)
                        .each(function (index, freeField) {
                            values[$(freeField).find(`.field-label`).text().trim()] = $(freeField).find(`input, select`).val();
                        });

                    console.log(freeFields);

                    pushNextPage($current);
                    updateTimeline($currentTimelineEvent);
                    updateSummaryTable();
                    toggleSummaryButtons($current);

                    const $freeFieldsContainer = $summaryContainer.find(`.free-fields-container`);
                    $freeFieldsContainer.empty();
                    for (const [label, value] of Object.entries(values)) {
                        renderFreeField($freeFieldsContainer, label, value);
                    }
                }
            }
        });

    $validateStockEntryButton.on(`click`, function () {
        const $current = $(this).closest(`.stock-exit-container`).find(`.active`);

        const parsedReferences = JSON.stringify(references);
        const parsedFreeFields = JSON.stringify(freeFields);
        wrapLoadingOnActionButton($(this), () => (
            AJAX.route(AJAX.POST, `delivery_station_submit_request`, {references: parsedReferences, freeFields: parsedFreeFields})
                .json()
                .then(({success, msg}) => {
                    if (success) {
                        pushNextPage($current);
                        updateTimeline(undefined, true);
                        toggleSummaryButtons($current);

                        $nextButton.addClass(`d-none`);
                        $backButton.addClass(`d-none`);

                        setTimeout(() => backToHome(), REDIRECT_TO_HOME_DELAY);
                    } else {
                        showGenericModal(msg);
                    }
                })
        ));
    });

    $(`.back-button, .edit-stock-exit-button`).on(`click`, function () {
        backPreviousPage();
    });

    $giveUpButton.on(`click`, () => {
        let list = [];
        references.forEach(({reference, location}) => list.push(`<li><strong>${reference}</strong> à son emplacement d'origine <strong>${location}</strong></li>`));

        const message = `
            <div>
                Voulez-vous abandonner cette demande de livraison ?<br>
                Si oui, pensez à ranger les références et les articles:
                <ul>
                    ${list}
                </ul>
            </div>
        `;

        $modalGeneric.find(`.submit`).removeClass(`d-none`);

        showGenericModal(message);
    });

    $submitGiveUpStockExit
        .add($modalGeneric.find(`.submit`))
        .add($backToHomeButton)
        .on(`click`, () => backToHome());

    $quantityChoiceContainer.find(`[name=barcode]`).on(`focusout`, function () {
        const barcode = $(this).val();

        if (barcode !== ``) {
            processBarcodeEntering(barcode);
        }
    });

    $(document).arrive(`.delete-line`, function () {
        $(this).on(`click`, function () {
            const lineIndex = $(this).data(`line-index`);
            $modalDeleteLine.find(`.submit`).attr(`data-line-index`, lineIndex);

            const {reference, location} = references[lineIndex];
            const message = `Si oui, pensez à remettre la référence <strong>${reference}</strong> à son emplacement d'origine <strong>${location}.</strong>`;
            $modalDeleteLine.find(`.reminder`).html(message);

            if (references.length === 1) {
                $modalDeleteLine.find(`.last-reference`).removeClass(`d-none`);
            }

            $modalDeleteLine.modal(`show`);
        });
    });

    $modalDeleteLine.find(`.submit`).on(`click`, function () {
        const lineIndex = $(this).data(`line-index`);
        references.splice(lineIndex, 1);
        $summaryContainer
            .find(`tbody tr`)[lineIndex]
            .closest(`tr`)
            .remove();

        if (references.length === 0) {
            window.location.href = Routing.generate(`delivery_station_login`, true);
        }
    });

    $addReferenceContainer.on(`click`, () => {
        Array(3).fill(0).forEach(() => backPreviousPage());
        $quantityChoiceContainer.find(`.suppliers li`).remove();
        $quantityChoiceContainer.find(`.location`).text(``);
        $quantityChoiceContainer.find(`.location`).siblings(`span`).removeClass(`d-none`);

        $quantityChoiceContainer.find(`[name=barcode], [name=pickedQuantity]`).val(null);
    });

    $modalGeneric.on(`hidden.bs.modal`, function () {
        $(this).find(`.error-message`).empty();
    });

    $informationButton.on(`click`, function () {
        $modalInformation.modal(`show`);
        $modalInformation.find(`.bookmark-icon`).removeClass(`d-none`);
    });
});


function toggleRequiredMobileLoginKeyModal() {
    $(`.modal-required-mobile-login-key`).modal(`show`);
}

function updateTimeline($currentTimelineEvent = undefined, hide = false) {
    $timeline.toggleClass(`d-none`, hide);

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

    toggleAutofocus();
}

function backPreviousPage() {
    const $current = $(`.active`)
    const $currentTimelineEvent = $timeline.find(`.current`);
    const $modalGiveUpStockExit = $(`.modal-give-up-stock-exit`);

    if ($current.prev().exists() && !$current.prev().first().is(`body`)) {
        $currentTimelineEvent.addClass(`future`).removeClass(`current`);
        $($currentTimelineEvent.prev()[0]).addClass(`current`).removeClass(`future`);
        $current.removeClass(`active`).addClass(`d-none`);
        $($current.prev()[0]).addClass(`active`).removeClass(`d-none`);

        if($referenceChoiceContainer.is(`.active`)) {
            $searchButton.removeClass(`d-none`);
            $nextButton.addClass(`d-none`);
        }

        toggleSummaryButtons();
    } else {
        $modalGiveUpStockExit.modal(`show`);
        $modalGiveUpStockExit.find(`.bookmark-icon`).removeClass(`d-none`);
    }
}

function updateReferenceInformations() {
    //const reference = references[references.length - 1];
    for (const [index, value] of Object.entries(referenceValues)) {
        if (index === `image`) {
            if (value) {
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
            $referenceInformations.find(`.${index}`).text(value || `-`);
        }
    }

    $quantityChoiceContainer.find(`.location`).text(referenceValues.location);
}

function updateSummaryTable() {
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
                <span class="wii-field-text">${value || `-`}</span>
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
            <td class="wii-body-text">@pickedQuantity</td>
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
    const $current = $stockExitContainer.find(`.active`);

    if ($current.is(`.summary-container`)) {
        $giveUpButton.removeClass(`d-none`);
        $giveUpButtonContainer.find(`.back-button`).addClass(`d-none`);
        $stockExitContainer.find(`.edit-stock-exit-button`).removeClass(`d-none`);
        $nextButton.addClass(`d-none`);
        $validateStockEntryButton.removeClass(`d-none`);
        $editStockExitButton.removeClass(`d-none`);
    } else if($current.is(`.reference-choice-container`)) {
        $nextButton.addClass(`d-none`);
    } else {
        $giveUpButton.addClass(`d-none`);
        $giveUpButtonContainer.find(`.back-button`).removeClass(`d-none`);
        $stockExitContainer.find(`.edit-stock-exit-button`).addClass(`d-none`);
        $nextButton.removeClass(`d-none`);
        $validateStockEntryButton.addClass(`d-none`);
        $editStockExitButton.addClass(`d-none`);
    }
}

function showGenericModal(message) {
    $modalGeneric.find(`.error-message`).html(message);
    $modalGeneric.modal(`show`);
}

function backToHome() {
    window.location.href = Routing.generate(`delivery_station_index`, true);
}

function processLogin($loadingContainer = undefined) {
    const mobileLoginKey = $(`[name=mobileLoginKey]`).val();

    if (mobileLoginKey) {
        wrapLoadingOnActionButton($loadingContainer || $(`.home .login`), () => (
            AJAX.route(AJAX.POST, `delivery_station_login`, {mobileLoginKey})
                .json()
                .then(({success}) => {
                    if (success) {
                        window.location.href = Routing.generate(`delivery_station_form`, {mobileLoginKey});
                    } else {
                        toggleRequiredMobileLoginKeyModal();
                    }
                })
        ));
    } else {
        toggleRequiredMobileLoginKeyModal();
    }
}

function processReferenceChoice($current, $loadingContainer, $currentTimelineEvent) {
    const reference = $current.find(`[name=reference]`).val();

    if (reference) {
        wrapLoadingOnActionButton($loadingContainer, () => (
            AJAX.route(AJAX.GET, `delivery_station_get_informations`, {reference})
                .json()
                .then(({values}) => {
                    referenceValues = values;
                    $referenceChoiceContainer
                        .find(`[name=reference]`)
                        .val(null)
                        .trigger(`change`);

                    //references.push(values);
                    console.log(references);

                    pushNextPage($current);
                    updateTimeline($currentTimelineEvent);
                    updateReferenceInformations();

                    $searchButton.addClass(`d-none`);
                    $nextButton.removeClass(`d-none`);
                })
        ));
    } else {
        showGenericModal(`Vous devez sélectionner une référence pour pouvoir continuer.`);
    }
}

function processBarcodeEntering(barcode) {
    const reference = referenceValues.id;

    wrapLoadingOnActionButton($(`body`), () => (
        AJAX.route(AJAX.GET, `delivery_station_get_informations`, {reference, barcode})
            .json()
            .then(({success, msg, values}) => {
                if (success) {
                    const {location, suppliers, isReference} = values;

                    referenceValues.location = location;
                    referenceValues.isReference = isReference;

                    const orderedSuppliers = suppliers
                        .split(`,`)
                        .reduce((acc, supplier) => acc + `<li>${supplier}</li>`, ``);

                    $quantityChoiceContainer.find(`.location`).siblings(`span`).addClass(`d-none`);
                    $quantityChoiceContainer.find(`.location`).text(location);
                    $quantityChoiceContainer.find(`.suppliers`).siblings(`span`).addClass(`d-none`);
                    $quantityChoiceContainer.find(`.suppliers`).html(orderedSuppliers);

                    toggleAutofocus($quantityChoiceContainer.find(`[name=pickedQuantity]`));
                } else {
                    showGenericModal(msg);
                }
            })
    ));
}

function toggleAutofocus($element = undefined) {
    const $activeContainer = $stockExitContainer.exists()
        ? $stockExitContainer.find(`.active`)
        : $homeContainer;

    $element = $activeContainer
        .find($element || `input, select`)
        .not(`.filtered-field`)
        .first();

    setTimeout(() => {
        if ($element.is(`.select2-hidden-accessible`)) {
            $element.select2(`open`);
        } else {
            $element.trigger(`focus`);
        }
    }, 1);
}
