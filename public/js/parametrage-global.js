let pathDays = Routing.generate('days_param_api', true);
let tableDays = $('#tableDays').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathDays,
        "type": "POST"
    },
    columns:[
        { "data": 'Day', 'title' : 'Jour' },
        { "data": 'Worked', 'title' : 'Travaillé' },
        { "data": 'Times', 'title' : 'Horaires de travail' },
        { "data": 'Order', 'title' : 'Ordre' },
        { "data": 'Actions', 'title' : 'Actions' },
    ],
    order: [
        [3, 'asc']
    ],
    columnDefs: [
        {
            'targets': [3],
            'visible': false
        }
    ],
});

let modalEditDays = $('#modalEditDays');
let submitEditDays = $('#submitEditDays');
let urlEditDays = Routing.generate('days_edit', true);
InitialiserModal(modalEditDays, submitEditDays, urlEditDays, tableDays, errorEditDays, false, false);

$(function() {
    initSelect2($('.select2'));
    ajaxAutoCompleteEmplacementInit($('.ajax-autocomplete-location'));
    ajaxAutoCompleteTransporteurInit($('.ajax-autocomplete-transporteur'));
    initDisplaySelect2('#receptionLocation', '#receptionLocationValue');
    $('#receptionLocation').on('change', editDefaultLocationValue);

    // config tableau de bord : emplacements
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
    $('#locationArrivageDest').on('change', editArrivageDestination);

    // config tableau de bord : transporteurs
    initDisplaySelect2Multiple('#carrierDock', '#carrierDockValue');
});

function errorEditDays(data) {
    let modal = $("#modalEditDays");
    if (data.success === false) {
        displayError(modal, data.msg, data.success);
    } else {
        modal.find('.close').click();
        alertSuccessMsg(data.msg);
    }
}

function updateToggledParam(switchButton, path) {
    $.post(path, JSON.stringify({val: switchButton.is(':checked')}), function () {
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
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            alertSuccessMsg('La configuration des étiquettes a bien été mise à jour.');
        }
    };
    let data = $('#dimsForm').find('.data');
    let json = {};
    data.each(function () {
        let val = $(this).attr('type') === 'checkbox' ? $(this).is(':checked') : $(this).val();
        let name = $(this).attr("name");
        json[name] = val;
    });
    let Json = JSON.stringify(json);
    let path = Routing.generate('ajax_dimensions_etiquettes', true);
    xhttp.open("POST", path, true);
    xhttp.send(Json);
    //TODO passer en jquery
}

function updatePrefixDemand(){
    let prefixe = $('#prefixeDemande').val();
    let typeDemande = $('#typeDemandePrefixageDemande').val();

    let path = Routing.generate('ajax_update_prefix_demand',true);
    let params = JSON.stringify({prefixe: prefixe, typeDemande: typeDemande});

    let msg = '';
    if(typeDemande === 'aucunPrefixe'){
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

    $.post(path, params, function(data) {
        $('#prefixeDemande').val(data);
    }, 'json');
}

function saveTranslations() {
    let $inputs = $('#translation').find('.translate');
    let data = [];
    $inputs.each(function() {
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
    $.post(Routing.generate('save_encodage'), JSON.stringify($('select[name="param-type-encodage"]').val()), function() {
        alertSuccessMsg('Mise à jour de vos préférences d\'encodage réussie.');
    });
}

function editDefaultLocationValue() {
    let path = Routing.generate('edit_reception_location',true);
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
    let path = Routing.generate('edit_dashboard_params',true);
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
function editFont() {
    let path = Routing.generate('edit_font',true);
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
            alertSuccessMsg("Mise à jour de la destination des arrivages bien effectuée.");
        } else {
            alertErrorMsg("Une erreur est survenue lors de la mise à jour du choix de la police.");
        }
    });
}
