import Routing from '@app/fos-routing';

import '@styles/pages/kiosk.scss';
import '@styles/pages/delivery-station.scss';

const REDIRECT_TO_HOME_DELAY = 15000;

let references = [];
let freeFields = [];
let referenceValues = {};
let isScannedBarcode = false;

const $timeline = $(`.timeline-container`);
const $formHeader = $(`.form-header`);
const $formHeaderSubtitle = $(`.form-header-subtitle`);

const $loginContainer = $(`.login-container`);
const $stockExitContainer = $(`.stock-exit-container`);
const $referenceChoiceContainer = $(`.reference-choice-container`);
const $quantityChoiceContainer = $(`.quantity-choice-container`);
const $summaryContainer = $(`.summary-container`);
const $addReferenceContainer = $(`.add-reference-container`);
const $otherInformationsContainer = $(`.other-informations-container`);
const $treatedDeliveryContainer = $(`.treated-delivery-container`);
const $multipleFieldFieldsContainer = $(`.multiple-filter-fields-container`);
const $filterFieldsContainer = $(`.filter-fields-container`);

const $loginButton = $(`button.login`);
const $validateButton = $(`.validate-button`);
const $submitGiveUpStockExit = $(`#submitGiveUpStockExit`);
const $giveUpButton = $(`.give-up-button`);
const $informationButton = $(`.information-button`);
const $nextButton = $(`.next-button`);
const $searchButton = $(`.search-button`);
const $backButton = $(`.back-button`);
const $backToHomeButton = $(`.back-to-home-button`);
const $editFreeFieldsButton = $(`.edit-free-fields-button`);
const $goToSummaryButton = $(`.go-to-summary-button`);

const $modalGeneric = $(`.modal-generic`);
const $modalInformation = $(`#modal-information`);
const $modalDeleteLine = $(`.modal-delete-line`);
const $modalGiveUpStockExit = $(`.modal-give-up-stock-exit`);

const $referenceInformations = $(`.reference-informations`);

