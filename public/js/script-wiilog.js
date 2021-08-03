const MAX_UPLOAD_FILE_SIZE = 10000000;
const MAX_IMAGE_PIXELS = 1000000;
const ALLOWED_IMAGE_EXTENSIONS = ['PNG', 'png', 'JPEG', 'jpeg', 'JPG','jpg','svg'];

const PAGE_PURCHASE_REQUEST = 'rpurchase';
const PAGE_TRANSFER_REQUEST = 'rtransfer';
const PAGE_TRANSFER_ORDER = 'otransfer';
const PAGE_DEM_COLLECTE = 'dcollecte';
const PAGE_DEM_LIVRAISON = 'dlivraison';
const PAGE_HAND = 'handling';
const PAGE_ORDRE_COLLECTE = 'ocollecte';
const PAGE_ORDRE_LIVRAISON = 'olivraison';
const PAGE_PREPA = 'prépa';
const PAGE_ARRIVAGE = 'arrivage';
const PAGE_IMPORT = 'import';
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
const PAGE_RCPT_TRACA = 'reception_traca';
const PAGE_DISPATCHES = 'acheminement';
const PAGE_STATUS = 'status';
const PAGE_EMPLACEMENT = 'emplacement';
const PAGE_URGENCES = 'urgences';
const PAGE_NOTIFICATIONS = 'notifications';
const STATUT_ACTIF = 'disponible';
const STATUT_INACTIF = 'consommé';
const STATUT_EN_TRANSIT = 'en transit';

/** Constants which define a valid barcode */
const BARCODE_VALID_REGEX = /^[A-Za-z0-9_ \/\-]{1,24}$/;

// alert modals config
const AUTO_HIDE_DEFAULT_DELAY = 2000;

// set max date for pickers
const MAX_DATETIME_HTML_INPUT = '2100-12-31T23:59';
const MAX_DATE_HTML_INPUT = '2100-12-31';

