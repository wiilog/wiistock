$('.select2').select2();

//REFERENCE ARTICLE

const urlApiRefArticle = Routing.generate('ref_article_api', true);
var tableRefArticle = $('#tableRefArticle_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": urlApiRefArticle,
        "type": "POST"
    },
    columns: [
        { "data": 'Libellé' },
        { "data": 'Référence' },
        { "data": 'Quantité' },
        { "data": 'Actions' },
    ],
});

let ModalRefArticleNew = $("#modalNewRefArticle");
let ButtonSubmitRefArticleNew = $("#submitNewRefArticle");
let urlRefArticleNew = Routing.generate('reference_article_new', true);
InitialiserModal(ModalRefArticleNew, ButtonSubmitRefArticleNew, urlRefArticleNew, tableRefArticle);

let ModalDeleteRefArticle = $("#modalDeleteRefArticle");
let SubmitDeleteRefArticle = $("#submitDeleteRefArticle");
let urlDeleteRefArticle = Routing.generate('reference_article_delete', true);
InitialiserModal(ModalDeleteRefArticle, SubmitDeleteRefArticle, urlDeleteRefArticle, tableRefArticle);

let modalModifyRefArticle = $('#modalEditRefArticle');
let submitModifyRefArticle = $('#submitEditRefArticle');
let urlModifyRefArticle = Routing.generate('reference_article_edit', true);
InitialiserModal(modalModifyRefArticle, submitModifyRefArticle, urlModifyRefArticle,  tableRefArticle);


$('#myTab button').on('click', function (e) {
    $(this).siblings().removeClass('data');
    $(this).addClass('data');
  })


function idType(div) {
    let id = div.attr('value');
   $('#idType').attr('value', id);    
}

function  visibleBlockModal(bloc) {
    console.log(bloc);
    let blocContent = bloc.siblings().filter('.col-12');
    let sortUp =  bloc.find('h3').find('.fa-sort-up');
    let sortDown = bloc.find('h3').find('.fa-sort-down');

    if (sortUp.attr('class').search('d-none') > 0) {
        sortUp.removeClass('d-none');
        sortUp.addClass('d-block');
        sortDown.removeClass('d-block');
        sortDown.addClass('d-none');

        blocContent.removeClass('d-none')
        blocContent.addClass('d-block');
        
        console.log('vue');
    }else{
        sortUp.removeClass('d-block');
        sortUp.addClass('d-none');
        sortDown.removeClass('d-none');
        sortDown.addClass('d-block');

        blocContent.removeClass('d-block')
        blocContent.addClass('d-none')
        
        console.log('cache');
    }


   
}