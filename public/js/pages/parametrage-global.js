let allowedLogoExtensions = ['PNG', 'png', 'JPEG', 'jpeg', 'JPG','jpg'];
let pathDays = Routing.generate('days_param_api', true);
let disabledDates = [];
let tableDaysConfig = {
    ajax: {
        "url": pathDays,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
        {"data": 'Day', 'title': 'Jour'},
        {"data": 'Worked', 'title': 'Travaillé'},
        {"data": 'Times', 'title': 'Horaires de travail'},
        {"data": 'Order', 'title': 'Ordre'},
    ],
    order: [
        [4, 'asc']
    ],
    rowConfig: {
        needsRowClickAction: true,
    }
};

let tableDays = initDataTable('tableDays', tableDaysConfig);
let workFreeDaysTable;

let modalEditDays = $('#modalEditDays');
let submitEditDays = $('#submitEditDays');
let urlEditDays = Routing.generate('days_edit', true);
InitModal(modalEditDays, submitEditDays, urlEditDays, {tables: [tableDays]});

$(function () {
    Select2.init($('#locationArrivageDest'));
    Select2.init($('#listNaturesColis'));
    Select2.initFree($('select[name="businessUnit"]'));
    Select2.initFree($('select[name="dispatchEmergencies"]'));
    Select2.location($('.ajax-autocomplete-location'));
    Select2.carrier($('.ajax-autocomplete-transporteur'));
    Select2.initValues($('#receptionLocation'), $('#receptionLocationValue'));
    $('#receptionLocation').on('change', editDefaultLocationValue);

    updateImagePreview('#preview-label-logo', '#upload-label-logo');
    updateImagePreview('#preview-delivery-note-logo', '#upload-delivery-note-logo');
    updateImagePreview('#preview-waybill-logo', '#upload-waybill-logo');

    // config tableau de bord : emplacements
    initValuesForDashboard();
    $('#locationArrivageDest').on('change', editArrivageDestination);
    $('#locationDemandeLivraison').on('change', function() {
        editDemandeLivraisonDestination($(this));
    });
    // config tableau de bord : transporteurs

    const inputWorkFreeDayAlreadyAdd = JSON.parse($('#workFreeDays input[type="hidden"][name="already-work-free-days"]').val());
    disabledDates = inputWorkFreeDayAlreadyAdd.map((dateStr) => moment(dateStr, 'YYYY-MM-DD'));
    initDateTimePicker(
        '#workFreeDays input[name="newWorkFreeDay"]',
        "DD/MM/YYYY",
        false,
        null,
        null,
        disabledDates
    );

    let tableNonWorkedDaysConfig = {
        ajax: {
            "url": Routing.generate('workFreeDays_table_api', true),
            "type": "GET"
        },
        columns: [
            { "data": 'actions', className: 'noVis', orderable: false},
            { "data": 'day', 'title': 'Jour', orderable: false },
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
        order: [],
    };
    workFreeDaysTable = initDataTable('tableWorkFreeDays', tableNonWorkedDaysConfig);
});

function initValuesForDashboard() {
    Select2.initValues($('#locationToTreat'), $('#locationToTreatValue'));
    Select2.initValues($('#locationWaitingDock'), $( '#locationWaitingDockValue'));
    Select2.initValues($('#locationWaitingAdmin'), $( '#locationWaitingAdminValue'));
    Select2.initValues($('#locationAvailable'), $( '#locationAvailableValue'));
    Select2.initValues($('#locationDropZone'), $( '#locationDropZoneValue'));
    Select2.initValues($('#locationLitiges'), $( '#locationLitigesValue'));
    Select2.initValues($('#locationUrgences'), $( '#locationUrgencesValue'));
    Select2.initValues($('#locationsFirstGraph'), $( '#locationsFirstGraphValue'));
    Select2.initValues($('#locationsSecondGraph'), $( '#locationsSecondGraphValue'));
    Select2.initValues($('#locationArrivageDest'), $( '#locationArrivageDestValue'));
    Select2.initValues($('#locationDemandeLivraison'), $('#locationDemandeLivraisonValue'));
    Select2.initValues($('#packaging1'), $('#packagingLocation1'));
    Select2.initValues($('#packaging2'), $('#packagingLocation2'));
    Select2.initValues($('#packaging3'), $('#packagingLocation3'));
    Select2.initValues($('#packaging4'), $('#packagingLocation4'));
    Select2.initValues($('#packaging5'), $('#packagingLocation5'));
    Select2.initValues($('#packaging6'), $('#packagingLocation6'));
    Select2.initValues($('#packaging7'), $('#packagingLocation7'));
    Select2.initValues($('#packaging8'), $('#packagingLocation8'));
    Select2.initValues($('#packaging9'), $('#packagingLocation9'));
    Select2.initValues($('#packaging10'), $('#packagingLocation10'));
    Select2.initValues($('#packagingRPA'), $('#packagingLocationRPA'));
    Select2.initValues($('#packagingLitige'), $('#packagingLocationLitige'));
    Select2.initValues($('#packagingKitting'), $( '#packagingLocationKitting'));
    Select2.initValues($('#packagingUrgence'), $('#packagingLocationUrgence'));
    Select2.initValues($('#packagingDSQR'), $('#packagingLocationDSQR'));
    Select2.initValues($('#packagingDestinationGT'), $('#packagingLocationDestinationGT'));
    Select2.initValues($('#packagingOrigineGT'), $('#packagingLocationOrigineGT'));
    Select2.initValues($('#carrierDock'), $( '#carrierDockValue'));
}

function updateToggledParam(switchButton) {
    let params = {
        val: switchButton.is(':checked'),
        param: switchButton.data('param'),
    };
    $.post(Routing.generate('toggle_params', true), JSON.stringify(params), function (resp) {
        if (resp) {
            showBSAlert('La modification du paramétrage a bien été prise en compte.', 'success');
        } else {
            showBSAlert('Une erreur est survenue lors de la modification du paramétrage.', 'danger');
        }
    }, 'json');
}

function ajaxMailerServer() {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            showBSAlert('La configuration du serveur mail a bien été mise à jour.', 'success');
        }
    }
    let data = $('#mailerServerForm').find('.data');
    let json = {};
    data.each(function () {
        let val = $(this).val();
        let name = $(this).attr("name");
        json[name] = val;
    })
    let Json = JSON.stringify(json);
    let path = Routing.generate('ajax_mailer_server', true);
    xhttp.open("POST", path, true);
    xhttp.send(Json);
}

