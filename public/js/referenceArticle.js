$('.select2').select2();

//REFERENCE ARTICLE

let tableRefArticle;
// console.log(urlApiRefArticle);
let urltest = Routing.generate('ref_article_api', true);
$(document).ready(function () {
    let jsonB= 'lol';
    $.post(urltest, jsonB, function (data) {
        let dataContent = data.data;
        let columnContent = data.column;
        tableRefArticle = $('#tableRefArticle_id').DataTable({
            "autoWidth": false,
            "scrollX": true,
            colReorder: true,
            "language": {
                "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
            },
            "data": dataContent,
            "columns" : columnContent
        });
    })
});

// const urlApiRefArticle = Routing.generate('ref_article_api', true);
// let tableRefArticle = $('#tableRefArticle_id').DataTable({
//     "scrollX": true,
//     "language": {
//         "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
//     },
//     ajax: {
//         "url": urlApiRefArticle,
//         "type": "POST",
//     },
//     // "data": data.data,
//     // "columns": data.column,
// });


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
InitialiserModal(modalModifyRefArticle, submitModifyRefArticle, urlModifyRefArticle, tableRefArticle);


$('#myTab div').on('click', function (e) {
    $(this).siblings().removeClass('data');
    $(this).addClass('data');
})


function idType(div) {
    let id = div.attr('value');
    $('#idType').attr('value', id);
}

function visibleBlockModal(bloc) {
    let blocContent = bloc.siblings().filter('.col-12');
    let sortUp = bloc.find('h3').find('.fa-sort-up');
    let sortDown = bloc.find('h3').find('.fa-sort-down');

    if (sortUp.attr('class').search('d-none') > 0) {
        sortUp.removeClass('d-none');
        sortUp.addClass('d-block');
        sortDown.removeClass('d-block');
        sortDown.addClass('d-none');

        blocContent.removeClass('d-none')
        blocContent.addClass('d-block');
    } else {
        sortUp.removeClass('d-block');
        sortUp.addClass('d-none');
        sortDown.removeClass('d-none');
        sortDown.addClass('d-block');

        blocContent.removeClass('d-block')
        blocContent.addClass('d-none')
    }
}

//COLUMN VISIBLE

let tableColumnVisible = $('#tableColumnVisible_id').DataTable({
    "paging": false,
    "info": false
});

function visibleColumn(check) {
    let columnNumber = check.data('column')
    console.log(columnNumber);
    let column = tableRefArticle.column(columnNumber);
    console.log(column);
    column.visible(!column.visible());
}

function updateQuantityDisplay(elem) {
    let typeQuantite = elem.closest('.radio-btn').find('#type_quantite').val();
    let modalBody = elem.closest('.modal-body');

    if (typeQuantite == 'reference') {
        modalBody.find('.article').addClass('d-none');
        modalBody.find('.reference').removeClass('d-none');

    } else if (typeQuantite == 'article') {
        modalBody.find('.reference').addClass('d-none');
        modalBody.find('.article').removeClass('d-none');
    }
}
