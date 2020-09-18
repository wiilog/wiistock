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
    initSelect2($('#locationArrivageDest'));
    initFreeSelect2($('select[name="businessUnit"]'));
    ajaxAutoCompleteEmplacementInit($('.ajax-autocomplete-location'));
    ajaxAutoCompleteTransporteurInit($('.ajax-autocomplete-transporteur'));
    initDisplaySelect2('#receptionLocation', '#receptionLocationValue');
    $('#receptionLocation').on('change', editDefaultLocationValue);
    $('#logo').change(function() {
        fileToImagePreview($(this));
    });
    // config tableau de bord : emplacements
    initSelect2ValuesForDashboard();
    $('#locationArrivageDest').on('change', editArrivageDestination);
    $('select[name="businessUnit"]').on('change', editBusinessUnit);
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

function initSelect2ValuesForDashboard() {
    initDisplaySelect2Multiple('#locationToTreat', '#locationToTreatValue');
    initDisplaySelect2Multiple('#locationWaitingDock', '#locationWaitingDockValue');
    initDisplaySelect2Multiple('#locationWaitingAdmin', '#locationWaitingAdminValue');
    initDisplaySelect2Multiple('#locationAvailable', '#locationAvailableValue');
    initDisplaySelect2Multiple('#locationDropZone', '#locationDropZoneValue');
    initDisplaySelect2Multiple('#locationLitiges', '#locationLitigesValue');
    initDisplaySelect2Multiple('#locationUrgences', '#locationUrgencesValue');
    initDisplaySelect2Multiple('#locationsFirstGraph', '#locationsFirstGraphValue');
    initDisplaySelect2Multiple('#locationsSecondGraph', '#locationsSecondGraphValue');
    initDisplaySelect2Multiple('#locationArrivageDest', '#locationArrivageDestValue');
    initDisplaySelect2Multiple('#locationDemandeLivraison','#locationDemandeLivraisonValue');
    initDisplaySelect2Multiple('#packaging1','#packagingLocation1');
    initDisplaySelect2Multiple('#packaging2','#packagingLocation2');
    initDisplaySelect2Multiple('#packaging3','#packagingLocation3');
    initDisplaySelect2Multiple('#packaging4','#packagingLocation4');
    initDisplaySelect2Multiple('#packaging5','#packagingLocation5');
    initDisplaySelect2Multiple('#packaging6','#packagingLocation6');
    initDisplaySelect2Multiple('#packaging7','#packagingLocation7');
    initDisplaySelect2Multiple('#packaging8','#packagingLocation8');
    initDisplaySelect2Multiple('#packagingRPA','#packagingLocationRPA');
    initDisplaySelect2Multiple('#packagingLitige','#packagingLocationLitige');
    initDisplaySelect2Multiple('#packagingUrgence','#packagingLocationUrgence');
    initDisplaySelect2Multiple('#packagingDSQR','#packagingLocationDSQR');
    initDisplaySelect2Multiple('#packagingDestinationGT','#packagingLocationDestinationGT');
    initDisplaySelect2Multiple('#packagingOrigineGT','#packagingLocationOrigineGT');
    initDisplaySelect2Multiple('#carrierDock', '#carrierDockValue');
}

function updateToggledParam(switchButton) {
    let params = {
        val: switchButton.is(':checked'),
        param: switchButton.data('param'),
    };
    $.post(Routing.generate('toggle_params', true), JSON.stringify(params), function (resp) {
        if (resp) {
            alertSuccessMsg('La modification du paramétrage a bien été prise en compte.');
        } else {
            alertErrorMsg('Une erreur est survenue lors de la modification du paramétrage.');
        }
    }, 'json');
}

function ajaxMailerServer() {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            alertSuccessMsg('La configuration du serveur mail a bien été mise à jour.');
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
    let $fileInput = $('#logo');
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
            alertSuccessMsg('La configuration des étiquettes a bien été mise à jour.');
            $('.blChosen').text("\"" + response['param-cl-etiquette'] + "\"");
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
            alertSuccessMsg('Le préfixage des noms de demandes a bien été mis à jour.');
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
    alertSuccessMsg('Mise à jour de votre personnalisation des libellés : merci de patienter.', false);
    loadSpinner($spinner);
    $.post(path, JSON.stringify(data), (resp) => {
        $('html,body').animate({scrollTop: 0});
        if (resp) {
            location.reload();
        } else {
            hideSpinner($spinner);
            alertErrorMsg('Une erreur est survenue lors de la personnalisation des libellés.');
        }
    });
}