function ajaxDims() {
    let $fileInput = $('#upload-label-logo');
    let data = new FormData();
    let dataInputs = $('#dimsForm').find('.data');
    dataInputs.each(function () {
        let val = $(this).attr('type') === 'checkbox' ? $(this).is(':checked') : $(this).val();
        let name = $(this).attr("name");
        data.append(name, val);
    });
    if ($fileInput[0].files && $fileInput[0].files[0]) {
        data.append('logo', $fileInput[0].files[0]);
    }
    $.ajax({
        url: Routing.generate('ajax_dimensions_etiquettes', true),
        data: data,
        type: 'post',
        contentType: false,
        processData: false,
        cache: false,
        dataType: 'json',
        success: (response) => {
            showBSAlert('La configuration des étiquettes a bien été mise à jour.', 'success');
            $('.blChosen').text("\"" + response['param-cl-etiquette'] + "\"");
        }
    });
}

function ajaxDocuments() {
    let $deliveryNote = $('[name="logo-delivery-note"]');
    let $waybill = $('[name="logo-waybill"]');

    let data = new FormData();

    if ($deliveryNote[0].files && $deliveryNote[0].files[0]) {
        data.append('logo-delivery-note', $deliveryNote[0].files[0]);
    }

    if ($waybill[0].files && $waybill[0].files[0]) {
        data.append('logo-waybill', $waybill[0].files[0]);
    }

    $.ajax({
        url: Routing.generate('ajax_documents', true),
        data: data,
        type: 'post',
        contentType: false,
        processData: false,
        cache: false,
        dataType: 'json',
        success: () => {
            showBSAlert('La configuration des étiquettes a bien été mise à jour.', 'success');
        }
    });
}

