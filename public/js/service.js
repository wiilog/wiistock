$('.select2').select2({
    placeholder: {
        id: 'demandeur', // the value of the option
        text: 'Demandeur',
    }

});
// console.log('test');
// function textEditor() {
//     console.log('re');
//     var quill = new Quill('#editor-container', {
        
//         modules: {
//             toolbar: [
//                 [{ header: [1, 2, false] }],
//                 ['bold', 'italic', 'underline'],
//                 ['image', 'code-block']
//             ]
//         },
//         theme: 'snow'
//     });
//     console.log('retest');
// };

// function setCommentaire() {

//     var commentaire = document.querySelector('input[name=commentaire]');
//     commentaire.value = quill.container.firstChild.innerHTML;

// };






var pathService = Routing.generate('service_api', true);
var tableService = $('#tableService_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": pathService,
        "type": "POST"
    },
    columns: [
        { "data": 'Date' },
        { "data": 'Demandeur' },
        { "data": 'Libellé' },
        { "data": 'Statut' },
        { "data": 'Actions' },
    ],

});

$('#submitSearchService').on('click', function () {

    let statut = $('#statut').val();
    let demandeur = [];
    demandeur = $('#demandeur').val()
    demandeurString = demandeur.toString();
    demandeurPiped = demandeurString.split(',').join('|')

    tableService
        .columns(3)
        .search(statut)
        .draw();

    tableService
        .columns(1)
        .search(demandeurPiped ? '^' + demandeurPiped + '$' : '', true, false)
        .draw();

    $.fn.dataTable.ext.search.push(
        function (settings, data, dataIndex) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let dateInit = (data[0]).split('/').reverse().join('-') || 0;

            if (
                (dateMin == "" && dateMax == "")
                ||
                (dateMin == "" && moment(dateInit).isSameOrBefore(dateMax))
                ||
                (moment(dateInit).isSameOrAfter(dateMin) && dateMax == "")
                ||
                (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))

            ) {
                return true;
            }
            return false;
        }

    );
    tableService
        .draw();
});


// var quill = new Quill('#editor-container', {
//     modules: {
//         toolbar: [
//             [{ header: [1, 2, false] }],
//             ['bold', 'italic', 'underline'],
//             ['image', 'code-block']
//         ]
//     },
//     theme: 'snow'
// });

// function setCommentaire(){ 

//     var commentaire = document.querySelector('input[name=commentaire]');
//         commentaire.value = quill.container.firstChild.innerHTML;


// };

let modalNewService = $("#modalNewService");
let submitNewService = $("#submitNewService");
let urlNewService = Routing.generate('creation_service', true);
InitialiserModal(modalNewService, submitNewService, urlNewService, tableService);

let modalModifyService = $('#modalEditService');
let submitModifyService = $('#submitEditService');
let urlModifyService = Routing.generate('service_edit', true);
InitialiserModal(modalModifyService, submitModifyService, urlModifyService, tableService);


function initEditor() {
    console.log('re');
    var quill = new Quill('#editor-container', {

        modules: {
            toolbar: [
                [{ header: [1, 2, false] }],
                ['bold', 'italic', 'underline'],
                ['image', 'code-block']
            ]
        },
        theme: 'snow'
    });
    console.log('retest');
};

var quill = new Quill('#editor-container', {
    modules: {
        toolbar: [
            [{ header: [1, 2, false] }],
            ['bold', 'italic', 'underline'],
            ['image', 'code-block']
        ]
    },
    theme: 'snow'
});


function setCommentaire() {
    console.log('rst');
    var commentaire = document.querySelector('input[name=commentaire]');
    commentaire.value = quill.container.firstChild.innerHTML;
};

