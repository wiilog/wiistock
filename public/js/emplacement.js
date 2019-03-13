var pathEmplacement = Routing.generate("emplacement_api", true);
var tableEmplacement = $('#tableEmplacement_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax:{
        "url": pathEmplacement,
        "type": "POST"
    },
    columns: [
        { "data": 'Nom' },
        { "data": 'Description' },
        { "data": 'Actions' }
    ],
    buttons: [
       'copy', 'excel', 'pdf'
     ]
});

let modalNewEmplacement = $("#modalNewEmplacement"); 
let submitNewEmplacement = $("#submitNewEmplacement");
let urlNewEmplacement = Routing.generate('creation_emplacement', true);
InitialiserModal(modalNewEmplacement, submitNewEmplacement, urlNewEmplacement, tableEmplacement);

let ModalDeleteEmplacement = $("#modalDeleteEmplacement");
let SubmitDeleteEmplacement = $("#submitDeleteEmplacement");
let urlDeleteEmplacement = Routing.generate('emplacement_delete', true)
InitialiserModal(ModalDeleteEmplacement, SubmitDeleteEmplacement, urlDeleteEmplacement, tableEmplacement);

let modalModifyEmplacement = $('#modalEditEmplacement');
let submitModifyEmplacement = $('#submitEditEmplacement');
let urlModifyEmplacement = Routing.generate('emplacement_edit', true);
InitialiserModal(modalModifyEmplacement, submitModifyEmplacement, urlModifyEmplacement, tableEmplacement);


$('#myTab button').on('click', function (e) {
    $(this).siblings().removeClass('data');
    $(this).addClass('data');
  })