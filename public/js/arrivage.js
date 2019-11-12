const allowedExtensions = ['pdf', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'doc', 'docx', 'ppt', 'pptx', 'csv', 'txt'];

$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Destinataire',
    }
});

let $submitSearchArrivage = $('#submitSearchArrivage');

$(function() {
    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ARRIVAGE);;
    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field == 'utilisateurs') {
                $('#utilisateur').val(element.value.split(',')).select2();
            } else {
                $('#'+element.field).val(element.value);
            }
        });
        if (data.length > 0)$submitSearchArrivage.click();
    }, 'json');
});

let pathArrivage = Routing.generate('arrivage_api', true);
let tableArrivage = $('#tableArrivages').DataTable({
    responsive: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[11, "desc"]],
    scrollX: true,
    ajax: {
        "url": pathArrivage,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'},
        {"data": "NumeroArrivage", 'name': 'NumeroArrivage', 'title': "N° d'arrivage"},
        {"data": 'Transporteur', 'name': 'Transporteur', 'title': 'Transporteur'},
        {"data": 'Chauffeur', 'name': 'Chauffeur', 'title': 'Chauffeur'},
        {"data": 'NoTracking', 'name': 'NoTracking', 'title': 'N° tracking transporteur'},
        {"data": 'NumeroBL', 'name': 'NumeroBL', 'title': 'N° commande / BL'},
        {"data": 'Fournisseur', 'name': 'Fournisseur', 'title': 'Fournisseur'},
        {"data": 'Destinataire', 'name': 'Destinataire', 'title': 'Destinataire'},
        {"data": 'Acheteurs', 'name': 'Acheteurs', 'title': 'Acheteurs'},
        {"data": 'NbUM', 'name': 'NbUM', 'title': 'Nb UM'},
        {"data": 'Statut', 'name': 'Statut', 'title': 'Statut'},
        {"data": 'Date', 'name': 'Date', 'title': 'Date'},
        {"data": 'Utilisateur', 'name': 'Utilisateur', 'title': 'Utilisateur'},
    ],
});

