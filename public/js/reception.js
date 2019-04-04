$('.select2').select2();

//RECEPTION
var path = Routing.generate('reception_api', true);
var table = $('#tableReception_id').DataTable({
    order: [[1, "desc"]],
    language: {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": path,
        "type": "POST"
    },
    columns: [
        { "data": 'Date' },
        { "data": 'Fournisseur' },
        { "data": 'Référence' },
        { "data": 'Statut' },
        { "data": 'Actions' }
    ],
});

let modalReceptionNew = $("#modalNewReception");
let SubmitNewReception = $("#submitButton");
let urlReceptionIndex = Routing.generate('reception_new', true)
InitialiserModal(modalReceptionNew, SubmitNewReception, urlReceptionIndex, table);

let ModalDelete = $("#modalDeleteReception");
let SubmitDelete = $("#submitDeleteReception");
let urlDeleteReception = Routing.generate('reception_delete', true)
InitialiserModal(ModalDelete, SubmitDelete, urlDeleteReception, table);

let modalModifyReception = $('#modalEditReception');
let submitModifyReception = $('#submitEditReception');
let urlModifyReception = Routing.generate('reception_edit', true);
InitialiserModal(modalModifyReception, submitModifyReception, urlModifyReception, table);


//AJOUTE_ARTICLE
let pathAddArticle = Routing.generate('reception_article_api', { 'id': id }, true);
let tableArticle = $('#tableArticle_id').DataTable({
    language: {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": pathAddArticle,
        "type": "POST"
    },
    columns: [
        { "data": 'Libellé' },
        { "data": 'Référence' },
        { "data": 'Référence CEA' },
        { "data": 'Statut' },
        { "data": 'Actions' }
    ],
});

let modal = $("#modalAddArticle");
let submit = $("#addArticleSubmit");
let url = Routing.generate('reception_article_add', true);
InitialiserModal(modal, submit, url, tableArticle);

let modalDeleteArticle = $("#modalDeleteArticle");
let submitDeleteArticle = $("#submitDeleteArticle");
let urlDeleteArticle = Routing.generate('reception_article_delete', true);
InitialiserModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, tableArticle);

let modalEditArticle = $("#modalEditArticle");
let submitEditArticle = $("#submitEditArticle");
let urlEditArticle = Routing.generate('reception_article_edit', true);
InitialiserModal(modalEditArticle, submitEditArticle, urlEditArticle, tableArticle);

//GENERATOR BARCODE

let printBarcode = function (button) {
    barcode = button.data('ref')
    JsBarcode("#barcode", barcode, {
        format: "CODE128",
    });
    printJS({
        printable: 'barcode',
        type: 'html',
        maxWidth: 250
    });
}

let pathPrinterAll = Routing.generate('article_printer_all', { 'id': id }, true);
let printerAll = function () {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
            data.forEach(function (element) {
                JsBarcode("#barcode", element, {
                    format: "CODE128",
                });
                console.log(element)
                printJS({
                    printable: 'barcode',
                    type: 'html',
                    maxWidth: 250
                });
            });
        }
    };
    Data = 'hello';
    json = JSON.stringify(Data);
    xhttp.open("POST", pathPrinterAll, true);
    xhttp.send(json);
}



function updateStock(select) {
    let id = select.val();
    $.post(Routing.generate('get_article_stock'), { 'id': id }, function (data) {
        $('#stock').val(data);
    }, "json");
}

//initialisation editeur de texte une seule fois
var editorAlreadyDone = false;
// console.log('dd')
// console.log(editorAlreadyDone)

function initEditor() {
     if (editorAlreadyDone === false) {
       console.log(editorAlreadyDone);
        var quill = new Quill('.editor-container', {
            modules: {
                toolbar: [
                    [{ header: [1, 2, false] }],
                    ['bold', 'italic', 'underline'],
                    ['image', 'code-block']
                ]
            },
            theme: 'snow'
        });
     editorAlreadyDone = true;
     console.log(editorAlreadyDone)
    console.log('vv')
    }
};
var editorAlreadyDoneId = false;
function initEditorId() {
    if (editorAlreadyDoneId === false) {
      console.log(editorAlreadyDoneId);
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
    editorAlreadyDoneId = true;
    console.log(editorAlreadyDoneId)
   console.log('vv')
   }
};
//passe de l'éditeur àl'imput pour insertion en BDD
function setCommentaire() {
    var quill = new Quill('.editor-container');
    var commentaire = document.querySelector('input[name=commentaire]');
    commentaire.value = quill.container.firstChild.innerHTML;
};

function setCommentaireId() {
    var quill = new Quill('#editor-container');
    console.log('testCOmm0');
    var commentaire = document.querySelector('input[name=commentaire]');
    commentaire.value = quill.container.firstChild.innerHTML;
};


