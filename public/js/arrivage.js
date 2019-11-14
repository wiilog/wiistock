// const allowedExtensions = ['pdf', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'doc', 'docx', 'ppt', 'pptx', 'csv', 'txt'];

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

function listColis(elem) {
    let arrivageId = elem.data('id');
    let path = Routing.generate('arrivage_list_colis_api', true);
    let modal = $('#modalListColis');
    let params = { id: arrivageId };

    $.post(path, JSON.stringify(params), function(data) {
        modal.find('.modal-body').html(data);
    }, 'json');
}

function getDataAndPrintLabels(codes) {
    let path = Routing.generate('arrivage_get_data_to_print', true);
    let param = codes;

    $.post(path, JSON.stringify(param), function (response) {
        let codeColis = [];
        if (response.response.exists) {
            for(const code of response.codeColis) {
                codeColis.push(code.code)
            }
            printBarcodes(codeColis, response.response, ('Etiquettes.pdf'));
        }
    });
}

function printBarcode(code) {
    let path = Routing.generate('get_print_data', true);

    $.post(path, function (response) {
        printBarcodes([code], response, ('Etiquette_' + code + '.pdf'));
    });
}

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
initModalNewArrivage(modalNewArrivage, submitNewArrivage, urlNewArrivage);

let editorNewArrivageAlreadyDone = false;
let quillNew;

function initNewArrivageEditor(modal) {
    if (!editorNewArrivageAlreadyDone) {
        quillNew = initEditor(modal + ' .editor-container-new');
        editorNewArrivageAlreadyDone = true;
    }
}

function dragEnterDiv(event, div) {
    displayWrong(div);
}

function dragOverDiv(event, div) {
    event.preventDefault();
    event.stopPropagation();
    displayWrong(div);
    return false;
}

function dragLeaveDiv(event, div) {
    event.preventDefault();
    event.stopPropagation();
    displayNeutral(div);
    return false;
}

function dropNewOnDiv(event, div) {
    if (event.dataTransfer) {
        if (event.dataTransfer.files.length) {
            event.preventDefault();
            event.stopPropagation();
            displayRight(div);
            displayAttachements(event.dataTransfer.files, div);
        }
    } else {
        displayWrong(div);
    }
    return false;
}

function uploadFENew(span) {
    let files = $('#fileInputNew')[0].files;
    let div = span.closest('.dropFrame');

    displayAttachements(files, div);
}

function displayAttachements(files, div) {

    let valid = checkFilesFormat(files, div);

    if (valid) {
        let dropfile = $('#dropfileNew');

        $.each(files, function(index, file) {
            let fileName = file.name;

            let reader = new FileReader();
            reader.addEventListener('load', function() {
                dropfile.after(`
                    <p class="attachement" id="` + withoutExtension(fileName) + `">
                        <a target="_blank" href="`+ reader.result + `">
                            <i class="fa fa-file mr-2"></i>` + fileName + `
                        </a>
                        <i class="fa fa-times red pointer" onclick="removeAttachementNew($(this))"></i>
                    </p>`);
            });

            reader.readAsDataURL(file);
        });
        clearErrorMsg(div);
    }
}

function withoutExtension(fileName) {
    let array = fileName.split('.');
    return array[0];
}

function checkFilesFormat(files, div) {
    let valid = true;
    $.each(files, function (index, file) {
        if (file.name.includes('.') === false) {
            div.closest('.modal-body').next('.error-msg').html("Le format de votre pièce jointe n'est pas supporté. Le fichier doit avoir une extension.");
            displayWrong(div);
            valid = false;
        } else {
            displayRight(div);
        }
    });
    return valid;
}

function openFENew() {
    $('#fileInputNew').click();
}


function removeAttachementNew($elem) {
    $elem.closest('.attachement').remove();
}

function initModalNewArrivage(modal, submit, path) {
    submit.click(function () {
        submitActionArrivage(modal, path);
    });
}

function submitActionArrivage(modal, path) {
    // On récupère toutes les données qui nous intéressent
    // dans les inputs...
    let inputs = modal.find(".data");
    let Data = new FormData();
    let missingInputs = [];
    let wrongNumberInputs = [];
    let passwordIsValid = true;
    inputs.each(function () {
        let val = $(this).val();
        let name = $(this).attr("name");
        Data.append(name, val);
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
        Data.append([$(this).attr("name")], $(this).is(':checked'));
    });
    $("div[name='id']").each(function () {
        Data.append([$(this).attr("name")], $(this).attr('value'));
    });
    modal.find(".elem").remove();

    // ... puis on récupère les fichiers ...
    let files = $('#fileInputNew')[0].files;

    $.each(files, function(index, file) {
        Data.append('file' + index, file);
    });

    // si tout va bien on envoie la requête ajax...
    if (missingInputs.length == 0 && wrongNumberInputs.length == 0 && passwordIsValid) {
        $.ajax({
            url: path,
            data: Data,
            type: 'post',
            contentType: false,
            processData: false,
            cache: false,
            dataType: 'json',
            success: (data) => {
                if (data.redirect) {
                    window.location.href = data.redirect + '/1';
                    return;
                }
            }
        });
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