$(function () {
    $(document).on('hide.bs.modal', function () {
        $('.select2-container.select2-container--open').remove();
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
});

function registerNotificationChannel() {
    FCM.getToken({vapidKey: "BAtT1Leq2TOoLNyai0HAk5Fv3Tcqk0ps0wEGBwbT8TmHbXNCRU_jOLyvEm4_mc7-nb7XubnEZs-7VkB-ix8FX9A"}).then((token) => {
        $.post(Routing.generate('register_topic', {token}), function() {
            FCM.onMessage((payload) => {
                const $notificationModal = $('.notification-modal');
                const $countFigure = $(`.header-icon.notifications`).find('.icon-figure');
                if (payload.data.image && payload.data.image !== "") {
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
    });
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
    const openModalNew = 'new';
    const openModalEdit = 'edit';
    if (query["open-modal"] === openModalNew
        || query["open-modal"] === openModalEdit) {
        if (query["open-modal"] === openModalNew) {
            const $modal = $('[data-modal-type="new"]').first();
            clearModal($modal)
            $modal.modal("show");
        } else { // edit
            const $openModal = $(`.open-modal-edit`);
            $openModal.data('id', query['modal-edit-id']);
            $openModal.trigger('click');
            delete query['modal-edit-id'];
        }
        delete query["open-modal"];
        SetRequestQuery(query);
    }
}

//DELETE
function deleteRow(button, modal, submit) {
    let id = button.data('id');
    modal.find(submit).attr('value', id);
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
        $('.list-multiple').select2();
    }, 'json');
}


//MODIFY
/**
 * La fonction modifie les valeurs d'une modale modifier avec les valeurs data-attibute.
 * Ces valeurs peuvent être trouvées dans datatableLigneArticleRow.html.twig
 *
 * @param {Document} button
 * @param {string} path le chemin pris pour envoyer les données.
 * @param {Document} modal la modalde modification
 * @param {Document|string} submit le bouton de validation du form pour le edit
 *
 * @param editorToInit
 * @param editor
 * @param setMaxQuantity
 * @param afterLoadingEditModal
 * @param wantsFreeFieldsRequireCheck
 */

function editRow(button, path, modal, submit, editorToInit = false, editor = '.editor-container-edit', setMaxQuantity = false, afterLoadingEditModal = () => {}, wantsFreeFieldsRequireCheck = true) {
    clearFormErrors(modal);
    let id = button.data('id');
    let ref = button.data('ref');
    let json = {id: id, isADemand: 0};
    if (ref !== false) {
        json.ref = ref;
    }

    modal.find(submit).attr('value', id);

    $.post(path, JSON.stringify(json), function (resp) {
        let $modalBody;
        if(modal.find('.to-replace').exists()) {
            $modalBody = modal.find('.to-replace');
        } else {
            $modalBody = modal.find('.modal-body');
        }

        $modalBody.html(resp);

        modal.find('.select2').select2();
        Select2Old.initFree(modal.find('.select2-free'));
        Select2Old.provider(modal.find('.ajax-autocomplete-fournisseur-edit'));
        Select2Old.frequency(modal.find('.ajax-autocomplete-frequency'));
        Select2Old.articleReference(modal.find('.ajax-autocomplete-edit, .ajax-autocomplete-ref'));
        Select2Old.location(modal.find('.ajax-autocomplete-location-edit'));
        Select2Old.carrier(modal.find('.ajax-autocomplete-transporteur-edit'));
        Select2Old.user(modal.find('.ajax-autocomplete-user-edit'));
        modal.find('.list-multiple').select2();
        if (wantsFreeFieldsRequireCheck) {
            toggleRequiredChampsLibres(modal.find('#typeEdit'), 'edit');
        }
        registerNumberInputProtection($modalBody.find('input[type="number"]'));

        if (setMaxQuantity) {
            setMaxQuantityEdit($('#referenceEdit'));
        }

        if (editorToInit) {
            initEditor(editor);
        }

        afterLoadingEditModal(modal);
    }, 'json');

}

function setMaxQuantityEdit(select) {
    let params = {
        refArticleId: select.val(),
    };
    $.post(Routing.generate('get_quantity_ref_article'), params, function (data) {
        let modalBody = select.closest(".modal-body");
        modalBody.find('#quantite').attr('max', data);
    }, 'json');
}

function toggleRadioButton($button) {
    let sel = $button.data('title');
    let tog = $button.data('toggle');
    $('#' + tog).prop('value', sel);
    $('span[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('not-active');
    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('not-active').addClass('active');
}

function toggleRequestType($switch) {
    let type = $switch.val();

    let path = Routing.generate('demande', true);
    let demande = $('#demande');
    let params = JSON.stringify({demande, typeDemande: type});
    let boutonNouvelleDemande = $switch.closest('.modal').find('.boutonCreationDemande');

    $.post(path, params, function (data) {
        if(!data.success) {
            $('.error-msg').html(data.msg);
            boutonNouvelleDemande.removeClass('d-none');
            let pathIndex;
            if (type === 'livraison') {
                pathIndex = Routing.generate('demande_index', false);
            } else if (type === 'collecte') {
                pathIndex = Routing.generate('collecte_index', false);
            } else if (type === 'transfert') {
                pathIndex = Routing.generate('transfer_request_index', false);
            }

            boutonNouvelleDemande.find('#creationDemande').html(
                "<a href=\'" + pathIndex + "\'>Nouvelle demande de " + type + "</a>"
            );

            $switch.closest('.modal').find('.plusDemandeContent').addClass('d-none');
            $switch.closest('.modal').find('.editChampLibre').addClass('d-none');
            $switch.closest('.modal').find('#submitPlusDemande').addClass('d-none');
            $switch.closest('.modal').find('#submitPlusDemandeAndRedirect').addClass('d-none');
        } else {
            ajaxPlusDemandeContent($switch, type);
            $switch.closest('.modal').find('.boutonCreationDemande').addClass('d-none');
            $switch.closest('.modal').find('.plusDemandeContent').removeClass('d-none');
            $switch.closest('.modal').find('.editChampLibre').removeClass('d-none');
            $switch.closest('.modal').find('#submitPlusDemande').removeClass('d-none');
            $switch.closest('.modal').find('#submitPlusDemandeAndRedirect').removeClass('d-none');
            $switch.closest('.modal').find('.error-msg').html('');
        }
    }, 'json');
}

function initEditorInModal(modal) {
    initEditor(modal + ' .editor-container');
}

function initEditor(div) {
    // protection pour éviter erreur console si l'élément n'existe pas dans le DOM
    if ($(div).length) {
        return new Quill(div, {
            modules: {
                toolbar: [
                    [{header: [1, 2, 3, false]}],
                    ['bold', 'italic', 'underline', 'image'],
                    [{'list': 'ordered'}, {'list': 'bullet'}]
                ]
            },
            formats: [
                'header',
                'bold', 'italic', 'underline', 'strike', 'blockquote',
                'list', 'bullet', 'indent', 'link', 'image'
            ],
            theme: 'snow'
        });
    }
    return null;
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

function updateQuantityDisplay($elem) {
    let $modalBody = $elem.closest('.modal-body');
    const $reference = $modalBody.find('.reference');
    const $article = $modalBody.find('.article');
    const $allArticle = $modalBody.find('.article, .emergency-comment');
    let typeQuantite = $modalBody.find('.type_quantite').val();

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
                .removeClass('data')

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
            $('.list-multiple').select2();
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

    let inputs = $modal.find('.modal-body').find(".data,.data-array");
    // on vide tous les inputs (sauf les disabled et les input hidden)
    inputs.each(function () {
        if ($(this).attr('disabled') !== 'disabled' && $(this).attr('type') !== 'hidden' && !$(this).hasClass('no-clear')) {
            if ($(this).hasClass('needs-default')) {
                $(this).val($(this).data('init'));
            } else {
                $(this).val("");
            }
        }
        // on enlève les classes is-invalid
        $(this).removeClass('is-invalid');
        $(this).next().find('.select2-selection').removeClass('is-invalid');
        //TODO protection ?
    });
    // on vide tous les select2
    let selects = $modal
        .find('.modal-body')
        .find('.ajax-autocomplete, .ajax-autocomplete-location, .ajax-autocomplete-fournisseur, .ajax-autocomplete-transporteur, .select2, .select2-free, .ajax-autocomplete-user, [data-s2-initialized]');
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

function saveFilters(page, tableSelector, callback) {
    let path = Routing.generate('filter_sup_new');

    const $filterDateMin = $('.filter-date-min');
    const $filterDateMax = $('.filter-date-max');
    const $filterDateExpected = $('.filter-date-expected');
    const $filterDateMinPicker = $filterDateMin.data("DateTimePicker");
    const $filterDateMaxPicker = $filterDateMax.data("DateTimePicker");
    const $filterDateExpectedPicker = $filterDateExpected.data("DateTimePicker");

    if ($filterDateMinPicker) {
        $filterDateMinPicker.format('YYYY-MM-DD');
    }
    if ($filterDateMaxPicker) {
        $filterDateMaxPicker.format('YYYY-MM-DD');
    }
    if ($filterDateExpectedPicker) {
        $filterDateExpectedPicker.format('YYYY-MM-DD');
    }

    const valFunction = {
        'filter-input': ($input) => ($input.val() || '').trim(),
        'filter-select2': ($input) => ($input.select2('data') || [])
                .filter(({id, text}) => (id.trim() && text.trim()))
                .map(({id, text}) => ({id, text})),
        'filter-checkbox': ($input) => $input.is(':checked')
    };

    let params = {
        page,
        ...(Object.keys(valFunction).reduce((acc, key) => {
            const $fields = $('.filters-container').find(`.${key}`);
            const values = {};
            $fields.each(function () {
                const $elem = $(this);
                values[$elem.attr('name')] = valFunction[key]($elem);
            });

            return ({
                ...acc,
                ...values
            })
        }, {}))
    };

    if ($filterDateMinPicker) {
        $filterDateMinPicker.format('DD/MM/YYYY');
    }
    if ($filterDateMaxPicker) {
        $filterDateMaxPicker.format('DD/MM/YYYY');
    }
    if ($filterDateExpectedPicker) {
        $filterDateExpectedPicker.format('DD/MM/YYYY');
    }
    $.post(path, JSON.stringify(params), function (response) {
        if (response) {
            if (callback) {
                callback();
            }
            if (tableSelector) {
                $(tableSelector).each(function() {
                    const $table = $(this);
                    if ($table && $table.DataTable && $table.is(`:visible`)) {
                        $table.DataTable().draw();
                    }
                })
            }
        } else {
            showBSAlert('Veuillez saisir des filtres corrects (pas de virgule ni de deux-points).', 'danger');
        }
    }, 'json');
}

function checkAndDeleteRow(icon, modalName, route, submit, getParams = null) {
    let $modalBody = $(modalName).find('.modal-body');
    let $submit = $(submit);
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
        if (resp.delete == false) {
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
        $.post(path, JSON.stringify(params), function (response) {
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

function initDateTimePicker(dateInput = '#dateMin, #dateMax, #expectedDate', format = 'DD/MM/YYYY', minDate = false, defaultHours = null, defaultMinutes = null, disableDates = null) {
    let options = {
        format: format,
        useCurrent: false,
        locale: moment.locale('fr'),
        showTodayButton: true,
        showClear: true,
        icons: {
            clear: 'fas fa-trash',
        },
        tooltips: {
            today: 'Aujourd\'hui',
            clear: 'Supprimer',
            selectMonth: 'Choisir le mois',
            selectYear: 'Choisir l\'année',
            selectDecade: 'Choisir la décennie',
        }
    };
    if (disableDates) {
        options.disabledDates = disableDates;
    }
    if (minDate) {
        options.minDate = moment().hours(0).minutes(0).seconds(0);
    }
    if (defaultHours !== null && defaultMinutes !== null) {
        options.defaultDate = moment().hours(defaultHours).minutes(defaultMinutes);
    }

    $(dateInput).data("dtp-initialized", "true");
    $(dateInput).datetimepicker(options);
}


function warningEmptyDatesForCsv() {
    showBSAlert('Veuillez saisir des dates dans le filtre en haut de page.', 'danger');
    $('#dateMin, #dateMax').addClass('is-invalid');
    $('.is-invalid').on('click', function () {
        $(this).parent().find('.is-invalid').removeClass('is-invalid');
    });
}

function displayFiltersSup(data) {
    data.forEach(function (element) {
        switch (element.field) {
            case 'utilisateurs':
            case 'declarants':
            case 'providers':
            case 'reference':
            case 'statut':
            case 'carriers':
            case 'emplacement':
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
                    }
                    else {
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
            case 'customs':
            case 'frozen':
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
                const dateValue = moment(element.value, sourceFormat).format('DD/MM/YYYY');
                if ($fieldDate.data("DateTimePicker")) {
                    $fieldDate.data("DateTimePicker").date(dateValue);
                } else {
                    $fieldDate.val(dateValue);
                }
                break;

            default:
                const $fieldWithId = $('#' + element.field);
                const $field = $fieldWithId.length > 0
                    ? $fieldWithId
                    : $('.filters-container').find(`[name="${element.field}"]`);
                $field.val(element.value);
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
            ...buttonConfig.map(({action, ...config}) => {
                return $('<div/>', {class: 'col-auto'}).append($('<button/>', {
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

function managePrintButtonTooltip(active, $button) {
    if ($button) {
        $button.tooltip(
            active ? undefined : 'dispose'
        )
    }
}

function initOnTheFlyCopies($elems) {
    $elems.each(function () {
        $(this).keyup(function () {
            $(this).closest('.form-group').find('.copiedOnTheFly').val($(this).val());
        })
    });
}

function saveExportFile(routeName, needsDateFilters = true, routeParam = {}) {
    const $spinner = $('#spinner');
    loadSpinner($spinner);

    const path = Routing.generate(routeName, routeParam, true);

    const data = {};
    $('.filterService input').each(function () {
        const $input = $(this);
        const name = $input.attr('name');
        const val = $input.val();
        if (name && val) {
            data[name] = val;
        }
    });

    if ((data.dateMin && data.dateMax) || !needsDateFilters) {
        if (data.dateMin && data.dateMax) {
            data.dateMin = moment(data.dateMin, 'DD/MM/YYYY').format('YYYY-MM-DD');
            data.dateMax = moment(data.dateMax, 'DD/MM/YYYY').format('YYYY-MM-DD');
        }

        const dataKeys = Object.keys(data);

        const joinedData = dataKeys
            .map((key) => `${encodeURIComponent(key)}=${encodeURIComponent(data[key])}`)
            .join('&');

        window.location.href = `${path}?${joinedData}`;
        hideSpinner($spinner);
    }
    else {
        warningEmptyDatesForCsv();
        hideSpinner($spinner);
    }
}

function fillDemandeurField($modal) {
    const $operatorSelect = $modal.find('.select2-declarant');
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

function registerNumberInputProtection($inputs) {
    const forbiddenChars = [
        "e",
        "E",
        "+",
        "-"
    ];

    $inputs.on("keydown", function (e) {
        if (forbiddenChars.includes(e.key)) {
            e.preventDefault();
        }
    });
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
    const $modal = $select.closest('.modal');
    toggleRequiredChampsLibres($select, 'create');
    const $freeFieldsContainer = $modal.find('.free-fields-container');

    toggleRequiredChampsLibres($select, 'create', $freeFieldsContainer);
    typeChoice($select, $freeFieldsContainer);

    const type = parseInt($select.val());

    const $selectStatus = $modal.find('select[name="status"]');
    $selectStatus.find('option[data-type-id!="' + type + '"]').addClass('d-none');
    $selectStatus.val(null).trigger('change');

    const $errorEmptyStatus = $selectStatus.siblings('.error-empty-status');
    $errorEmptyStatus.addClass('d-none');

    if(!type) {
        $selectStatus.removeClass('d-none');
        $selectStatus.prop('disabled', true);
    } else {
        const $correspondingStatuses = $selectStatus.find('option[data-type-id="' + type + '"]');
        $selectStatus.removeAttr('disabled');
        $correspondingStatuses.removeClass('d-none');
        const defaultStatuses = JSON.parse($selectStatus.siblings('input[name="defaultStatuses"]').val() || '{}');

        if ($correspondingStatuses.length > 1) {
            $selectStatus.removeClass('d-none');
            if (defaultStatuses[type]) {
                $selectStatus.val(defaultStatuses[type]);
            } else if ($correspondingStatuses.length === 1) {
                $selectStatus.val($correspondingStatuses[0].value);
            } else {
                $selectStatus.removeAttr('disabled');
            }
        } else if($correspondingStatuses.length === 1) {
            $selectStatus.val($modal.find('select[name="status"] option:not(.d-none):first').val())
                .removeClass('d-none')
                .prop('disabled', true);
        } else if (type) {
            $errorEmptyStatus.removeClass('d-none');
            $selectStatus.addClass('d-none');
        }
    }
}

function getBSAlertModal() {
    return $('#alert-modal');
}

function updateImagePreview(preview, upload, $title = null, $delete = null, $callback = null) {
    let $upload = $(upload)[0];

    $(upload).change(() => {
        if ($upload.files && $upload.files[0]) {
            let fileNameWithExtension = $upload.files[0].name.split('.');
            let extension = fileNameWithExtension[fileNameWithExtension.length - 1];

            if ($upload.files[0].size < MAX_UPLOAD_FILE_SIZE) {
                if (ALLOWED_IMAGE_EXTENSIONS.indexOf(extension) !== -1) {
                    if ($title) {
                        $title.text(fileNameWithExtension.join('.').substr(0, 5) + '...');
                        $title.attr('title', fileNameWithExtension.join('.'));
                        if($title.siblings('input[name=titleComponentLogo]').length > 0) {
                            $title.siblings('input[name=titleComponentLogo]').last().val($upload.files[0].name);
                        }
                    }

                    let reader = new FileReader();
                    reader.onload = function (e) {
                        let image = new Image();

                        image.onload = function() {
                            const pixels = image.height * image.width;
                            if (pixels <= MAX_IMAGE_PIXELS) {
                                if ($callback) {
                                    $callback($upload);
                                }
                                $(preview)
                                    .attr('src', e.target.result)
                                    .removeClass('d-none');
                                if ($delete) {
                                    $delete.removeClass('d-none');
                                }
                            } else {
                                showBSAlert('Veuillez choisir une image ne faisant pas plus de 1000x1000.', 'danger');
                            }
                        };

                        image.src = e.target.result;
                    };

                    reader.readAsDataURL($upload.files[0]);
                } else {
                    showBSAlert('Veuillez choisir une image valide (png, jpeg, jpg, svg).', 'danger')
                }
            } else {
                showBSAlert('La taille du fichier est supérieure à 10 mo.', 'danger')
            }
        }
    })
}
