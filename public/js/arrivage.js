$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Demandeur',
    }
});

let pathArrivage = Routing.generate('arrivage_api', true);
let tableArrivage = $('#tableArrivages').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    "order": [[0, "desc"]],
    ajax: {
        "url": pathArrivage,
        "type": "POST"
    },
    columns: [
        { "data": "NumeroArrivage", 'name': 'NumeroArrivage', 'title': "N° d'arrivage" },
        { "data": 'Transporteur', 'name': 'Transporteur', 'title': 'Transporteur' },
        { "data": 'NoTracking', 'name': 'NoTracking', 'title': 'N° tracking transporteur' },
        { "data": 'NumeroBL', 'name': 'NumeroBL', 'title': 'N° commande / BL' },
        { "data": 'Fournisseur', 'name': 'Fournisseur', 'title': 'Fournisseur' },
        { "data": 'Destinataire', 'name': 'Destinataire', 'title': 'Destinataire' },
        { "data": 'NbUM', 'name': 'NbUM', 'title': 'Nb UM' },
        { "data": 'Statut', 'name': 'Statut', 'title': 'Statut' },
        { "data": 'Date', 'name': 'Date', 'title': 'Date' },
        { "data": 'Utilisateur', 'name': 'Utilisateur', 'title': 'Utilisateur' },
        { "data": 'Actions', 'name': 'Actions', 'title': 'Actions' },
    ],

});

let editorNewArrivageAlreadyDone = false;
function initNewArrivageEditor(modal) {
    if (!editorNewArrivageAlreadyDone) {
        initEditor2(modal + '.editor-container-new');
        editorNewArrivageAlreadyDone = true;
    }
};

let modalNewArrivage = $("#modalNewArrivage");
let submitNewArrivage = $("#submitNewArrivage");
let urlNewArrivage = Routing.generate('arrivage_new', true);
InitialiserModal(modalNewArrivage, submitNewArrivage, urlNewArrivage, tableArrivage);

let modalModifyArrivage = $('#modalEditArrivage');
let submitModifyArrivage = $('#submitEditArrivage');
let urlModifyArrivage = Routing.generate('arrivage_edit', true);
InitialiserModal(modalModifyArrivage, submitModifyArrivage, urlModifyArrivage, tableArrivage);

let modalDeleteArrivage = $('#modalDeleteArrivage');
let submitDeleteArrivage = $('#submitDeleteArrivage');
let urlDeleteArrivage = Routing.generate('arrivage_delete', true);
InitialiserModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage, tableArrivage);

function toggleLitige(select) {
    let bloc = select.closest('.modal').find('#litigeBloc');
    let status = select.find('option:selected').text();

    let litigeType = bloc.find('#litigeType');
    let constantConform = $('#constantConforme').val();

    if (status === constantConform) {
        litigeType.removeClass('needed');
        bloc.addClass('d-none');

    } else {
        bloc.removeClass('d-none');
        litigeType.addClass('needed');
    }
}

function deleteRowArrivage(button, modal, submit, hasLitige) {
    deleteRow(button, modal, submit);
    let hasLitigeText = modal.find('.hasLitige');
    if (hasLitige) {
        hasLitigeText.removeClass('d-none');
    } else {
        hasLitigeText.addClass('d-none');
    }
}

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
        success:function(){
            $('#dropfile').css('border', '3px dashed #BBBBBB');
        }
    });
}