$(function () {
    toggleAutofocus();
    $treatedDeliveryContainer.find(`.delay`).text(REDIRECT_TO_HOME_DELAY / 1000);

    $loginButton.on(`click`, function () {
        processLogin($(this));
    });

    // catching DataWedge barcode event
    $(document).on(`keyup`, (event) => {
        if (event.originalEvent.key === 'Enter') {
            const $target = $(event.target);
            if ($target.is(`.login`)) {
                processLogin();
            } else if ($target.closest(`.select2-container`).siblings(`select`).is(`[name=reference]`)) {
                isScannedBarcode = $target.val().startsWith(`REF`);
            } else if ($target.is(`[name=barcode]`)) {
                processBarcodeEntering($target.val());
            }
        }
    });

    $nextButton
        .add($searchButton)
        .on(`click`, function () {
            const $current = $stockExitContainer.find(`.active`);
            const $currentTimelineEvent = $timeline.find(`.current`);

            if ($current.is(`.reference-choice-container`)) {
                processReferenceChoice($current, $searchButton, $currentTimelineEvent);
            }

            if ($current.is(`.quantity-choice-container`)) {
                const barcode = $current.find(`[name=barcode]`).val();
                const pickedQuantity = $current.find(`[name=pickedQuantity]`).val();

                if (barcode && Number(pickedQuantity)) {
                    if (references.findIndex((reference) => reference.barcode === barcode) > -1) {
                        showGenericModal(`Le code barre <strong>${barcode}</strong> est déjà présent dans cette demande de livraison.`)
                    } else {
                        wrapLoadingOnActionButton($nextButton, () => (
                            AJAX.route(AJAX.GET, `delivery_station_get_informations`, {
                                pickedQuantity,
                                barcode,
                                reference: referenceValues.id
                            })
                                .json()
                                .then(({success, msg}) => {
                                    if (success) {
                                        references.push(referenceValues);
                                        const index = references.length - 1;
                                        references[index].barcode = barcode;
                                        references[index].pickedQuantity = pickedQuantity;

                                        $backButton.addClass(`d-none`);
                                        $giveUpButton.removeClass(`d-none`);

                                        if (references.length > 1) {
                                            $current
                                                .addClass(`d-none`)
                                                .removeClass(`active`);

                                            $summaryContainer
                                                .removeClass(`d-none`)
                                                .addClass(`active`);

                                            $goToSummaryButton.addClass(`d-none`);

                                            updateTimeline($currentTimelineEvent);
                                            updateTimeline($timeline.find(`.current`));
                                            updateSummaryTable();
                                            toggleSummaryButtons();
                                        } else {
                                            wrapLoadingOnActionButton($(`body`), () => getFreeFields($current, $currentTimelineEvent));
                                        }
                                    } else {
                                        showGenericModal(msg);
                                    }
                                })
                        ))
                    }
                } else if (!barcode) {
                    showGenericModal(`Vous devez renseigner un code barre pour continuer.`);
                } else if (pickedQuantity === ``) {
                    showGenericModal(`Vous devez renseigner une quantité prise pour continuer.`);
                } else if (Number(pickedQuantity) === 0) {
                    showGenericModal(`La quantité prise doit être supérieure à 0.`);
                }
            }

            if ($current.is(`.other-informations-container`)) {
                const $freeFields = $current.find(`.free-field`);

                const neededFreeField = Array.from($freeFields.find(`input, select`)).find((freeField) => $(freeField).is(`.needed`) && !$(freeField).val());
                if (neededFreeField) {
                    showGenericModal(`Le champ libre <strong>${$(neededFreeField).siblings(`.field-label`).text().replace(`*`, ``)}</strong> est obligatoire.`);
                } else {
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

                    $goToSummaryButton.addClass(`d-none`);

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

    $backButton.on(`click`, function () {
        backPreviousPage();
    });

    $(`.free-field select[multiple]`).on(`change`, function() {
        $referenceChoiceContainer.find(`[name=reference]`).val(null).trigger(SELECT2_TRIGGER_CHANGE);

        const label = $(this).siblings(`.field-label`).text().trim();
        const id = $(this).attr(`name`);
        const $fields = $(this).val().map((value) => {
            return $(`
                <div class="filter-parent mr-2 mb-2" data-removable="removable">
                    <div class="btn filter has-tooltip">
                        <div class="row align-items-center">
                            <div class="col">
                                <span class="bold">${value}</span>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-times pointer remove-filter" data-free-field-value="${value}"></i>
                            </div>
                        </div>
                    </div>
                </div>
            `)});

        const $fieldContainer = $multipleFieldFieldsContainer.find(`*[data-free-field-id="${id}"]`);
        if(!$fieldContainer.exists()) {
            $multipleFieldFieldsContainer.append($(`
                <div class="fields-list wii-small-text font-weight-bold mt-2" data-free-field-id="${id}">
                    <div class="name">${label} :</div>
                    <div class="fields d-flex flex-wrap"></div>
                </div>
            `));
        }

        $multipleFieldFieldsContainer.find(`*[data-free-field-id="${id}"] .fields`).html($fields);

        if(!$(this).val() || $(this).val().length === 0) {
            $(`*[data-free-field-id="${id}"]`).remove();
        }
    });

    $(document).on(`click`, `.remove-filter`, function () {
        const freeFieldId = $(this).closest(`.fields-list`).data(`free-field-id`);
        const freeFieldValue = $(this).data(`free-field-value`);

        const $select = $filterFieldsContainer.find(`[name="${freeFieldId}"]`);
        const index = $select.val().findIndex((value) => value === freeFieldValue);

        const values = $select.val().filter((v, i) => i !== index);
        $select.val(values).trigger(`change`);
        $(this).closest(`.filter-parent`).remove();

        if(!$select.val() || $select.val().length === 0) {
            $(`*[data-free-field-id="${freeFieldId}"]`).remove();
        }
    });

    $editFreeFieldsButton.on(`click`, () => {
        backPreviousPage();

        $editFreeFieldsButton
            .add($backButton)
            .addClass(`d-none`);

        $giveUpButton
            .add($goToSummaryButton)
            .removeClass(`d-none`);

        $nextButton.prop(`disabled`, false);
    });

    $validateButton.on(`click`, () => {
        const $current = $stockExitContainer.find(`.active`);

        const stringifiedReferences = JSON.stringify(references);
        const stringifiedFreeFields = JSON.stringify(freeFields);
        const token = $(`[name=token]`).val();
        const mobileLoginKey = $(`[name=mobileLoginKey]`).val();
        wrapLoadingOnActionButton($validateButton, () => (
            AJAX.route(AJAX.POST, `delivery_station_submit_request`, {
                references: stringifiedReferences,
                freeFields: stringifiedFreeFields,
                token,
                mobileLoginKey,
            })
                .json()
                .then(({success, msg}) => {
                    if (success) {
                        pushNextPage($current);
                        updateTimeline(undefined, true);
                        toggleSummaryButtons($current);

                        $nextButton
                            .add($backButton)
                            .addClass(`d-none`);

                        setTimeout(() => backToHome(), REDIRECT_TO_HOME_DELAY);
                    } else {
                        showGenericModal(msg);
                    }
                })
        ));
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

    $goToSummaryButton.on(`click`, () => {
        $stockExitContainer
            .find(`.active`)
            .removeClass(`active`)
            .addClass(`d-none`);

        $summaryContainer
            .removeClass(`d-none`)
            .addClass(`active`);

        $timeline.find(`span`).removeClass(`current future`);

        $timeline
            .find(`span`)
            .last()
            .addClass(`current`);

        updateSummaryTable();
        toggleSummaryButtons();
        $goToSummaryButton
            .add($searchButton)
            .addClass(`d-none`);
    });

    $submitGiveUpStockExit
        .add($modalGeneric.find(`.submit`))
        .add($backToHomeButton)
        .on(`click`, () => backToHome());

    $quantityChoiceContainer.find(`[name=barcode]`).on(`focusout change`, function () {
        const barcode = $(this).val();

        if (barcode !== ``) {
            processBarcodeEntering(barcode);

            if (Number($quantityChoiceContainer.find(`[name=pickedQuantity]`).val())) {
                $nextButton.prop(`disabled`, false);
            }
        } else {
            $nextButton.prop(`disabled`, true);
        }
    });

    $quantityChoiceContainer.find(`[name=pickedQuantity]`).on(`keyup change`, function () {
        const pickedQuantity = Number($(this).val());
        const $barcode = $quantityChoiceContainer.find(`[name=barcode]`);

        $nextButton.prop(`disabled`, !pickedQuantity || !$barcode.is(`.is-valid`));
    });

    $(document).arrive(`.delete-line`, function () {
        $(this).on(`click`, function () {
            const lineIndex = $(this).data(`line-index`);
            $modalDeleteLine
                .find(`.submit`)
                .data(`line-index`, lineIndex)
                .attr(`data-line-index`, lineIndex);

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
            .find(`tbody tr`)
            .get(lineIndex)
            .closest(`tr`)
            .remove();

        if (references.length === 0) {
            backToHome();
        }
    });

    $addReferenceContainer.on(`click`, () => {
        Array(3).fill(0).forEach(() => backPreviousPage());
        $goToSummaryButton.removeClass(`d-none`);
        $quantityChoiceContainer.find(`.suppliers li`).remove();
        $quantityChoiceContainer.find(`.suppliers`).siblings(`span`).removeClass(`d-none`);
        $quantityChoiceContainer.find(`.location`).text(``);
        $quantityChoiceContainer.find(`.location`).siblings(`span`).removeClass(`d-none`);

        $quantityChoiceContainer.find(`[name=barcode], [name=pickedQuantity]`).val(null);
    });

    $modalGeneric.on(`hidden.bs.modal`, function () {
        $(this).find(`.error-message`).empty();
    });

    $informationButton
        .find(`i`)
        .on(`click`, function () {
            $modalInformation.modal(`show`);
            $modalInformation.find(`.bookmark-icon`).removeClass(`d-none`);
        });

    $filterFieldsContainer.find(`.data`).on(`change`, () => {
        const $elements = $filterFieldsContainer.find(`.data`);
        let values = [];
        $elements.each(function () {
            values.push({label: $(this).attr(`name`), value: $(this).val()})
        });

        $(`[name=filterFields]`).val(JSON.stringify(values));
    });
});

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

    if ($current.prev().exists() && !$current.prev().is(`.login-container`) && !$current.prev().first().is(`body`)) {
        $currentTimelineEvent.addClass(`future`).removeClass(`current`);
        $($currentTimelineEvent.prev()[0]).addClass(`current`).removeClass(`future`);
        $current.removeClass(`active`).addClass(`d-none`);
        $($current.prev()[0]).addClass(`active`).removeClass(`d-none`);

        if ($referenceChoiceContainer.is(`.active`)) {
            $searchButton.removeClass(`d-none`);
            $nextButton.addClass(`d-none`);
        }

        if ($current.is($quantityChoiceContainer)) {
            $quantityChoiceContainer
                .find(`[name=barcode], [name=pickedQuantity]`)
                .val(null)
                .trigger(`change`);
        }

        toggleSummaryButtons();
    } else {
        $modalGiveUpStockExit.modal(`show`);
        $modalGiveUpStockExit.find(`.bookmark-icon`).removeClass(`d-none`);
    }
}

function updateReferenceInformations() {
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

    if (referenceValues.location) {
        $quantityChoiceContainer.find(`.location`).siblings(`span`).addClass(`d-none`);
        $quantityChoiceContainer.find(`.location`).text(referenceValues.location);
    }
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
                <span class="wii-field-name">${label.replace(`*`, ``)}</span>
                <span class="wii-field-text">${value || `-`}</span>
            </div>
        </div>
    `;

    $freeFieldsContainer.append($wrapper);
}

function renderReferenceLine($summaryContainer, lineIndex, {reference, label, barcode, pickedQuantity}) {
    let $line = $(`
        <tr>
            <td class="wii-body-text">${reference}</td>
            <td class="wii-body-text">${label}</td>
            <td class="wii-body-text">${barcode}</td>
            <td class="wii-body-text">${pickedQuantity}</td>
            <td class="wii-body-text">
                <i class="wii-icon wii-icon-trash wii-icon-25px-danger delete-line pointer" data-line-index="${lineIndex}"></i>
            </td>
        </tr>
    `);

    $summaryContainer
        .find(`tbody`)
        .append($line);
}

function toggleSummaryButtons() {
    const $current = $stockExitContainer.find(`.active`);

    if ($current.is(`.summary-container`)) {
        $giveUpButton.removeClass(`d-none`);
        $backButton.addClass(`d-none`);
        $nextButton.addClass(`d-none`);
        $validateButton.removeClass(`d-none`);
        $editFreeFieldsButton.removeClass(`d-none`);
    } else if ($current.is(`.reference-choice-container`)) {
        $nextButton.addClass(`d-none`);
    } else {
        $giveUpButton.addClass(`d-none`);
        $backButton.removeClass(`d-none`);
        $nextButton.removeClass(`d-none`);
        $validateButton.addClass(`d-none`);
        $editFreeFieldsButton.addClass(`d-none`);
    }
}

function showGenericModal(message) {
    $modalGeneric.find(`.error-message`).html(message);
    $modalGeneric.modal(`show`);
}

function backToHome() {
    const token = $(`[name=token]`).val();
    window.location.href = Routing.generate(`delivery_station_form`, {token});
}

function processLogin($loadingContainer = undefined) {
    const mobileLoginKey = $(`[name=mobileLoginKey]`).val();
    const token = $(`[name=token]`).val();

    if (mobileLoginKey) {
        wrapLoadingOnActionButton($loadingContainer || $(`.home .login`), () => (
            AJAX.route(AJAX.GET, `delivery_station_check_mobile_login_key`, {mobileLoginKey, token})
                .json()
                .then(({success, msg}) => {
                    if(success) {
                        $backButton
                            .add($searchButton)
                            .add($timeline)
                            .add($formHeader)
                            .add($formHeaderSubtitle)
                            .removeClass(`d-none`);

                        pushNextPage($loginContainer);
                    } else {
                        showGenericModal(msg);
                    }
                })
        ));
    } else {
        showGenericModal(`Merci de renseigner une clé de connexion nomade valide.`);
    }
}

function processReferenceChoice($current, $loadingContainer, $currentTimelineEvent) {
    const reference = $current.find(`[name=reference]`).val();

    if (reference) {
        wrapLoadingOnActionButton($loadingContainer, () => (
            AJAX.route(AJAX.GET, `delivery_station_get_informations`, {reference: reference.trim(), isScannedBarcode})
                .json()
                .then(({values, prefill}) => {
                    isScannedBarcode = false;
                    referenceValues = values;
                    $referenceChoiceContainer
                        .find(`[name=reference], .free-field input, .free-field select`)
                        .val(null)
                        .trigger(`change`);

                    pushNextPage($current);
                    updateTimeline($currentTimelineEvent);
                    updateReferenceInformations();

                    if(references.length > 0) {
                        $goToSummaryButton.removeClass(`d-none`);
                    }

                    if(prefill && !referenceValues.isReferenceByArticle) {
                        $quantityChoiceContainer
                            .find(`[name=barcode]`)
                            .val(referenceValues.barcode)
                            .trigger(`blur`);
                    }

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
                $quantityChoiceContainer.find(`[name=barcode]`).toggleClass(`is-valid`, success);
                $nextButton.prop(`disabled`, !success || !Number($quantityChoiceContainer.find(`[name=pickedQuantity]`).val()));
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
    const $activeContainer = $stockExitContainer.find(`.active`)

    $element = $activeContainer.find(`.trigger-autofocus`).exists()
        ? $activeContainer.find(`.trigger-autofocus`)
        : $activeContainer.find($element || `input, select`)
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

function getFreeFields($current, $currentTimelineEvent) {
    const token = $(`[name=token]`).val();
    AJAX.route(AJAX.GET, `delivery_station_get_free_fields`, {token})
        .json()
        .then(({template}) => {
            pushNextPage($current);
            updateTimeline($currentTimelineEvent);

            template = template || `<div class="text-center">Il n'y a aucun champ libre pour ce type de demande de livraison.</div>`;
            $otherInformationsContainer.html(template);

            toggleAutofocus();
        });
}
