$('.select2').select2();

var pathArticle = Routing.generate('article_api', true);
var tableArticle = $('#tableArticle_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax:{ 
        "url": pathArticle,
        "type": "POST"
    },
    columns:[
        { "data": 'Référence' },
        { "data": 'Statut' },
        { "data": 'Libellé' },
        { "data": 'Référence article' },
        { "data": 'Quantité' },
        { "data": 'Actions' }
    ],
});

let modalEditArticle = $("#modalEditArticle");
let submitEditArticle = $("#submitEditArticle");
let urlEditArticle = Routing.generate('reception_article_edit', true);
InitialiserModal(modalEditArticle, submitEditArticle, urlEditArticle, tableArticle);

//initialisation editeur de texte une seule fois
var editorAlreadyDone = false;
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
var editorIdAlreadyDone = false;
function initEditorId() {     
        var quill = new Quill('#editor', {
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
};


//passe de l'éditeur àl'imput pour insertion en BDD
function setCommentaire() {
    var quill = new Quill('.editor-container'); 
    var commentaire = document.querySelector('input[name=commentaire]');
    commentaire.value = quill.container.firstChild.innerHTML;
};

function setCommentaireId() {
    var quill = new Quill('#editor');
    let com = quill.container.firstChild.innerHTML;
    $('#commentaire').val(com);
};
