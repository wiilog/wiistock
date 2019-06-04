$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Demandeur',
    }
});

let pathArrivage = Routing.generate('arrivage_api', true);
let tableService = $('#tableArrivages').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    "order": [[0, "desc"]],
    ajax: {
        "url": pathArrivage,
        "type": "POST"
    },
    columns: [
        {"data": "NumArrivage", 'name': 'NumArrivage', 'title': "N° d'arrivage"},
        {"data": 'Transporteur', 'name': 'Transporteur', 'title': 'Transporteur'},
        {"data": 'NoTrackingTransp', 'name': 'NoTrackingTransp', 'title': 'N° tracking transporteur'},
        {"data": 'NumBL', 'name': 'NumBL', 'title': 'N° commande / BL'},
        {"data": 'Fournisseur', 'name': 'Fournisseur', 'title': 'Fournisseur'},
        {"data": 'Destinataire', 'name': 'Destinataire', 'title': 'Destinataire'},
        {"data": 'NbUM', 'name': 'NbUM', 'title': 'Nb UM'},
        {"data": 'Statut', 'name': 'Statut', 'title': 'Statut'},
        {"data": 'Date', 'name': 'Date', 'title': 'Date'},
        {"data": 'Utilisateur', 'name': 'Utilisateur', 'title': 'Utilisateur'},
    ],

});

function dragEnterDiv(event, div) {
    div.css('border', '3px dashed red');
}

function dragOverDiv(event, div) {
    event.preventDefault();
    event.stopPropagation();
    div.css('border', '3px dashed red');
    return false;
};

function dragLeaveDiv(event, div) {
    event.preventDefault();
    event.stopPropagation();
    div.css('border', '3px dashed #BBBBBB');
    return false;
}

function dropOnDiv(event, div) {
    if (event.dataTransfer) {
        if (event.dataTransfer.files.length) {
            // Stop the propagation of the event
            event.preventDefault();
            event.stopPropagation();
            div.css('border', '3px dashed green');
            // Main function to upload
            upload(event.dataTransfer.files);
        }
    } else {
        div.css('border', '3px dashed #BBBBBB');
    }
    return false;
}

function upload(files) {

    let formData = new FormData();
    $.each(files, function (index, file) {
        formData.append('file' + index, file);
    });
    let path = Routing.generate('arrivage_depose', true);
    $.ajax({
        url: path,
        data: formData,// the formData function is available in almost all new browsers.
        type:"post",
        contentType:false,
        processData:false,
        cache:false,
        dataType:"json", // Change this according to your response from the server.
        success:function(data){
            $('#dropfile').css('border', '3px dashed #BBBBBB');
        }
    });
}

// let modalNewService = $("#modalNewService");
// let submitNewService = $("#submitNewService");
// let urlNewService = Routing.generate('service_new', true);
// InitialiserModal(modalNewService, submitNewService, urlNewService, tableService);
//
// let modalModifyService = $('#modalEditService');
// let submitModifyService = $('#submitEditService');
// let urlModifyService = Routing.generate('service_edit', true);
// InitialiserModal(modalModifyService, submitModifyService, urlModifyService, tableService);
//
// let modalDeleteService = $('#modalDeleteService');
// let submitDeleteService = $('#submitDeleteService');
// let urlDeleteService = Routing.generate('service_delete', true);
// InitialiserModal(modalDeleteService, submitDeleteService, urlDeleteService, tableService);