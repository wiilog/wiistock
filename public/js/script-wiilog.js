const PAGE_PURCHASE_REQUEST = 'rpurchase';
const PAGE_TRANSFER_REQUEST = 'rtransfer';
const PAGE_TRANSFER_ORDER = 'otransfer';
const PAGE_DEM_COLLECTE = 'dcollecte';
const PAGE_DEM_LIVRAISON = 'dlivraison';
const PAGE_HAND = 'handling';
const PAGE_ORDRE_COLLECTE = 'ocollecte';
const PAGE_ORDRE_LIVRAISON = 'olivraison';
const PAGE_PREPA = 'prépa';
const PAGE_LU_ARRIVAL = 'LUArrival';
const PAGE_IMPORT = 'import';
const PAGE_EXPORT = 'export';
const PAGE_ALERTE = 'alerte';
const PAGE_RECEPTION = 'reception';
const PAGE_MVT_STOCK = 'mvt_stock';
const PAGE_MVT_TRACA = 'mvt_traca';
const PAGE_PACK = 'pack';
const PAGE_LITIGE_ARR = 'litige';
const PAGE_ENCOURS = 'encours';
const PAGE_INV_ENTRIES = 'inv_entries';
const PAGE_INV_MISSIONS = 'inv_missions';
const PAGE_INV_SHOW_MISSION = 'inv_mission_show';
const PAGE_RECEIPT_ASSOCIATION = 'receipt_association';
const PAGE_DISPATCHES = 'acheminement';
const PAGE_PRODUCTION = 'production';
const PAGE_STATUS = 'status';
const PAGE_EMPLACEMENT = 'emplacement';
const PAGE_TRANSPORT_REQUESTS = 'transportRequests';
const PAGE_PREPARATION_PLANNING = 'preparationPlanning';
const PAGE_PRODUCTION_PLANNING = 'productionPlanning';
const PAGE_TRANSPORT_ORDERS = 'transportOrders';
const PAGE_SUBCONTRACT_ORDERS = 'subcontractOrders';
const PAGE_TRANSPORT_ROUNDS = 'transportRounds';
const PAGE_URGENCES = 'urgences';
const PAGE_NOTIFICATIONS = 'notifications';
const PAGE_TRUCK_ARRIVAL = 'truckArrival';
const PAGE_SHIPPING = 'shipping_request';
const STATUT_ACTIF = 'disponible';
const STATUT_INACTIF = 'consommé';
const STATUT_EN_TRANSIT = 'en transit';


// alert modals config
const AUTO_HIDE_DEFAULT_DELAY = 2000;

// set max date for pickers
const MAX_DATETIME_HTML_INPUT = '2100-12-31T23:59';
const MAX_DATE_HTML_INPUT = '2100-12-31';

const TEAM_SIZE = 18;

const SELECT2_TRIGGER_CHANGE = 'change.select2';

const DATE_FORMATS_TO_DISPLAY = {
    'd/m/Y': 'DD/MM/YYYY',
    'Y-m-d': 'YYYY-MM-DD',
    'm-d-Y': 'MM-DD-YYYY',
}

const onFlyFormOpened = [];

$(function () {
    $(document).on('hide.bs.modal', function () {
        $('.select2-container.select2-container--open').remove();
    });

    $(".stop-propagation").on("click", function (e) {
        e.stopPropagation();
    });

    $(document).arrive(`.stop-propagation`, function() {
        $(this).on("click", function (e) {
            e.stopPropagation();
        });
    });

    $(document).arrive(`.prevent-default`, function() {
        $(this).on(`click`, function (e) {
            e.preventDefault();
        });
    });

    $('[data-toggle="popover"]').popover();

    setTimeout(() => {
        openQueryModal();
    }, 200);

    $('[type=datetime-local]').attr('max', MAX_DATETIME_HTML_INPUT);
    $('[type=date]').attr('max', MAX_DATE_HTML_INPUT);

    // Override Symfony Form content
    $('.form-error-icon').text('Erreur');
    $('.removeRequired, .form-group, label').removeClass('required');

    registerNotificationChannel();
    registerEasterEgg();

    $("div[data-name='user_language']").on('change', function() {
        changeUserLanguageDateFormat();
    });

    $("select[name='user_dateFormat']").on('change', function() {
        changeUserLanguageDateFormat();
    });
});

function changeUserLanguageDateFormat() {
    const params = {
        language: $("div[data-name='user_language']").find(':checked').val(),
        dateFormat: $("select[name='user_dateFormat']").find(':selected').val(),
    };

    $.post(Routing.generate(`header_language_dateFormat_api`), params)
        .then(() => window.location.reload());
}

function registerNotificationChannel() {
    if(typeof FCM !== `undefined`) {
        FCM.getToken({vapidKey: "BAtT1Leq2TOoLNyai0HAk5Fv3Tcqk0ps0wEGBwbT8TmHbXNCRU_jOLyvEm4_mc7-nb7XubnEZs-7VkB-ix8FX9A"}).then((token) => {
            $.post(Routing.generate('register_topic', {token}), function() {
                FCM.onMessage((payload) => {
                    const $notificationModal = $('.notification-modal');
                    const $countFigure = $(`.header-icon.notifications`).find('.icon-figure');
                    if(payload.data.image && payload.data.image !== "") {
                        $notificationModal.find('.notification-image').attr('src', payload.data.image);
                        $notificationModal.find('.notification-image').display();
                    } else {
                        $notificationModal.find('.notification-image').display(true);
                    }
                    $notificationModal.find('.notification-title').text(payload.data.title);
                    $notificationModal.find('.notification-content').text(payload.data.content);
                    $notificationModal.fadeIn(200);
                    let figure = Number.parseInt($countFigure.text()) || 0;
                    figure += 1;
                    $countFigure.text(figure);
                    $countFigure.display();
                });
            })
        }).catch(() => {});
    }
}

