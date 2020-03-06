let pathTransporteur = Routing.generate('transporteur_api', true);
let tableTransporteur = $('#tableTransporteur_id').DataTable({

    order: [[1, 'desc']],
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathTransporteur,
        "type": "POST"
    },
    columns: [
        { "data": 'Actions', 'name': 'Actions', 'title': 'Actions' },
        { "data": 'Label', 'name': 'Label', 'title': 'Nom' },
        { "data": 'Code', 'name': 'Code', 'title': 'Code' },
        { "data": 'Nombre_chauffeurs', 'name': 'Nombre_chauffeurs', 'title': 'Nombre de chauffeurs' },
    ],
    columnDefs: [
        {
            "orderable" : false,
            "targets" : 0
        },
    ],
});

let modalNewTransporteur = $("#modalNewTransporteur");
let submitNewTransporteur = $("#submitNewTransporteur");
let urlNewTransporteur = Routing.generate('transporteur_new', true);
InitialiserModal(modalNewTransporteur, submitNewTransporteur, urlNewTransporteur, tableTransporteur);

let modalModifyTransporteur = $('#modalEditTransporteur');
let submitModifyTransporteur = $('#submitEditTransporteur');
let urlModifyTransporteur = Routing.generate('transporteur_edit', true);
InitialiserModal(modalModifyTransporteur, submitModifyTransporteur, urlModifyTransporteur, tableTransporteur);

let modalDeleteTransporteur = $('#modalDeleteTransporteur');
let submitDeleteTransporteur = $('#submitDeleteTransporteur');
let urlDeleteTransporteur = Routing.generate('transporteur_delete', true);
InitialiserModal(modalDeleteTransporteur, submitDeleteTransporteur, urlDeleteTransporteur, tableTransporteur);