function updatePrefixDemand() {
    let prefixe = $('#prefixeDemande').val();
    let typeDemande = $('#typeDemandePrefixageDemande').val();

    let path = Routing.generate('ajax_update_prefix_demand', true);
    let params = JSON.stringify({prefixe: prefixe, typeDemande: typeDemande});

    let msg = '';
    if (typeDemande === 'aucunPrefixe') {
        $('#typeDemandePrefixageDemande').addClass('is-invalid');
        msg += 'Veuillez sélectionner un type de demande.';
    } else {
        $.post(path, params, () => {
            $('#typeDemandePrefixageDemande').removeClass('is-invalid');
            showBSAlert('Le préfixage des noms de demandes a bien été mis à jour.', 'success');
        });
    }
    $('.error-msg').html(msg);
}

function getPrefixDemand(select) {
    let typeDemande = select.val();

    let path = Routing.generate('ajax_get_prefix_demand', true);
    let params = JSON.stringify(typeDemande);

    $.post(path, params, function (data) {
        $('#prefixeDemande').val(data);
    }, 'json');
}

function saveTranslations() {
    let $inputs = $('#translation').find('.translate');
    let data = [];
    $inputs.each(function () {
        let name = $(this).attr('name');
        let val = $(this).val();
        data.push({id: name, val: val});
    });

    let path = Routing.generate('save_translations');
    const $spinner = $('#spinnerSaveTranslations');
    showBSAlert('Mise à jour de votre personnalisation des libellés : merci de patienter.', 'success', false);
    loadSpinner($spinner);
    $.post(path, JSON.stringify(data), (resp) => {
        $('html,body').animate({scrollTop: 0});
        if (resp) {
            location.reload();
        } else {
            hideSpinner($spinner);
            showBSAlert('Une erreur est survenue lors de la personnalisation des libellés.', 'danger');
        }
    });
}

function ajaxEncodage() {
    $.post(Routing.generate('save_encodage'), JSON.stringify($('select[name="param-type-encodage"]').val()), function () {
        showBSAlert('Mise à jour de vos préférences d\'encodage réussie.', 'success');
    });
}

function editDefaultLocationValue() {
    let path = Routing.generate('edit_reception_location', true);
    const locationValue = $(this).val();
    let param = {
        value: locationValue
    };

    $.post(path, param, (resp) => {
        if (resp) {
            showBSAlert("L'emplacement de réception a bien été mis à jour.", 'success');
        } else {
            showBSAlert("Une erreur est survenue lors de la mise à jour de l'emplacement de réception.", 'danger');
        }
    });
}

function editDashboardParams() {
    let path = Routing.generate('edit_dashboard_params', true);
    let data = $('#paramDashboard').find('.data');

    let param = {};
    data.each(function () {
        let val = $(this).val();
        let name = $(this).attr("id");
        param[name] = val;
    });

    $.post(path, param, (resp) => {
        if (resp) {
            showBSAlert("La configuration des tableaux de bord a bien été mise à jour.", 'success');
        } else {
            showBSAlert("Une erreur est survenue lors de la mise à jour de la configuration des tableaux de bord.", 'danger');
        }
    });
}

function editFont() {
    let path = Routing.generate('edit_font', true);
    let param = {
        value: $('select[name="param-font-family"]').val()
    };


    showBSAlert("Mise à jour de la police en cours. Veuillez patienter.", 'success', false);
    $.post(path, param, (resp) => {
        if (resp) {
            location.reload();
        } else {
            showBSAlert("Une erreur est survenue lors de la mise à jour du choix de la police.", 'danger');
        }
    });
}

function editArrivageDestination() {
    $.post(Routing.generate('set_arrivage_default_dest'), $(this).val(), (resp) => {
        if (resp) {
            showBSAlert("la destination des arrivages a bien été mise à jour.", 'success');
        } else {
            showBSAlert("Une erreur est survenue lors de la mise à jour de la destination des arrivages.", 'danger');
        }
    });
}

function editDemandeLivraisonDestination($select) {
    $.post(Routing.generate('edit_demande_livraison_default_dest'), $select.val(), (resp) => {
        if (resp) {
            showBSAlert("La destination des demandes de livraison a bien été mise à jour.", 'success');
        } else {
            showBSAlert("Une erreur est survenue lors de la mise à jour de la destination des demandes de livraison.", 'danger');
        }
    });
}

