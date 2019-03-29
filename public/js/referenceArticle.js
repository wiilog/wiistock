$('.select2').select2();

function InitialiserModalRefArticle(modal, submit, path, callback = function(){}) {
    submit.click(function () {
        xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                $('.errorMessage').html(JSON.parse(this.responseText))
                data = JSON.parse(this.responseText);
                if (data.new) {
                    tableRefArticle.row.add(data.new).draw( false );
                }else if(data.delete){
                    tableRefArticle.row($('#delete'+data.delete).parents('div').parents('td').parents('tr')).remove().draw( false );
                }else if(data.edit){
                    tableRefArticle.row($('#edit'+data.id).parents('div').parents('td').parents('tr')).remove().draw( false );
                    tableRefArticle.row.add(data.edit).draw( false );
                } else if (data.reload) {
                    tableRefArticle.clear();
                    tableRefArticle.rows.add(data.reload).draw();
                }

                callback(data);
                initRemove();

                let inputs = modal.find('.modal-body').find(".data");
                // on vide tous les inputs
                inputs.each(function () {
                    $(this).val("");
                });
                // on remet toutes les checkboxes sur off
                let checkboxes = modal.find('.checkbox');
                checkboxes.each(function () {
                    $(this).prop('checked', false);
                })
            }
        };

        // On récupère toutes les données qui nous intéressent
        // dans les inputs...
        let inputs = modal.find(".data");
        let Data = {};
        let missingInputs = [];
        let wrongInputs = [];

        inputs.each(function () {
            let val = $(this).val();
            let name = $(this).attr("name");
            Data[name] = val;
            // validation données obligatoires
            if ($(this).hasClass('needed') && (val === undefined || val === '' || val === null)) {
                let label = $(this).closest('.form-group').find('label').text();
                missingInputs.push(label);
                $(this).addClass('is-invalid');
            }
            // validation valeur des inputs de type number
            if ($(this).attr('type') === 'number') {
                let val = parseInt($(this).val());
                let min = parseInt($(this).attr('min'));
                let max = parseInt($(this).attr('max'));
                if (val > max || val < min) {
                    wrongInputs.push($(this));
                    $(this).addClass('is-invalid');
                }
            }
        });

        // ... et dans les checkboxes
        let checkboxes = modal.find('.checkbox');
        checkboxes.each(function () {
            Data[$(this).attr("name")] = $(this).is(':checked');
        });

        // si tout va bien on envoie la requête ajax...
        if (missingInputs.length == 0 && wrongInputs.length == 0) {
            modal.find('.close').click();
            Json = {};
            Json = JSON.stringify(Data);
            xhttp.open("POST", path, true);
            xhttp.send(Json);
        } else {

            // ... sinon on construit les messages d'erreur
            let msg = '';

            // cas où il manque des champs obligatoires
            if (missingInputs.length > 0) {
                if (missingInputs.length == 1) {
                    msg += 'Veuillez renseigner le champ ' + missingInputs[0] + ".<br>";
                } else {
                    msg += 'Veuillez renseigner les champs : ' + missingInputs.join(', ') + ".<br>";
                }
            }
            // cas où les champs number ne respectent pas les valeurs imposées (min et max)
            if (wrongInputs.length > 0) {
                wrongInputs.forEach(function (elem) {
                    let label = elem.closest('.form-group').find('label').text();

                    msg += 'La valeur du champ ' + label;

                    let min = elem.attr('min');
                    let max = elem.attr('max');

                    if (typeof (min) !== 'undefined' && typeof (max) !== 'undefined') {
                        msg += ' doit être comprise entre ' + min + ' et ' + max + ".<br>";
                    } else if (typeof (min) == 'undefined') {
                        msg += ' doit être inférieure à ' + max + ".<br>";
                    } else if (typeof (max) == 'undefined') {
                        msg += ' doit être supérieure à ' + min + ".<br>";
                    }

                })
            }

            modal.find('.error-msg').html(msg);
        }
    });
}


let ModalRefArticleNew = $("#modalNewRefArticle");
let ButtonSubmitRefArticleNew = $("#submitNewRefArticle");
let urlRefArticleNew = Routing.generate('reference_article_new', true);
InitialiserModalRefArticle(ModalRefArticleNew, ButtonSubmitRefArticleNew, urlRefArticleNew);

let ModalDeleteRefArticle = $("#modalDeleteRefArticle");
let SubmitDeleteRefArticle = $("#submitDeleteRefArticle");
let urlDeleteRefArticle = Routing.generate('reference_article_delete', true);
InitialiserModalRefArticle(ModalDeleteRefArticle, SubmitDeleteRefArticle, urlDeleteRefArticle);

let modalModifyRefArticle = $('#modalEditRefArticle');
let submitModifyRefArticle = $('#submitEditRefArticle');
let urlModifyRefArticle = Routing.generate('reference_article_edit', true);
InitialiserModalRefArticle(modalModifyRefArticle, submitModifyRefArticle, urlModifyRefArticle);

let modalNewFilter = $('#modalNewFilter');
let submitNewFilter = $('#submitNewFilter');
let urlNewFilter = Routing.generate('filter_new', true);
InitialiserModalRefArticle(modalNewFilter, submitNewFilter, urlNewFilter, displayNewFilter);

let url = Routing.generate('ref_article_api', true);

//REFERENCE ARTICLE

$(document).ready(function () {
    $.post(url, function (data) {
        let dataContent = data.data;
        let columnContent = data.column;
        tableRefArticle = $('#tableRefArticle_id').DataTable({
            "autoWidth": false,
            "scrollX": true,
            "pageLength": 50,
            "lengthMenu": [50, 100, 200, 500 ],
            "language": {
                "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
            },
            "data": dataContent,
            "columns": columnContent
        });
        initRemove();
    })
});

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


//Récupére Id du type selectionné
function idType(div, idInput) {
    let id = div.attr('value');
    $(idInput).attr('value', id);
}

//Cache/affiche les bloc des modal edit/new
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

// affiche le filtre après ajout
function displayNewFilter(data) {
    $('#filters').append(data.filterHtml);
}

// suppression du filtre au clic dessus
function initRemove() {
    $('.filter').on('click', removeFilter);
}

function removeFilter() {
    $(this).remove();

    let params = JSON.stringify({ 'filterId': $(this).find('.filter-id').val() });
    $.post(Routing.generate('filter_delete', true), params, function(data) {
        tableRefArticle.clear();
        tableRefArticle.rows.add(data).draw();
    });
}

// modale ajout d'un filtre, affichage du champ "contient" en fonction du champ sélectionné
function displayFilterValue(elem) {
    let type = elem.find(':selected').data('type');
    let modalBody = elem.closest('.modal-body');

    // cas particulier de liste déroulante pour type
    if (type == 'list') {
        $.getJSON(Routing.generate('type_show_select'), function(data) {
            modalBody.find('.input').html(data);
        })
    } else {
        if (type == 'booleen') type = 'checkbox';
        modalBody.find('.input').html('<input type="' + type + '" class="form-control data ' + type + '" id="value" name="value">');
    }


    let label = '';
    switch (type) {
        case 'checkbox':
            label = 'Oui / Non';
            break;
        case 'number':
        case 'list':
            label = 'Valeur';
            break;
        default:
            label = 'Contient';
    }

    elem.closest('.modal-body').find('.valueLabel').text(label);
}