$.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableArrivage.column('Date:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split(' ')[0].split('/').reverse().join('-') || 0;

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

tableArrivage.on('responsive-resize', function (e, datatable) {
    datatable.columns.adjust().responsive.recalc();
});

let modalNewArrivage = $("#modalNewArrivage");
let submitNewArrivage = $("#submitNewArrivage");
let urlNewArrivage = Routing.generate('arrivage_new', true);
InitModalArrivage(modalNewArrivage, submitNewArrivage, urlNewArrivage, tableArrivage);

let editorNewArrivageAlreadyDone = false;
let quillNew;

function initNewArrivageEditor(modal) {
    if (!editorNewArrivageAlreadyDone) {
        quillNew = initEditor(modal + ' .editor-container-new');
        editorNewArrivageAlreadyDone = true;
    }
}

let quillEdit;
let originalText = '';

// function toggleLitige(select) {
//     let bloc = select.closest('.modal').find('#litigeBloc');
//     let status = select.find('option:selected').text();
//
//     let litigeType = bloc.find('#litigeType');
//     let constantConform = $('#constantConforme').val();
//
//     if (status === constantConform) {
//         litigeType.removeClass('needed');
//         bloc.addClass('d-none');
//
//     } else {
//         bloc.removeClass('d-none');
//         litigeType.addClass('needed');
//     }
// }

function addCommentaire(select, bool) {
    let params = {
        typeLitigeId: select.val()
    };

    let quillType = bool ? quillNew : quillEdit;
    originalText = quillType.getText().trim();

    $.post(Routing.generate('add_comment', true), JSON.stringify(params), function (comment) {
        if (comment) {
            let d = new Date();
            let date = checkZero(d.getDate() + '') + '/' + checkZero(d.getMonth() + 1 + '') + '/' + checkZero(d.getFullYear() + '');
            date += ' ' + checkZero(d.getHours() + '') + ':' + checkZero(d.getMinutes() + '');

            let textToInsert = originalText.length > 0 && !bool ? originalText + "\n\n" : '';

            quillType.setContents([
                {insert: textToInsert},
                {insert: date + ' : '},
                {insert: comment},
                {insert: '\n'},
            ]);
        }
    });
}

function dragEnterDiv(event, div) {
    div.css('border', '3px dashed red');
}

function dragOverDiv(event, div) {
    event.preventDefault();
    event.stopPropagation();
    div.css('border', '3px dashed red');
    return false;
};

function dragLeaveDiv(event, div) {
    event.preventDefault();
    event.stopPropagation();
    div.css('border', '3px dashed #BBBBBB');
    return false;
}

function dropOnDiv(event, div) {
    if (event.dataTransfer) {
        if (event.dataTransfer.files.length) {
            // Stop the propagation of the event
            event.preventDefault();
            event.stopPropagation();
            div.css('border', '3px dashed green');

            let valid = checkFilesFormat(event.dataTransfer.files, div);

            if (valid) {
                upload(event.dataTransfer.files);
                clearErrorMsg(div);
            } else {
                div.css('border', '3px dashed #BBBBBB');
            }
        }
    } else {
        div.css('border', '3px dashed #BBBBBB');
    }
    return false;
}

function dropNewOnDiv(event, div) {
    if (event.dataTransfer) {
        if (event.dataTransfer.files.length) {
            event.preventDefault();
            event.stopPropagation();
            div.css('border', '3px dashed green');

            let valid = checkFilesFormat(event.dataTransfer.files, div);

            if (valid) {
                keepForSave(event.dataTransfer.files);
                clearErrorMsg(div);
            }
            else div.css('border', '3px dashed #BBBBBB');
        }
    } else {
        div.css('border', '3px dashed #BBBBBB');
    }
    return false;
}


function checkFilesFormat(files, div) {
    let valid = true;
    $.each(files, function (index, file) {
        if (file.name.includes('.') === false) {
            div.closest('.modal-body').next('.error-msg').html("Le format de votre pièce jointe n'est pas supporté. Le fichier doit avoir une extension.");
            valid = false;
        }
        // else if (!(allowedExtensions.includes(file.name.split('.').pop())) && valid) {
        //     div.closest('.modal-body').next('.error-msg').html('L\'extension .' + file.name.split('.').pop() + ' n\'est pas supportée.');
        //     valid = false;
        // }
    });
    return valid;
}

function openFE() {
    $('#fileInput').click();
}

function uploadFE(span) {
    let files = $('#fileInput')[0].files;
    let formData = new FormData();
    let div = span.closest('.dropFrame');
    clearErrorMsg(div);

    let valid = checkFilesFormat(files, div);

    if (valid) {
        $.each(files, function (index, file) {
            formData.append('file' + index, file);
        });
        let path = Routing.generate('arrivage_depose', true);

        let arrivageId = $('#dropfile').data('arrivage-id');
        formData.append('id', arrivageId);

        $.ajax({
            url: path,
            data: formData,
            type:"post",
            contentType:false,
            processData:false,
            cache:false,
            dataType:"json",
            success:function(html){
                let dropfile = $('#dropfile');
                dropfile.css('border', '3px dashed #BBBBBB');
                dropfile.after(html);
            }
        });
    } else {
        div.css('border', '3px dashed #BBBBBB');
    }
}

function openFENew() {
    $('#fileInputNew').click();
}

function uploadFENew(span) {
    let files = $('#fileInputNew')[0].files;
    let formData = new FormData();
    let div = span.closest('.dropFrame');
    clearErrorMsg(div);

    let valid = checkFilesFormat(files, div);

    if (valid) {
        $.each(files, function (index, file) {
            formData.append('file' + index, file);
        });
        let path = Routing.generate('garder_pj', true);
        $.ajax({
            url: path,
            data: formData,
            type: "post",
            contentType: false,
            processData: false,
            cache: false,
            dataType: "json",
            success: function (html) {
                let dropfile = $('#dropfileNew');
                dropfile.css('border', '3px dashed #BBBBBB');
                dropfile.after(html);
            }
        });
    } else {
        div.css('border', '3px dashed #BBBBBB');
    }
}

function keepForSave(files) {

    let formData = new FormData();
    $.each(files, function (index, file) {
        formData.append('file' + index, file);
    });

    let path = Routing.generate('garder_pj', true);

    $.ajax({
        url: path,
        data: formData,
        type:"post",
        contentType:false,
        processData:false,
        cache:false,
        dataType:"json",
        success:function(html){
            let dropfile = $('#dropfileNew');
            dropfile.css('border', '3px dashed #BBBBBB');
            dropfile.after(html);
        }
    });

}

function upload(files) {

    let formData = new FormData();
    $.each(files, function (index, file) {
        formData.append('file' + index, file);
    });
    let path = Routing.generate('arrivage_depose', true);

    let arrivageId = $('#dropfile').data('arrivage-id');
    formData.append('id', arrivageId);

    $.ajax({
        url: path,
        data: formData,
        type: "post",
        contentType: false,
        processData: false,
        cache: false,
        dataType: "json",
        success: function (html) {
            let dropfile = $('#dropfile');
            dropfile.css('border', '3px dashed #BBBBBB');
            dropfile.after(html);
        }
    });
}

function InitModalArrivage(modal, submit, path, table, callback = null, close = true) {
    submit.click(function () {
        submitActionArrivage(modal, path, table, callback, close);
    });
}

function submitActionArrivage(modal, path, table, callback, close) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {

        if (this.readyState == 4 && this.status == 200) {
            $('.errorMessage').html(JSON.parse(this.responseText));
            data = JSON.parse(this.responseText);
            $('p.attachement').each(function() {
                $(this).remove();
            });
            if (data.redirect) {
                window.location.href = data.redirect + '/1';
                return;
            }
            // pour mise à jour des données d'en-tête après modification
            if (data.entete) {
                $('.zone-entete').html(data.entete)
            }
            table.ajax.reload(function (json) {
                if (this.responseText !== undefined) {
                    $('#myInput').val(json.lastInput);
                }
            });

            clearModal(modal);

            if (callback !== null) callback(data);
            if (close == true) {
                modal.find('.close').click();
            }
        }
    };

    // On récupère toutes les données qui nous intéressent
    // dans les inputs...
    let inputs = modal.find(".data");
    let Data = {};
    let missingInputs = [];
    let wrongNumberInputs = [];
    let passwordIsValid = true;
    inputs.each(function () {
        let val = $(this).val();
        let name = $(this).attr("name");
        Data[name] = val;
        // validation données obligatoires
        if ($(this).hasClass('needed') && (val === undefined || val === '' || val === null)) {
            let label = $(this).closest('.form-group').find('label').text();
            // on enlève l'éventuelle * du nom du label
            label = label.replace(/\*/, '');
            missingInputs.push(label);
            $(this).addClass('is-invalid');
        }
        // validation valeur des inputs de type number
        if ($(this).attr('type') === 'number') {
            let val = parseInt($(this).val());
            let min = parseInt($(this).attr('min'));
            let max = parseInt($(this).attr('max'));
            if (val > max || val < min || isNaN(val)) {
                wrongNumberInputs.push($(this));
                $(this).addClass('is-invalid');
            }
        }
        // validation valeur des inputs de type password
        if ($(this).attr('type') === 'password') {
            let password = $(this).val();
            let isNotChanged = $(this).hasClass('optional-password') && password === "";
            if (!isNotChanged) {
                if (password.length < 8) {
                    modal.find('.password-error-msg').html('Le mot de passe doit faire au moins 8 caractères.');
                    passwordIsValid = false;
                } else if (!password.match(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/)) {
                    modal.find('.password-error-msg').html('Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre, un caractère spécial (@$!%*?&).');
                    passwordIsValid = false;
                } else {
                    passwordIsValid = true;
                }
            }
        }
    });

    // ... et dans les checkboxes
    let checkboxes = modal.find('.checkbox');
    checkboxes.each(function () {
        Data[$(this).attr("name")] = $(this).is(':checked');
    });
    $("div[name='id']").each(function () {
        Data[$(this).attr("name")] = $(this).attr('value');
    });
    modal.find(".elem").remove();
    // si tout va bien on envoie la requête ajax...
    if (missingInputs.length == 0 && wrongNumberInputs.length == 0 && passwordIsValid) {
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
        if (wrongNumberInputs.length > 0) {
            wrongNumberInputs.forEach(function (elem) {
                let label = elem.closest('.form-group').find('label').text();

                msg += 'La valeur du champ ' + label;

                let min = elem.attr('min');
                let max = elem.attr('max');

                if (typeof (min) !== 'undefined' && typeof (max) !== 'undefined') {
                    if (min > max) {
                        msg += " doit être inférieure à " + max + ".<br>";
                    } else {
                        msg += ' doit être comprise entre ' + min + ' et ' + max + ".<br>";
                    }
                } else if (typeof (min) == 'undefined') {
                    msg += ' doit être inférieure à ' + max + ".<br>";
                } else if (typeof (max) == 'undefined') {
                    msg += ' doit être supérieure à ' + min + ".<br>";
                } else if (min < 1) {
                    msg += ' ne peut pas être rempli'
                }

            })
        }
        modal.find('.error-msg').html(msg);
    }
}

