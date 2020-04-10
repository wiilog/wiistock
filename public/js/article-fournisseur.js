let pathArticleFournisseur = Routing.generate('article_fournisseur_api');
let tableArticleFournisseur = $('#tableArticleFournisseur').DataTable({

    processing: true,
    serverSide: true,

    order: [[1, 'desc']],
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathArticleFournisseur,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', title: 'Actions'},
        {"data": 'Référence', title: 'Référence'},
        {"data": 'label', title: 'Libellé'},
        {"data": 'Code Fournisseur', title: 'Code Fournisseur'},
        {"data": 'Article de référence', title: 'Article de référence'},
    ],
    columnDefs: [
        {"orderable": false, "targets": 0}
    ]
});

let modalNewArticleFournisseur = $("#modalNewArticleFournisseur");
let submitNewArticleFournisseur = $("#submitNewArticleFournisseur");
let urlNewArticleFournisseur = Routing.generate('article_fournisseur_new', true);
InitialiserModal(modalNewArticleFournisseur, submitNewArticleFournisseur, urlNewArticleFournisseur, tableArticleFournisseur, handleArticleFournisseurSucces, false, false);

let modalDeleteArticleFournisseur = $("#modalDeleteArticleFournisseur");
let submitDeleteArticleFournisseur = $("#submitDeleteArticleFournisseur");
let urlDeleteArticleFournisseur = Routing.generate('article_fournisseur_delete', true);
InitialiserModal(modalDeleteArticleFournisseur, submitDeleteArticleFournisseur, urlDeleteArticleFournisseur, tableArticleFournisseur);

let modalEditArticleFournisseur = $('#modalEditArticleFournisseur');
let submitEditArticleFournisseur = $('#submitEditArticleFournisseur');
let urlEditArticleFournisseur = Routing.generate('article_fournisseur_edit', true);
InitialiserModal(modalEditArticleFournisseur, submitEditArticleFournisseur, urlEditArticleFournisseur, tableArticleFournisseur, handleEditArticleFournisseurSucces, false, false);

function handleArticleFournisseurSucces(data) {

    if (data.success){
        console.log(data);
        modalNewArticleFournisseur.modal('hide');
        clearModal(modalNewArticleFournisseur);
    }
    else {
        modalNewArticleFournisseur.find('.error-msg').text(data.message);
        console.log(data);
    }

}

function handleEditArticleFournisseurSucces(data) {

    if (data.success){
        console.log(data);
        modalEditArticleFournisseur.modal('hide');
        clearModal(modalNewArticleFournisseur);
    }
    else {
        modalEditArticleFournisseur.find('.error-msg').text(data.message);
        console.log(data);
    }

}
