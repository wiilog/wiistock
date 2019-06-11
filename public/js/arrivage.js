$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Demandeur',
    }
});

let pathArrivage = Routing.generate('arrivage_api', true);
let tableArrivage = $('#tableArrivages').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    "order": [[0, "desc"]],
    ajax: {
        "url": pathArrivage,
        "type": "POST"
    },
    columns: [
        { "data": "NumeroArrivage", 'name': 'NumeroArrivage', 'title': "N° d'arrivage" },
        { "data": 'Transporteur', 'name': 'Transporteur', 'title': 'Transporteur' },
        { "data": 'NoTracking', 'name': 'NoTracking', 'title': 'N° tracking transporteur' },
        { "data": 'NumeroBL', 'name': 'NumeroBL', 'title': 'N° commande / BL' },
        { "data": 'Fournisseur', 'name': 'Fournisseur', 'title': 'Fournisseur' },
        { "data": 'Destinataire', 'name': 'Destinataire', 'title': 'Destinataire' },
        { "data": 'NbUM', 'name': 'NbUM', 'title': 'Nb UM' },
        { "data": 'Statut', 'name': 'Statut', 'title': 'Statut' },
        { "data": 'Date', 'name': 'Date', 'title': 'Date' },
        { "data": 'Utilisateur', 'name': 'Utilisateur', 'title': 'Utilisateur' },
        { "data": 'Actions', 'name': 'Actions', 'title': 'Actions' },
    ],

});

let editorNewArrivageAlreadyDone = false;
function initNewArrivageEditor(modal) {
    if (!editorNewArrivageAlreadyDone) {
        initEditor2(modal + ' .editor-container-new');
        editorNewArrivageAlreadyDone = true;
    }
};

let modalNewArrivage = $("#modalNewArrivage");
let submitNewArrivage = $("#submitNewArrivage");
let urlNewArrivage = Routing.generate('arrivage_new', true);
InitialiserModalArrivage(modalNewArrivage, submitNewArrivage, urlNewArrivage, tableArrivage);

let modalModifyArrivage = $('#modalEditArrivage');
let submitModifyArrivage = $('#submitEditArrivage');
let urlModifyArrivage = Routing.generate('arrivage_edit', true);
InitialiserModal(modalModifyArrivage, submitModifyArrivage, urlModifyArrivage, tableArrivage);

let modalDeleteArrivage = $('#modalDeleteArrivage');
let submitDeleteArrivage = $('#submitDeleteArrivage');
let urlDeleteArrivage = Routing.generate('arrivage_delete', true);
InitialiserModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage, tableArrivage);

function toggleLitige(select) {
    let bloc = select.closest('.modal').find('#litigeBloc');
    let status = select.find('option:selected').text();

    let litigeType = bloc.find('#litigeType');
    let constantConform = $('#constantConforme').val();

    if (status === constantConform) {
        litigeType.removeClass('needed');
        bloc.addClass('d-none');

    } else {
        bloc.removeClass('d-none');
        litigeType.addClass('needed');
    }
}

function deleteRowArrivage(button, modal, submit, hasLitige) {
    deleteRow(button, modal, submit);
    let hasLitigeText = modal.find('.hasLitige');
    if (hasLitige) {
        hasLitigeText.removeClass('d-none');
    } else {
        hasLitigeText.addClass('d-none');
    }
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
            // Main function to upload
            upload(event.dataTransfer.files);
        }
    } else {
        div.css('border', '3px dashed #BBBBBB');
    }
    return false;
}

function upload(files) {

    let formData = new FormData();
    $.each(files, function (index, file) {
        formData.append('file' + index, file);
    });
    let path = Routing.generate('arrivage_depose', true);

    let arrivageId = $('#dropfile').data('arrivage-id');
    formData.append('id', arrivageId);

    let filepath = '/uploads/attachements/';

    $.ajax({
        url: path,
        data: formData,
        type:"post",
        contentType:false,
        processData:false,
        cache:false,
        dataType:"json",
        success:function(fileNames){
            let dropfile = $('#dropfile');
            dropfile.css('border', '3px dashed #BBBBBB');
            fileNames.forEach(function(fileName) {
                dropfile.after('<p><i class="fa fa-file mr-2"></i><a target="_blank" href="' + filepath + fileName + '">' + fileName + '</a></p>');
            })
        }
    });
}

function editRowArrivage(button) {
    let path = Routing.generate('arrivage_edit_api', true);
    let modal = $('#modalEditArrivage');
    let submit = $('#submitEditArrivage');
    let id = button.data('id');
    let params = { id: id };

    $.post(path, JSON.stringify(params), function(data) {
        modal.find('.modal-body').html(data.html);
        initEditor2('.editor-container-edit');
        $('#modalEditArrivage').find('#acheteurs').select2('val', data.acheteurs); //TODO CG 1 seul fonctionne... ???
    }, 'json');

    modal.find(submit).attr('value', id);
}

function InitialiserModalArrivage(modal, submit, path, table, callback = null, close = true) {
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

            if (data.redirect) {
                window.location.href = data.redirect;
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
            let nbUm = data.nbUm;
            let printUm = data.printUm;
            let printArrivage = data.printArrivage;
            console.log(data.arrivage);
            let d = new Date();
            let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
            date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
            if (data.exists) {
                let doc = adjustScalesForDoc(data);
                $("#barcodes").empty();
                if (printUm) {
                    for (let i = 0; i < nbUm; i++) {
                        console.log('colis ' + i);
                        $('#barcodes').append('<img id="barcode' + i + '">');
                        JsBarcode("#barcode" + i, data.arrivage + '-' + i, {
                            format: "CODE128",
                        });
                    }

                } if (printArrivage) {
                    console.log('arrivage');
                    $('#barcodes').append('<img id="barcodeArrivage">');
                    JsBarcode("#barcodeArrivage", data.arrivage, {
                        format: "CODE128",
                    });
                } if (printArrivage || printUm) {
                    console.log('arrivage or colis');
                    $("#barcodes").find('img').each(function () {
                        doc.addImage($(this).attr('src'), 'JPEG', 0, 0, doc.internal.pageSize.getWidth(), doc.internal.pageSize.getHeight());
                        doc.addPage();
                    });
                    doc.deletePage(doc.internal.getNumberOfPages())
                    doc.save('Etiquettes du ' + date + '.pdf');
                }
            } else {
                $('#cannotGenerate').click();
            }
            if (callback !== null) callback(data);
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
            if (val > max || val < min) {
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
        if (close == true) modal.find('.close').click();
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

function checkZero(data) {
    if (data.length == 1) {
        data = "0" + data;
    }
    return data;
}
// function deleteAttachement() {
//
// }