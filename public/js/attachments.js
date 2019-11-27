function displayAttachements(files, dropFrame) {

    let valid = checkFilesFormat(files, dropFrame);
    if (valid) {
        $.each(files, function(index, file) {
            let fileName = file.name;

            let reader = new FileReader();
            reader.addEventListener('load', function() {
                dropFrame.after(`
                    <p class="attachement" value="` + withoutExtension(fileName)+ `">
                        <a target="_blank" href="`+ reader.result + `">
                            <i class="fa fa-file mr-2"></i>` + fileName + `
                        </a>
                        <i class="fa fa-times red pointer" onclick="removeAttachement($(this))"></i>
                    </p>`);
            });
            reader.readAsDataURL(file);
        });
        clearErrorMsg(dropFrame);
    }
}

function withoutExtension(fileName) {
    let array = fileName.split('.');
    return array[0];
}

function removeAttachement($elem) {
    $elem.closest('.attachement').remove();
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

let droppedFiles = [];

function dropOnDiv(event, div) {
    if (event.dataTransfer) {
        if (event.dataTransfer.files.length) {
            event.preventDefault();
            event.stopPropagation();
            let array = Array.from(event.dataTransfer.files);
            droppedFiles = [...droppedFiles, ...array];
            displayRight(div);
            displayAttachements(event.dataTransfer.files, div);
        }
    } else {
        displayWrong(div);
    }
    return false;
}

function openFE(span) {
    span.closest('.modal').find('.fileInput').click();
}

function uploadFE(span) {
    let files = span[0].files;
    let dropFrame = span.closest('.dropFrame');

    displayAttachements(files, dropFrame);
}

function initModalWithAttachments(modal, submit, path, table = null, callback = null, close = true, clear = true) {
    submit.click(function () {
        submitActionWithAttachments(modal, path, table, callback, close, clear);
    });
}

function submitActionWithAttachments(modal, path, table, callback, close, clear) {
    // On récupère toutes les données qui nous intéressent
    // dans les inputs...
    let inputs = modal.find(".data");
    let inputsArray = modal.find(".data-array");

    let Data = new FormData();
    let missingInputs = [];
    let wrongNumberInputs = [];
    let passwordIsValid = true;
    let name;
    let vals = [];
    let arrayIdVal = {};

    inputsArray.each(function () {
        arrayIdVal = {id: $(this).data('id'), val: $(this).val()};
        vals.push(arrayIdVal);
        name = $(this).attr("name");
        Data.append(name, JSON.stringify(vals));
    });

    inputs.each(function () {
        let val = $(this).val();
        name = $(this).attr("name");
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

    // ... puis on récupère les fichiers (issus du clic)...
    let files = modal.find('.fileInput')[0].files;
    // ... (issus du drag & drop)
    files = [...files, ...droppedFiles];

    $.each(files, function(index, file) {
        Data.append('file' + index, file);
    });

    // si tout va bien on envoie la requête ajax...
    if (missingInputs.length == 0 && wrongNumberInputs.length == 0 && passwordIsValid) {
        if (close == true) modal.find('.close').click();

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
                    let print = null;
                    if (data.printColis === true && data.printArrivage === true) {
                        print = '/1/1';
                    } else if (data.printColis === true && data.printArrivage !== true) {
                        print = '/1/0';
                    } else if (data.printColis !== true && data.printArrivage === true) {
                        print = '/0/1';
                    } else if (data.printColis !== true && data.printArrivage !== true) {
                        print = '/0/0';
                    }
                    window.location.href = data.redirect + print;
                    return;
                }

                if (table) {
                    table.ajax.reload(function (json) {
                        if (data !== undefined) {
                            $('#myInput').val(json.lastInput);
                        }
                    }, false);
                }

                // mise à jour des données d'en-tête après modification
                if (data.entete) {
                    $('.zone-entete').html(data.entete)
                }

                if (clear) clearModal(modal);

                if (callback !== null) callback(data);
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