$submitSearchArrivage.on('click', function () {
    let dateMin = $('#dateMin').val();
    let dateMax = $('#dateMax').val();
    let statut = $('#statut').val();
    let utilisateur = $('#utilisateur').val();
    let utilisateurString = utilisateur.toString();
    let utilisateurPiped = utilisateurString.split(',').join('|');

    saveFilters(PAGE_ARRIVAGE, dateMin, dateMax, statut, utilisateurPiped);

    tableArrivage
        .columns('Statut:name')
        .search(statut ? '^' + statut + '$' : '', true, false)
        .draw();

    tableArrivage
        .columns('Destinataire:name')
        .search(utilisateurPiped ? '^' + utilisateurPiped + '$' : '', true, false)
        .draw();

    tableArrivage
        .draw();
});

function deleteAttachementNew(pj) {
    let params = {
        pj: pj
    };
    $.post(Routing.generate('remove_one_kept_pj', true), JSON.stringify(params), function() {
        $('#modalNewArrivage').find('p.attachement').each(function() {
            if ($(this).attr('id') === pj) $(this).remove();
        });
    });
}

function generateCSVArrivage () {
    loadSpinner($('#spinnerArrivage'));
    let data = {};
    $('.filterService, select').first().find('input').each(function () {
        if ($(this).attr('name') !== undefined) {
            data[$(this).attr('name')] = $(this).val();
        }
    });

    if (data['dateMin'] && data['dateMax']) {
        let params = JSON.stringify(data);
        let path = Routing.generate('get_arrivages_for_csv', true);

        $.post(path, params, function(response) {
            if (response) {
                $('.error-msg').empty();
                let csv = "";
                $.each(response, function (index, value) {
                    csv += value.join(';');
                    csv += '\n';
                });
                aFile(csv);
                hideSpinner($('#spinnerArrivage'));
            }
        }, 'json');

    } else {
        $('.error-msg').html('<p>Saisissez une date de départ et une date de fin dans le filtre en en-tête de page.</p>');
        hideSpinner($('#spinnerArrivage'))
    }
}

let aFile = function (csv) {
    let d = new Date();
    let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
    date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
    let exportedFilenmae = 'export-arrivage-' + date + '.csv';
    let blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, exportedFilenmae);
    } else {
        let link = document.createElement("a");
        if (link.download !== undefined) {
            let url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", exportedFilenmae);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
}
