var id = $('#demande-id').data('id');
var path = Routing.generate('LigneArticle_api', { id: id }, true);
var table = $('#table-lignes').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    "processing": true,
    "ajax": {
        "url": path,
        "type": "POST"
    },
    columns:[
            {"data": 'Référence CEA'},
            {"data": 'Libellé'},
            {"data": 'Quantité'},
            {"data": 'Actions'}
    ],
});


var dataModal1 = $("#modifModalCenter");
var id1 = dataModal1.data('id');
var ButtonSubmit1 = $("#modifsubmitButton");
var path1 = Routing.generate('modifDemande', { id: id1 }, true);
InitialiserModal(dataModal1, ButtonSubmit1, path1);


var dataModal2 = $("#ajoutLigneModalCenter");
var id2 = dataModal2.data('id');
var ButtonSubmit2 = $("#ajoutsubmitButton");
var path2 = Routing.generate('ajoutLigneArticle', { id: id2 }, true);
InitialiserModal(dataModal2, ButtonSubmit2, path2);