function clickNotification(event) {
    if ($(event.target).prop("tagName") === $('.notification-modal').find('.close').find('span').prop("tagName")) {
        event.stopPropagation();
        return;
    }
    window.location.href = Routing.generate('notifications_index', true);
}

function openQueryModal(query = null, event) {
    if (event) {
        event.preventDefault();
    }
    query = query || GetRequestQuery();
    if (query["open-modal"]) {
        const openModalNew = 'new';
        const openModalEdit = 'edit';
        if (query["open-modal"] === openModalNew) {
            const $modal = $('[data-modal-type="new"]').first();
            if (query["clear-modal"] !== '0') {
                clearModal($modal);
            }
            $modal.modal("show");
        } else if (query["open-modal"] === openModalEdit) { // edit
            const $openModal = $(`.open-modal-edit`);
            $openModal.data('id', query['modal-edit-id']);
            $openModal.trigger('click');
            delete query['modal-edit-id'];
        } else { // modal selector
            $(query["open-modal"]).trigger('click');
        }
        delete query["open-modal"];
        delete query["clear-modal"];

        SetRequestQuery(query);
    }
}

//DELETE
function deleteRow(button, $modal, submit) {
    let id = button.data('id');
    $modal.find(submit).attr('value', id);
}

//SHOW
/**
 * Initialise une fenêtre modale
 *
 * @param {Document} modal la fenêtre modale selectionnée : document.getElementById("modal").
 * @param {Document} button le bouton qui va envoyé les données au controller via Ajax.
 * @param {string} path le chemin pris pour envoyer les données.
 *
 */
function showRow(button, path, modal) {
    let id = button.data('id');
    let params = JSON.stringify(id);
    $.post(path, params, function (data) {
        modal.find('.modal-body').html(data);
    }, 'json');
}


//MODIFY
/**
 * La fonction modifie les valeurs d'une modale modifier avec les valeurs data-attibute.
 * Ces valeurs peuvent être trouvées dans datatableLigneArticleRow.html.twig
 *
 * @param {Document} button
 * @param {string} path le chemin pris pour envoyer les données.
 * @param {Document} modal la modale de modification
 * @param {Document|string} submit le bouton de validation du form pour le edit
 * @param setMaxQuantity
 * @param afterLoadingEditModal
 * @param wantsFreeFieldsRequireCheck
 */

function editRow(button, path, modal, submit, setMaxQuantity = false, afterLoadingEditModal = () => {}, wantsFreeFieldsRequireCheck = true) {
    clearFormErrors(modal);
    clearModal(modal);

    let params;
    if (button) {
        let id = button.data('id');
        let ref = button.data('ref');
        let json = {id: id, isADemand: 0};
        if (ref !== false) {
            json.ref = ref;
        }

        modal.find(submit).attr('value', id);
        params = JSON.stringify(json);
    }
    else {
        params = null;
    }

    let $modalBody;
    if(modal.find('.to-replace').exists()) {
        $modalBody = modal.find('.to-replace');
    } else {
        $modalBody = modal.find('.modal-body');
    }
    $modalBody.html('');

    return wrapLoadingOnActionButton($modalBody, () => {
        return $.post(path, params, function (resp) {
            $modalBody.html(resp);

            modal.find('.select2').select2();
            Select2Old.initFree(modal.find('.select2-free'));
            Select2Old.provider(modal.find('.ajax-autocomplete-fournisseur-edit'));
            Select2Old.frequency(modal.find('.ajax-autocomplete-frequency'));
            Select2Old.articleReference(modal.find('.ajax-autocomplete-edit, .ajax-autocomplete-ref'));
            Select2Old.location(modal.find('.ajax-autocomplete-location-edit'));
            Select2Old.carrier(modal.find('.ajax-autocomplete-transporteur-edit'));
            Select2Old.user(modal.find('.ajax-autocomplete-user-edit'));

            if (wantsFreeFieldsRequireCheck) {
                toggleRequiredChampsLibres(modal.find('#typeEdit'), 'edit');
            }

            if (setMaxQuantity) {
                setMaxQuantityEdit($('#referenceEdit'));
            }

            afterLoadingEditModal(modal);
        }, 'json');
    });
}

async function setMaxQuantityEdit(select) {
    const refArticleQte = await getQuantityRefArticle(select.val());

    let modalBody = select.closest(".modal-body");
    modalBody.find('#quantite').attr('max', refArticleQte);
}