function ajaxEncodage() {
    $.post(Routing.generate('save_encodage'), JSON.stringify($('select[name="param-type-encodage"]').val()), function () {
        alertSuccessMsg('Mise à jour de vos préférences d\'encodage réussie.');
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
            alertSuccessMsg("L'emplacement de réception a bien été mis à jour.");
        } else {
            alertErrorMsg("Une erreur est survenue lors de la mise à jour de l'emplacement de réception.");
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
            alertSuccessMsg("La configuration des tableaux de bord a bien été mise à jour.");
        } else {
            alertErrorMsg("Une erreur est survenue lors de la mise à jour de la configuration des tableaux de bord.");
        }
    });
}

function editStatusArrivage($select) {
    let path = Routing.generate('edit_status_arrivage',true);
    const param = {
        value: $select.val()
    };

    $.post(path, param, (resp) => {
        if (resp) {
            alertSuccessMsg("Le statut de l'arrivage par défaut a bien été mis à jour.");
        } else {
            alertErrorMsg("Une erreur est survenue lors de la mise à jour du statut par défaut de l'arrivage.");
        }
    });
}

function editFont() {
    let path = Routing.generate('edit_font', true);
    let param = {
        value: $('select[name="param-font-family"]').val()
    };


    alertSuccessMsg("Mise à jour de la police en cours. Veuillez patienter.");
    $.post(path, param, (resp) => {
        if (resp) {
            location.reload();
        } else {
            alertErrorMsg("Une erreur est survenue lors de la mise à jour du choix de la police.");
        }
    });
}

function editArrivageDestination() {
    $.post(Routing.generate('set_arrivage_default_dest'), $(this).val(), (resp) => {
        if (resp) {
            alertSuccessMsg("la destination des arrivages a bien été mise à jour.");
        } else {
            alertErrorMsg("Une erreur est survenue lors de la mise à jour de la destination des arrivages.");
        }
    });
}

function editBusinessUnit() {
    console.log($(this).val());
    $.post(Routing.generate('set_business_unit'), {value: $(this).val()}, (resp) => {
        if (resp) {
            alertSuccessMsg("La liste business unit a bien été mise à jour.");
        } else {
            alertErrorMsg("Une erreur est survenue lors de la mise à jour de la liste business unit.");
        }
    });
}

function editDemandeLivraisonDestination($select) {
    $.post(Routing.generate('edit_demande_livraison_default_dest'), $select.val(), (resp) => {
        if (resp) {
            alertSuccessMsg("La destination des demandes de livraison a bien été mise à jour.");
        } else {
            alertErrorMsg("Une erreur est survenue lors de la mise à jour de la destination des demandes de livraison.");
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
            alertSuccessMsg("Les statuts de réception ont bien été mis à jour.");
        } else {
            alertErrorMsg("Une erreur est survenue lors de la mise à jour des statuts de réception.");
        }
    });
}


function fileToImagePreview($fileInput) {
    if ($fileInput[0].files && $fileInput[0].files[0]) {
        let fileNameWithExtension = $fileInput[0].files[0].name.split('.');
        let extension = fileNameWithExtension[fileNameWithExtension.length - 1];

        if (allowedLogoExtensions.indexOf(extension) !== -1) {
            let reader = new FileReader();
            reader.onload = function(e) {
                let $chosenLogo = $('#chosenLogo');
                $chosenLogo.attr('src', e.target.result);
                $chosenLogo.removeClass('d-none');
            };
            reader.readAsDataURL($fileInput[0].files[0]);
        } else {
            alertErrorMsg('Veuillez choisir une image valide (png, jpeg, jpg).', true)
        }
    }
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
                alertSuccessMsg(resp.text);
            } else {
                alertErrorMsg(resp.text);
            }
        });
    } else {
        alertErrorMsg('Veuillez sélectionner une date valide.');
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
                alertSuccessMsg(resp.message);
            } else {
                alertErrorMsg(resp.message);
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
        $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_WAYBILL_LOCATION_TO', val: $('[name="waybillLocationTo"]').val()}))
    ])
        .then((res) => {
            console.log(res);
            if (res.every((success) => success)) {
                alertSuccessMsg("Les paramétrages d'acheminements ont bien été mis à jour.");
            } else {
                alertErrorMsg("Une erreur est survenue lors de la mise à jour des paramétrages d'acheminements.");
            }
        });
}