function editReceptionStatus() {
    let path = Routing.generate('edit_status_receptions');
    let $inputs = $('#paramReceptions').find('.status');

    let param = {};
    $inputs.each(function () {
        let name = $(this).attr('name');
        let val = $(this).val();
        param[name] = val;
    });

    $.post(path, param, (resp) => {
        if (resp) {
            showBSAlert("Les statuts de réception ont bien été mis à jour.", 'success');
        } else {
            showBSAlert("Une erreur est survenue lors de la mise à jour des statuts de réception.", 'danger');
        }
    });
}


function updateImagePreview(preview, upload) {
    let $upload = $(upload)[0];

    $(upload).change(() => {
        if ($upload.files && $upload.files[0]) {
            let fileNameWithExtension = $upload.files[0].name.split('.');
            let extension = fileNameWithExtension[fileNameWithExtension.length - 1];

            if (allowedLogoExtensions.indexOf(extension) !== -1) {
                let reader = new FileReader();
                reader.onload = function(e) {
                    $(preview)
                        .attr('src', e.target.result)
                        .removeClass('d-none');
                };

                reader.readAsDataURL($upload.files[0]);
            } else {
                showBSAlert('Veuillez choisir une image valide (png, jpeg, jpg).', 'danger')
            }
        }
    })
}

function addWorkFreeDay($button) {
    const $input = $button.siblings('input[name="newWorkFreeDay"]');
    if ($input.val()) {
        const date = moment($input.val(), 'DD/MM/YYYY').format('YYYY-MM-DD');
        let path = Routing.generate('workFreeDay_new', true);
        $.post(path, {date}, (resp) => {
            if (resp.success) {
                let datetimeMoment = moment(date);
                disabledDates.push(datetimeMoment);
                $input.data('DateTimePicker').disabledDates(disabledDates);
                $input.val('');

                workFreeDaysTable.ajax.reload();
                showBSAlert(resp.text, 'success');
            } else {
                showBSAlert(resp.text, 'danger');
            }
        });
    } else {
        showBSAlert('Veuillez sélectionner une date valide.', 'danger');
    }
}

function deleteWorkFreeDay(id, date) {
    $.ajax({
        url: Routing.generate('workFreeDay_delete', true),
        type: 'DELETE',
        data: {id},
        success: (resp) => {
            if (resp.success) {
                const $input = $('#workFreeDays input[name="newWorkFreeDay"]');
                let datetimeToRemove = moment(date);
                const indexOfDeleted = disabledDates.findIndex((dateSaved) => datetimeToRemove.isSame(dateSaved.format('YYYY-MM-DD')));
                if (indexOfDeleted > -1) {
                    disabledDates.splice(indexOfDeleted, 1);
                    $input.data('DateTimePicker').disabledDates(disabledDates);
                }
                workFreeDaysTable.ajax.reload();
                showBSAlert(resp.message, 'success');
            } else {
                showBSAlert(resp.message, 'danger');
            }
        }
    });
}

function saveDispatchesParam() {
    Promise.all([
        $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_WAYBILL_CARRIER', val: $('[name="waybillCarrier"]').val()})),
        $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_WAYBILL_CONSIGNER', val: $('[name="waybillConsigner"]').val()})),
        $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_WAYBILL_RECEIVER', val: $('[name="waybillReceiver"]').val()})),
        $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_WAYBILL_LOCATION_FROM', val: $('[name="waybillLocationFrom"]').val()})),
        $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_WAYBILL_LOCATION_TO', val: $('[name="waybillLocationTo"]').val()})),
        $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL', val: $('[name="waybillContactPhoneMail"]').val()})),
        $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_WAYBILL_CONTACT_NAME', val: $('[name="waybillContactName"]').val()}))
    ])
        .then((res) => {
            if (res.every((success) => success)) {
                showBSAlert("Les paramétrages d'acheminements ont bien été mis à jour.", 'success');
            } else {
                showBSAlert("Une erreur est survenue lors de la mise à jour des paramétrages d'acheminements.", 'danger');
            }
        });
}