function toggleRadioButton($button) {
    let sel = $button.data('title');
    let tog = $button.data('toggle');
    $('#' + tog).prop('value', sel);
    $('span[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('not-active');
    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('not-active').addClass('active');
}

//FONCTION REFARTICLE

function typeChoice($select, $freeFieldsContainer = null) {
    if(!$freeFieldsContainer) {
        $freeFieldsContainer = $select.closest('.modal').find('.free-fields-container');
    }
    $freeFieldsContainer.children().addClass('d-none');

    const typeId = $select.val();
    if (typeId) {
        $freeFieldsContainer.children(`[data-type="${typeId}"]`).removeClass('d-none');
    }
}

function updateQuantityDisplay($elem, parent = '.modal-body') {
    let $modalBody = $elem.closest(parent);
    const $reference = $modalBody.find('.reference');
    const $article = $modalBody.find('.article');
    const $allArticle = $modalBody.find('.article, .emergency-comment');
    let typeQuantite = $elem.data('title');
    if (typeQuantite == 'reference') {
        $allArticle.addClass('d-none');
        $reference.removeClass('d-none');

        clearCheckboxes($allArticle);
        $allArticle.find('input, select').val('');
        $allArticle.find('select.select2-hidden-accessible').select2('val', '');
    } else if (typeQuantite == 'article') {
        $reference.addClass('d-none');
        $article.removeClass('d-none');

        clearCheckboxes($reference);
        $reference.find('input, select').val('');
        $reference.find('select.select2-hidden-accessible').select2('val', '');
    }
}

function toggleRequiredChampsLibres(type, require, $freeFieldContainer = null) {
    const $bloc = $freeFieldContainer
        ? $freeFieldContainer
        : type
            .closest('.modal')
            .find('.free-fields-container');

    const typeId = type instanceof jQuery ? type.val() : type;
    let params = {};
    if (typeId) {
        $bloc
            .find('.data')
            .removeClass('needed');

        $bloc
            .find('.wii-switch')
            .removeClass('needed');

        if (require === 'create') { // we don't save free field which are hidden
            $bloc
                .find('.data')
                .addClass('free-field-data')
                .removeClass('data');

            $bloc
                .children(`[data-type="${typeId}"]`)
                .find('.free-field-data')
                .removeClass('free-field-data')
                .addClass('data');
        }

        $bloc
            .find('span.is-required-label')
            .addClass('d-none');
        params[require] = typeId;
        let path = Routing.generate('display_required_champs_libres', true);
        $bloc.find('input[name], select[name], textarea[name]').removeClass('needed');
        $.post(path, JSON.stringify(params), function (data) {
            if (data) {
                data.forEach(function (element) {
                    const $formControl = $bloc.find('[name="' + element + '"], .wii-switch:has(input[name="' + element + '"])');
                    const $label = $formControl.closest('.free-field').find('label');
                    $label
                        .find('span.is-required-label')
                        .removeClass('d-none');
                    $formControl.addClass('needed');
                });
            }
        }, 'json');
    }
}

function clearErrorMsg($div) {
    $div.closest('.modal').find('.error-msg').html('');
}

function clearInvalidInputs($modal) {
    let $inputs = $modal.find('.modal-body').find(".data");
    $inputs.each(function () {
        // on enlève les classes is-invalid
        $(this).removeClass('is-invalid');
        $(this).next().find('.select2-selection').removeClass('is-invalid');
    });
}

function displayError(modal, msg, success) {
    if (!success) {
        modal.find('.error-msg').html(msg);
    } else {
        modal.find('.close').click();
    }
}

function clearModal(modal) {
    let $modal = typeof modal === 'string' ? $(modal) : modal;

    let switches = $modal.find('.wii-switch').find('input[type="radio"]');
    switches.each(function() {
        if($(this).data("init") === "checked") {
            $(this).prop('checked', true);
        } else {
            $(this).prop('checked', false);
        }
    })

    let $inputs = $modal.find('.modal-body').find(".data:not([type=radio]),.data-array:not([type=radio])");

    // on vide tous les inputs (sauf les disabled et les input hidden)
    $inputs.each(function () {
        const $input = $(this);
        if (!$input.closest('.wii-switch').exists()) {
            if ($input.attr('disabled') !== 'disabled' && $input.attr('type') !== 'hidden' && !$input.hasClass('no-clear')) {
                if ($input.hasClass('needs-default')) {
                    $input.val($input.data('init'));
                } else {
                    $input.val("");
                }
            }
            // on enlève les classes is-invalid
            $input.removeClass('is-invalid');
            $input.next().find('.select2-selection').removeClass('is-invalid');
            //TODO protection ?
        }
    });
    // on vide tous les select2
    let selects = $modal
        .find('.modal-body')
        .find('.ajax-autocomplete, .ajax-autocomplete-location, .ajax-autocomplete-fournisseur, .ajax-autocomplete-transporteur, .select2, .select2-free, .ajax-autocomplete-user, [data-s2-initialized], .list-multiple');
    selects.each(function () {
        const $this = $(this);

        if (!$this.hasClass('no-clear')) {
            if ($this.hasClass('needs-default')) {
                if($this.is(`[data-default-all]`)) {
                    $this.find(`option`).prop('selected', true);
                    $this.trigger('change');
                } else {
                    $this.val($this.data('init')).trigger('change');
                }
            } else {
                $this.val(null).trigger('change');
            }
        }
    });
    // let dataArrays = $modal
    //     .find('.modal-body')
    //     .find('.data-array');
    // dataArrays.each(function() {
    //     if ($(this).data('init') !== undefined) {
    //         $(this).val($(this).data('init'));
    //     }
    // });
    // on vide les messages d'erreur
    $modal.find('.error-msg, .password-error-msg').html('');
    // on remet toutes les checkboxes sur off
    clearCheckboxes($modal);

    // on vide les éditeurs de texte
    $modal.find('.ql-editor').text('');
    // on vide les div identifiées comme à vider
    $modal.find('.clear').html('');
    $modal.find('.remove-on-clear').remove();
    $modal.find('.attachement').remove();
    $modal.find('.isRight').removeClass('isRight');
    resetDroppedFiles();
}

function clearCheckboxes($modal) {
    let checkboxes = $modal.find('.checkbox');
    checkboxes.each(function () {
        if (!$(this).hasClass('no-clear')) {
            if ($(this).hasClass('needs-default')) {
                $(this).prop('checked', $(this).data('init'));
            } else {
                $(this).prop('checked', false);
                $(this).removeClass('active');
                $(this).addClass('not-active');
            }
        }
    });
}

function serializeFilters($table = undefined) {
    const valFunction = {
        'filter-input': ($input) => ($input.val() || ``).trim(),
        'filter-select2': ($input) => ($input.select2(`data`) || [])
            .filter(({id, text}) => (id.trim() && text.trim()))
            .map(({id, text}) => ({id, text: text.replace(/(\r\n|\n|\r)/gm, ``).trim()})),
        'filter-checkbox': ($input) => $input.is(`:checked`),
        'filter-switch': ($input) => $input.closest(`.wii-expanded-switch, .wii-switch`).find(':checked').val(),
    };

    return Object.keys(valFunction).reduce((acc, key) => {
        let $fields;
        if($table && $table.closest(`.settings`).exists()) {
            $fields = $table.closest(`.settings-content`).find(`.filters-container .${key}`);
        } else {
            $fields = $(`.filters-container .${key}`);
        }

        const values = {};
        $fields.each(function () {
            const $elem = $(this);
            values[$elem.data(`override-name`) || $elem.attr(`name`)] = valFunction[key]($elem);
        });

        return ({
            ...acc,
            ...values
        });
    }, {});
}

function initDatePickers() {
    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';
    initDateTimePicker('.free-field-date', DATE_FORMATS_TO_DISPLAY[format]);
    initDateTimePicker('.free-field-datetime', DATE_FORMATS_TO_DISPLAY[format] + ' HH:mm');
    initDateTimePicker('.datetime-field', DATE_FORMATS_TO_DISPLAY[format] + ' HH:mm');
    initDateTimePicker('.date-field', DATE_FORMATS_TO_DISPLAY[format]);
    fillDatePickers('.free-field-date');
    fillDatePickers('.datetime-field', 'YYYY-MM-DD', true);
    fillDatePickers('.date-field', 'YYYY-MM-DD', false);
    fillDatePickers('.free-field-datetime', 'YYYY-MM-DD', true);
}

function checkAndDeleteRow(icon, modalName, route, submit, getParams = null) {
    let $modalBody = $(modalName).find('.modal-body');
    let $submit = submit instanceof jQuery ? submit : $(submit);
    let id = icon.data('id');

    let param = JSON.stringify(id);
    $submit.hide();
    $modalBody.html(
        '<div class="row justify-content-center">' +
        '   <div class="col-auto">' +
        '       <div class="spinner-border" role="status">' +
        '           <span class="sr-only">Loading...</span>' +
        '       </div>' +
        '   </div>' +
        '</div>'
    );

    const getParamsStr = getParams
        ? Object
            .keys(getParams)
            .map((key) => (key + "=" + encodeURIComponent(getParams[key])))
            .join('&')
        : '';

    $.post(Routing.generate(route) + (getParamsStr ? `?${getParamsStr}` : ''), param, function (resp) {
        $modalBody.html(resp.html);
        if (!resp.delete) {
            $submit.hide();
        } else {
            $submit.show();
            $submit.attr('value', id);
        }
    });
}

function hideSpinner(div) {
    div.removeClass('d-flex');
    div.addClass('d-none');
}

function loadSpinner(div) {
    div.removeClass('d-none');
    div.addClass('d-flex');
}

function displayRight(div) {
    div.addClass('isRight');
    div.removeClass('isWrong');
}

function displayWrong(div) {
    div.removeClass('isRight');
    div.addClass('isWrong');
}

function displayNeutral(div) {
    div.removeClass('isRight');
    div.removeClass('isWrong');
}

function redirectToDemandeLivraison(demandeId) {
    window.open(Routing.generate('demande_show', {id: demandeId}));
}

/**
 * Manage on fly forms
 * Should instanciate onFlyFormOpened object in the script
 * @param id
 * @param button
 * @param forceHide
 */
function onFlyFormToggle(id, button, forceHide = false) {
    let $toShow = $('#' + id);
    let $toAdd = $('#' + button);
    const $flyForm = $toShow.closest('.fly-form');
    if (!forceHide && $toShow.hasClass('invisible')) {
        $flyForm.css('height', 'auto');
        $toShow.css("height", "auto");
        $toShow.removeClass('invisible');
        $toAdd.removeClass('invisible');
        onFlyFormOpened[id] = true;
    } else {
        $toShow
            .addClass('invisible')
            .css("height", "0");
        $toAdd.addClass('invisible');
        $flyForm.css('height', 0);

        // we reset all field
        $toShow
            .find('.newFormulaire')
            .each(function () {
                const $fieldNext = $(this).next();
                if ($fieldNext.is('.select2-container')) {
                    $fieldNext.removeClass('is-invalid');
                }

                $(this)
                    .removeClass('is-invalid')
                    .val('')
                    .trigger('change');
            });

        onFlyFormOpened[id] = false;

        const onFlyFormOpenedValues = Object.values(onFlyFormOpened);
        // si tous les formulaires sont cachés
        if (onFlyFormOpenedValues.length === 0 ||
            Object.values(onFlyFormOpened).every((opened) => !opened)) {
            $toShow.parent().parent().css("height", "0");
        }
    }
}


function onFlyFormSubmit(path, button, toHide, buttonAdd, $select = null) {
    let inputs = button.closest('.fly-form').find(".newFormulaire");
    let params = {};
    let formIsValid = true;
    inputs.each(function () {
        if ($(this).hasClass('neededNew') && ($(this).val() === '' || $(this).val() === null)) {
            $(this).addClass('is-invalid');
            const $fieldNext = $(this).next();
            if ($fieldNext.is('.select2-container')) {
                $fieldNext.addClass('is-invalid');
            }
            formIsValid = false;
        } else {
            $(this).removeClass('is-invalid');
            const $fieldNext = $(this).next();
            if ($fieldNext.is('.select2-container')) {
                $fieldNext.removeClass('is-invalid');
            }
        }
        params[$(this).attr('name')] = $(this).val();
    });
    if (formIsValid) {
        $.post(path, params, function (response) {
            if (response && response.success) {
                if ($select) {
                    let option = new Option(response.text, response.id, true, true);
                    $select.append(option).trigger('change');
                }
                onFlyFormToggle(toHide, buttonAdd, true);
            }
            else if (response && response.msg) {
                showBSAlert(response.msg, 'danger');
            }
        });
    }
}

/**
 * @param {string} dateInput
 * @param {string} format
 * @param {{}|{
 *      minDate: boolean,
 *      defaultHours: number|null,
 *      defaultMinutes: number|null,
 *      disableDates: boolean|null,
 *      setTodayDate: boolean,
 * }} options
 */
function initDateTimePicker(dateInput = '#dateMin, #dateMax, #expectedDate', format = 'DD/MM/YYYY', options = {}) {
    let config = {
        format: format,
        useCurrent: false,
        locale: moment.locale('fr'),
        showTodayButton: true,
        showClear: true,
        icons: {
            clear: 'fas wii-icon wii-icon-trash-black date-picker-icon pointer',
            today: 'fa fa-crosshairs date-picker-icon pointer',
        },
        tooltips: {
            today: 'Aujourd\'hui',
            clear: 'Supprimer',
            selectMonth: 'Choisir le mois',
            selectYear: 'Choisir l\'année',
            selectDecade: 'Choisir la décennie',
        },
        ...(options.setTodayDate ? {defaultDate: moment()} : null)
    };
    if (options.disableDates) {
        config.disabledDates = options.disableDates;
    }
    if (options.minDate) {
        config.minDate = moment().subtract(1, "days").hours(23).minutes(59).seconds(59);
    }
    if (options.defaultHours && options.defaultMinutes) {
        config.defaultDate = moment().hours(options.defaultHours).minutes(options.defaultMinutes);
    }

    const $dateInput = typeof dateInput === 'string' ? $(dateInput) : dateInput;
    $dateInput.data("dtp-initialized", "true");
    $dateInput.datetimepicker(config);
}

function fillDatePickers(selector, sourceFormat = 'YYYY-MM-DD', appendTime = false) {
    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';

    const time = appendTime ? ' HH:mm' : '';
    const destinationFormat = DATE_FORMATS_TO_DISPLAY[format] + time;
    $(selector).each(function () {
        if ($(this).data('init')) {
            const dateValue = ($(this).data('init') === `now` ? moment() : moment($(this).data('init'), sourceFormat + time)).format(destinationFormat);
            $(this)
                .data("DateTimePicker")
                .format(destinationFormat)
                .date(dateValue);
        }
    })
}



function warningEmptyDatesForCsv(errorMsg) {
    showBSAlert(errorMsg, 'danger');
    $('#dateMin, #dateMax').addClass('is-invalid');
    $('.is-invalid').on('click', function () {
        $(this).parent().find('.is-invalid').removeClass('is-invalid');
    });
}

function warningEmptyTypeTransportForCsv() {
    showBSAlert('Veuillez saisir un type de transport dans le filtre en haut de page.', 'danger');
    const buttonsType = $( ".wii-expanded-switch" ).first();
    buttonsType.addClass('is-invalid');
    $('.is-invalid').on('click', function () {
        $(this).parent().find('.is-invalid').removeClass('is-invalid');
    });
}

function displayFiltersSup(data, needsDateFormatting = false) {
    let format = 'd/m/Y';
    if (needsDateFormatting) {
        const $userFormat = $('#userDateFormat');
        format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';
    }

    data.forEach(function (element) {
        const $element = $(`.filters [name="${element.field}"]`);

        if($element.is(`.filter-switch`)) {
            $(`.filter-switch[name="${element.field}"][value="${element.value}"]`)
                .prop(`checked`, true)
                .trigger(`change`);
        } else {
            //TODO refacto !!!!!!
            switch (element.field) {
                case 'utilisateurs':
                case 'declarants':
                case 'providers':
                case 'reference':
                case 'statut':
                case 'carriers':
                case 'emplacement':
                case 'pickLocation':
                case 'dropLocation':
                case 'demCollecte':
                case 'disputeNumber':
                case 'demande':
                case 'multipleTypes':
                case 'receivers':
                case 'requesters':
                case 'commandList':
                case 'operators':
                case 'dispatchNumber':
                case 'emergencyMultiple':
                case 'businessUnit':
                case 'article':
                case 'managers':
                case 'deliverers':
                case 'drivers':
                case 'logisticUnits':
                case 'locationWithGroups':
                case 'unloadingLocation':
                    let valuesElement = element.value.split(',');
                    let $select = $(`.filter-select2[name="${element.field}"]`);
                    $select.find('option').prop('selected', false);
                    valuesElement.forEach((value) => {
                        let valueArray = value.split(':');
                        let id = valueArray[0];
                        let name = valueArray[1];
                        const $optionToSelect = $select.find(`option[value="${name}"]`).length > 0
                            ? $select.find(`option[value="${name}"]`)
                            : $select.find(`option[value="${id}"]`).length > 0
                                ? $select.find(`option[value="${id}"]`)
                                : null;
                        if ($optionToSelect) {
                            $optionToSelect.prop('selected', true);
                            $select.trigger('change');
                        } else {
                            let option = new Option(name, id, true, true);
                            $select.append(option).trigger('change');
                        }
                    });
                    break;

                // multiple
                case 'natures':
                    let valuesElement2 = element.value.split(',');
                    let $select2 = $(`.filter-select2[name="${element.field}"]`);
                    let ids = [];
                    valuesElement2.forEach((value) => {
                        let valueArray = value.split(':');
                        let id = valueArray[0];
                        ids.push(id);
                    });
                    $select2.val(ids).trigger('change');
                    break;

                case 'emergency':
                case 'attachmentAssigned':
                case 'customs':
                case 'frozen':
                case 'carrierTrackingNumberNotAssigned':
                case 'useTruckArrivals':
                    if (element.value === '1') {
                        $('#' + element.field + '-filter').attr('checked', 'checked');
                    }
                    break;

                case 'litigeOrigin':
                    const text = element.value || '';
                    const id = text.replace('é', 'e').substring(0, 3).toUpperCase();
                    $(`.filter-checkbox[name="${element.field}"]`).val(id).trigger('change');
                    break;

                case 'dateMin':
                case 'dateMax':
                    const sourceFormat = (element.value && element.value.indexOf('/') > -1)
                        ? 'DD/MM/YYYY'
                        : 'YYYY-MM-DD';
                    const $fieldDate = $(`.filter-input[name="${element.field}"]`);
                    const dateValue = moment(element.value, sourceFormat).format(DATE_FORMATS_TO_DISPLAY[format]);
                    if ($fieldDate.data("DateTimePicker")) {
                        $fieldDate
                            .data("DateTimePicker")
                            .format(DATE_FORMATS_TO_DISPLAY[format])
                            .date(dateValue);
                    } else {
                        $fieldDate.val(dateValue);
                    }
                    break;
                case 'statuses-filter':
                case 'date-choice':
                    let values = element.value.split(',');
                    values = values.map((value) => typeof value === "string" && !isNaN(value) && !isNaN(Number.parseInt(value)) ? Number.parseInt(value) : value);
                    const $parent = $(`.${element.field}`);
                    let itemChecked = false;
                    let itemSelectCount = 0;
                    $parent.find('.dropdown-item').each(function () {
                        const $input = $(this).find('input');
                        const id = $input.data('id');
                        if (values.includes(id)) {
                            $input.prop('checked', true);
                            itemChecked = true;
                            itemSelectCount++;
                        }
                    });

                    const plural = itemSelectCount > 1 ? 's' : '';

                    $parent
                        .find('.status-filter-title')
                        .html(`${itemSelectCount} statut${plural} sélectionné${plural}`)

                    // checked input if no selected for date-choice
                    if (!itemChecked) {
                        $parent
                            .find('.dropdown-item input[data-id="creationDate"]')
                            .prop('checked', true);
                    }

                    break;

                default:
                    const $fieldWithId = $('#' + element.field);
                    const $field = $fieldWithId.length > 0
                        ? $fieldWithId
                        : $('.filters-container').find(`[name="${element.field}"]`);
                    $field.val(element.value).trigger(`change`);
            }
        }
    });
}

/**
 *
 * @param {string|undefined} title
 * @param $body jQuery object
 * @param {array} buttonConfig array of html config
 * @param {'success'|'warning'|'error'|undefined} iconType
 * @param {boolean} autoHide delay in milliseconds
 * @return {jQuery} The displayed modal
 */
function displayAlertModal(title, $body, buttonConfig, iconType = undefined, autoHide = false) {
    const $alertModal = getBSAlertModal();
    hideSpinner($alertModal.find('.modal-footer .spinner'));
    $alertModal.find('.modal-footer-wrapper').removeClass('d-none');

    // set title
    const $modalHeader = $alertModal.find('.modal-header');
    const $modalTitle = $modalHeader.find('.modal-title');

    if (title) {
        $modalHeader.removeClass('d-none');
        $modalTitle.text(title);
    } else {
        $modalHeader.addClass('d-none');
        $modalTitle.empty();
    }

    const $modalBody = $alertModal.find('.modal-body');
    $modalBody
        .find('.bookmark-icon')
        .addClass('d-none')
        .removeClass('d-flex');

    // we display requested icon
    if (iconType) {
        $modalBody
            .find(`.bookmark-icon.bookmark-${iconType}`)
            .removeClass('d-none')
            .addClass('d-flex');
    }

    $modalBody
        .find('.modal-body-main')
        .html($body);

    // set buttons
    const $modalFooter = $alertModal.find('.modal-footer > .modal-footer-wrapper');
    if (buttonConfig && buttonConfig.length > 0) {
        $modalFooter.removeClass('d-none');
        const $wrapper = $('<div/>', {class: 'row justify-content-center'}).prepend(
            ...buttonConfig.map(({action, ...config}, index) => {
                const classes = 'col-auto ' + (index === 0 ? 'pr-0' : 'pl-2');
                return $('<div/>', {class: classes}).append($('<button/>', {
                    ...config,
                    ...(action
                        ? {
                            click: () => {
                                action($alertModal)
                            }
                        }
                        : {})
                }));
            })
        );
        $modalFooter.html($wrapper);
    } else {
        $modalFooter.addClass('d-none');
        $modalFooter.empty();
    }

    if (autoHide) {
        setTimeout(() => {
            if ($alertModal.hasClass('show')) {
                $modalFooter.find('.btn-action-on-hide').trigger('click');
                $alertModal.modal('hide');
            }
        }, AUTO_HIDE_DEFAULT_DELAY)
    }

    $alertModal.modal('show');
}

function initOnTheFlyCopies($elems) {
    $elems.each(function () {
        $(this).keyup(function () {
            $(this).closest('.form-group').find('.copiedOnTheFly').val($(this).val());
        })
    });
}

/**
 * @deprecated Use exportFile's utils.js function instead
 */
function saveExportFile(routeName,
                        needsDateFilters = true,
                        routeParam = {},
                        needsAdditionalFilters = false,
                        errorMsgDates = Translation.of('Général', null, 'Modale', 'Veuillez saisir des dates dans le filtre en haut de page.'),
                        needsDateFormatting = false) {

    const buttonTypeTransport = $("input[name='category']:checked")
    const $spinner = $('#spinner');
    loadSpinner($spinner);

    const path = Routing.generate(routeName, routeParam, true);
    let format = 'd/m/Y';
    if (needsDateFormatting) {
        const $userFormat = $('#userDateFormat');
        format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';
    }
    const data = {};
    $('.filterService input, .dateFilters input, select[name="statut"]').each(function () {
        const $input = $(this);
        const name = $input.attr('name');
        const val = $input.val();
        if (name && val) {
            if(!($input.is(':radio') && !$input.is(':checked'))) {
                data[name] = val;
            }
        }
    });
    if (data.dateMin && data.dateMax) {
        data.dateMin = moment(data.dateMin, DATE_FORMATS_TO_DISPLAY[format]).format('YYYY-MM-DD');
        data.dateMax = moment(data.dateMax, DATE_FORMATS_TO_DISPLAY[format]).format('YYYY-MM-DD');
    }
    const dataKeys = Object.keys(data);
    const joinedData = dataKeys
        .map((key) => `${encodeURIComponent(key)}=${encodeURIComponent(data[key])}`)
        .join('&');

    if ((data.dateMin && data.dateMax) || !needsDateFilters) {
        if(needsAdditionalFilters) {
            if (!buttonTypeTransport.is(':empty')) {
                warningEmptyTypeTransportForCsv();
            }
            else {
               window.location.href = `${path}?${joinedData}`;
            }
        }
        else {
            window.location.href = `${path}?${joinedData}`;
        }
    }
    else {
        warningEmptyDatesForCsv(errorMsgDates);
    }
    hideSpinner($spinner);
}

function fillDemandeurField($modal) {
    const $operatorSelect = $modal.find('select[name=disputeReporter]');
    const $loggedUserInput = $modal.find('input[hidden][name="logged-user"]');
    const userId = $loggedUserInput.data('id');
    const $operatorSelect2 = $operatorSelect
        .select2()
        .val(null)
        .trigger('change');

    if (userId) {
        const $alreadyLoggedUserOption = $operatorSelect.find(`option[value="${userId}"]`);
        if ($alreadyLoggedUserOption.length > 0) {
            $operatorSelect2.val(userId);
        }
        else {
            let option = new Option($loggedUserInput.data('username'), userId, true, true);
            $operatorSelect2.append(option);
        }
        $operatorSelect2.trigger('change');
    }
}

function limitTextareaLength($textarea, lineNumber, lineLength) {
    const textareaVal = ($textarea.val() || '');
    const linesSplit = textareaVal
        .replace(/\r\n/g,'\n')
        .split('\n');

    let newValueSplit = linesSplit;

    // set max line number
    if (linesSplit.length > lineNumber) {
        newValueSplit = newValueSplit.slice(0, lineNumber);
    }

    // set max line length
    newValueSplit = newValueSplit.map((line) => line.substr(0, lineLength));

    const newVal = newValueSplit.join('\n');
    const oldVal = $textarea.val();

    if (newVal !== oldVal) {
        const cursorPosition = $textarea[0].selectionStart
        $textarea.val(newVal).trigger('change');
        $textarea[0].selectionStart = cursorPosition;
        $textarea[0].selectionEnd = cursorPosition;
    }
}

function GetRequestQuery() {
    const searchSplit = (location.search.substring(1, location.search.length) || '').split('&');
    const res = {};
    for (let i = 0; i < searchSplit.length; i += 1) {
        const [name, value] = searchSplit[i].split('=');
        if (name) {
            res[decodeURIComponent(name).toLowerCase()] = decodeURIComponent(value);
        }
    }

    return res;
}

function SetRequestQuery(queryParams = {}) {
    const queryParamStr = Object
        .keys(queryParams)
        .map((key) => `${encodeURIComponent(key)}=${queryParams[key] ? encodeURIComponent(queryParams[key]) : ''}`)
        .join('&')

    const newUrl = `${window.location.protocol}//${window.location.host}${window.location.pathname}${queryParamStr ? ('?' + queryParamStr) : ''}`

    window.history.pushState({path: newUrl}, document.title, newUrl);
}

function onTypeChange($select) {
    const $modal = $select.closest('.modal, .wii-form');
    toggleRequiredChampsLibres($select, 'create');
    const $freeFieldsContainer = $modal.find('.free-fields-container');

    toggleRequiredChampsLibres($select, 'create', $freeFieldsContainer);
    typeChoice($select, $freeFieldsContainer);

    const typeId = parseInt($select.val());

    const $selectStatus = $modal.find('select[name="status"]');
    $selectStatus.find('option[data-type-id!="' + typeId + '"]').addClass('d-none');
    $selectStatus.val(null).trigger('change');

    let $errorEmptyStatus = $selectStatus.siblings('.error-empty-status');
    if(!$errorEmptyStatus.length) {
        $errorEmptyStatus = $selectStatus.closest('.form-item').find('.error-empty-status');

        if(!$errorEmptyStatus.length) {
            $errorEmptyStatus = $modal.find('.error-empty-status');
        }
    }
    $errorEmptyStatus.addClass('d-none');

    if(!typeId) {
        $selectStatus.removeClass('d-none');
        $selectStatus.siblings('.select2-container').removeClass('d-none');
        $selectStatus.prop('disabled', true);
    } else {
        const $correspondingStatuses = $selectStatus.find('option[data-type-id="' + typeId + '"]');
        $selectStatus.prop('disabled', false);
        $correspondingStatuses.removeClass('d-none');
        const defaultStatuses = JSON.parse($selectStatus.siblings('input[name="defaultStatuses"]').val() || '{}');

        const [type] = $select.is('[data-s2-initialized]') ? $select.select2('data') : [];

        if ($correspondingStatuses.length > 1 || type?.statusCount > 1) {
            $selectStatus.removeClass('d-none');
            $selectStatus.siblings('.select2-container').removeClass('d-none');
            if (defaultStatuses[typeId]) {
                $selectStatus.val(defaultStatuses[typeId]);
            } else if ($correspondingStatuses.length === 1) {
                $selectStatus.val($correspondingStatuses[0].value);
            } else {
                $selectStatus.removeAttr('disabled');
            }
        } else if($correspondingStatuses.length === 1 || type?.statusCount === 1) {
            const firstStatus = $modal.find('select[name="status"] option:not(.d-none):first').val();

            if (firstStatus) {
                // option loaded in DOM
                $selectStatus
                    .val(firstStatus)
                    .prop('disabled', true);
            }

            $selectStatus.removeClass('d-none');
            $selectStatus.siblings('.select2-container').removeClass('d-none');
        } else if (typeId) {
            $errorEmptyStatus.removeClass('d-none');
            $selectStatus.addClass('d-none');
            $selectStatus.siblings('.select2-container').addClass('d-none');
        }

        if ($modal.attr('id') === 'modalNewHandling') {
            $.post(Routing.generate('handling_users_by_type'), {id: typeId}, function (data) {
                const $select2 = $modal.find('select[name=receivers]');
                $select2.empty().trigger('change');
                Object.entries(data).forEach(([key, value]) => {
                    let option = new Option(value, key, true, true);
                    $select2.append(option).trigger('change');
                })
            });
        }
    }
}

function changeAttributByType($select) {
    const $modal = $select.closest('.modal');
    // find all fixed fields that can be configurable
    const $fields = $modal.find('[data-displayed-type]');

    // remove required symbol from all fields
    $fields.find('.required-symbol')
        .remove();
    $fields.find('.data')
        .removeClass('needed')
        .prop('required', false)

    // find all fields that should be displayed
    const $fieldsToDisplay = $fields.filter(`[data-displayed-type~="${$select.val()}"]`);

    // find all fields that should be required
    const $fieldsRequired = $fieldsToDisplay.filter(`[data-required-type~="${$select.val()}"]`);

    // add required symbol to all required fields
    $fieldsRequired.find('.field-label')
        .append($('<span class="required-symbol">*</span>'));
    $fieldsRequired.find('.data')
        .addClass('needed')
        .prop('required', true)

    // find all fields that should not be required and remove required attribute
    const $fieldsNotRequired = $fieldsToDisplay.not($fieldsRequired);
    $fieldsNotRequired.find('.data')
        .removeClass('is-invalid');
    $fieldsNotRequired.find('.invalid-feedback')
        .remove();
    $fieldsNotRequired.find('.invalid-feedback')
        .remove();

    // hide the fields that should not be displayed
    $fields.not($fieldsToDisplay)
        .addClass('d-none');
    // show the fields that should be displayed
    $fieldsToDisplay.removeClass('d-none');
}

function getBSAlertModal() {
    return $('#alert-modal');
}

function registerEasterEgg() {
    let count = 0;
    const $modalEasterEgg = $('#modalEasterEgg');
    $('.do-not-click-me-several-times').click(() => {
        count += 1;
        if (count === TEAM_SIZE) {
            $modalEasterEgg.modal('show');
        }
    });
    $modalEasterEgg.on('hidden.bs.modal', () => {
        count = 0;
    })
}


function loadAndDisplayLabels($select, name) {
    const $form = $select.closest('.ligneFournisseurArticle');
    const $codeSelect = $form.find(`[name=${name}]`);
    if($select.val()) {
        const [selected] = $select.select2('data');
        if (selected) {
            const {id, code} = selected;
            const [codeSelectSelected] = $codeSelect.select2('data');
            const selectCodeFournisseur = () => {
                let option = new Option(code, id, true, true);
                $codeSelect.append(option).trigger('change');
            }
            if (codeSelectSelected) {
                const {id: codeSelectId} = codeSelectSelected;
                if (id !== codeSelectId) {
                    selectCodeFournisseur();
                }
            }
            else {
                selectCodeFournisseur();
            }
        }
        else {
            $codeSelect.val(null).trigger('change');
        }
    }
}

function loadAndDisplayInfos($select, name) {
    const $form = $select.closest('.ligneFournisseurArticle');
    const $nomSelect = $form.find(`[name=${name}]`);
    if($select.val()) {
        const [selected] = $select.select2('data');
        if (selected) {
            const {id, text} = selected;
            const [nomSelectSelected] = $nomSelect.select2('data');
            const selectNomFournisseur = () => {
                let option = new Option(text, id, true, true);
                $nomSelect.append(option).trigger('change');
            }
            if (nomSelectSelected) {
                const {id: nomSelectId} = nomSelectSelected;
                if (id !== nomSelectId) {
                    selectNomFournisseur();
                }
            }
            else {
                selectNomFournisseur();
            }
        }
    }
    else {
        $nomSelect.val(null).trigger('change');
    }
    let $modal = $select.closest('.modal');

    $select.parent()
        .siblings('.newContent')
        .removeClass('d-none')
        .addClass('d-block');

    $modal.find('span[role="textbox"]').each(function () {
        $(this).parent().css('border-color', '');
    });
}

function scrollToBottom() {
    window.scrollTo(0, document.body.scrollHeight);
}

function registerCopyToClipboard(message = undefined) {
    $(`.copyable`).on(`click`, function () {
        navigator.clipboard
            .writeText($(this)
                .text()
                .trim()
            ).then(() => Flash.add(Flash.INFO, message || `Le texte a bien été copié dans le presse-papiers.`));
    });
}

function openModal(modal) {
    let $modal = modal instanceof jQuery ? modal : $(modal);
    $modal.modal(`show`);
}

function onSelectAll() {
    const $select = $(this).closest(`.input-group`).find(`select`);

    $select.find(`option:not([disabled])`).each(function () {
        $(this).prop(`selected`, true);
    });

    $select.trigger(`change`);
}
