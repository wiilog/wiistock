const FILE_MAX_SIZE = 2000000;

function displayAttachements(files, $dropFrame, isMultiple = true) {

    let errorMsg = [];

    const $fileBag = $dropFrame.siblings('.file-bag');

    if (!isMultiple) {
        $fileBag.empty();
    }

    $.each(files, function(index, file) {
        let formatValid = checkFileFormat(file, $dropFrame);
        let sizeValid = checkSizeFormat(file, $dropFrame);

        if (!formatValid) {
            errorMsg.push('"' + file.name + '" : Le format de votre pièce jointe n\'est pas supporté. Le fichier doit avoir une extension.');
        } else if (!sizeValid) {
            errorMsg.push('"' + file.name + '" : La taille du fichier ne doit pas dépasser 2 Mo.');
        } else {
            let fileName = file.name;

            let reader = new FileReader();
            reader.addEventListener('load', function () {
                $fileBag.append(`
                    <p class="attachement" value="` + withoutExtension(fileName) + `">
                        <a target="_blank" href="` + reader.result + `">
                            <i class="fa fa-file mr-2"></i>` + fileName + `
                        </a>
                        <i class="fa fa-times red pointer" onclick="removeAttachement($(this))"></i>
                    </p>`);
            });
            reader.readAsDataURL(file);
        }
    });

    if (errorMsg.length === 0) {
        displayRight($dropFrame);
        clearErrorMsg($dropFrame);
    } else {
        displayWrong($dropFrame);
        $dropFrame.closest('.modal').find('.error-msg').html(errorMsg.join("<br>"));
    }

}

function withoutExtension(fileName) {
    let array = fileName.split('.');
    return array[0];
}

function removeAttachement($elem) {
    let deleted = false;
    let fileName = $elem.closest('.attachement').find('a').first().text().trim();
    $elem.closest('.attachement').remove();
    droppedFiles.forEach(file => {
        if (file.name === fileName && !deleted) {
            deleted = true;
            droppedFiles.splice(droppedFiles.indexOf(file), 1);
        }
    });
}

function checkFileFormat(file) {
    return file.name.includes('.') !== false;
}

function checkSizeFormat(file) {
    return file.size < FILE_MAX_SIZE;
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

function openFileExplorer(span) {
    span.closest('.modal').find('.fileInput').trigger('click');
}

function saveDroppedFiles(event, $div) {
    if (event.dataTransfer) {
        if (event.dataTransfer.files.length) {
            event.preventDefault();
            event.stopPropagation();
            let files = Array.from(event.dataTransfer.files);

            const $inputFile = $div.find('.fileInput');
            saveInputFiles($inputFile, files);
        }
    }
    else {
        displayWrong($div);
    }
    return false;
}

function saveInputFiles($inputFile, files) {
    let filesToSave = files || $inputFile[0].files;
    const isMultiple = $inputFile.prop('multiple');

    Array.from(filesToSave).forEach(file => {
        if (checkSizeFormat(file) && checkFileFormat(file)) {
            if (!isMultiple) {
                droppedFiles = [];
            }
            droppedFiles.push(file);
        }
    });

    let dropFrame = $inputFile.closest('.dropFrame');

    displayAttachements(filesToSave, dropFrame, isMultiple);
    $inputFile[0].value = '';
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
    $('.is-invalid').removeClass('is-invalid');
    let Data = new FormData();
    let missingInputs = [];
    let wrongNumberInputs = [];
    let passwordIsValid = true;
    let name;
    let arrayIdVal = {};
    let totalForInputsArrayNeededPositive = {};

    inputsArray.each(function () {
        name = $(this).attr("name");
        if ($(this).hasClass('needed-positiv')) {
            if (!totalForInputsArrayNeededPositive[name]) {
                totalForInputsArrayNeededPositive[name] = 0;
            }
            totalForInputsArrayNeededPositive[name] += Number($(this).val());
        }
        if (!arrayIdVal[name]) {
            arrayIdVal[name] = {};
        }
        arrayIdVal[name][$(this).data('id')] = $(this).val();
    });
    for (const name in arrayIdVal) {
        Data.append(name, JSON.stringify(arrayIdVal[name]));
    }
    inputs.each(function () {
        let val = $(this).val();
        name = $(this).attr("name");
        Data.append(name, val);
        // validation données obligatoires
        if ($(this).hasClass('needed') && (val === undefined || val === '' || val === null || (Array.isArray(val) && val.length === 0))) {
            let label = $(this).closest('.form-group').find('label').text();
            // on enlève l'éventuelle * du nom du label
            label = label.replace(/\*/, '');
            missingInputs.push(label);
            $(this).addClass('is-invalid');
            $(this).next().find('.select2-selection').addClass('is-invalid');
        }
        // validation valeur des inputs de type number
        if ($(this).attr('type') === 'number') {
            let val = parseInt($(this).val());
            let min = parseInt($(this).attr('min'));
            let max = parseInt($(this).attr('max'));
            if (val > max || val < min) {
                wrongNumberInputs.push($(this));
                $(this).addClass('is-invalid');
            } else if (!isNaN(val)) {
                $(this).removeClass('is-invalid');
            }
            if ($(this).is(':disabled') === true) {
                $(this).removeClass('is-invalid');
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
    $.each(droppedFiles, function(index, file) {
        Data.append('file' + index, file);
    });
    // si tout va bien on envoie la requête ajax...
    const inputArrayNames = Object.keys(totalForInputsArrayNeededPositive);
    const firstNameNeededPositiveArray = inputArrayNames.reduce(
        (lastName, currentName) => {
            return (!lastName && totalForInputsArrayNeededPositive[currentName] === 0)
                ? currentName
                : lastName
        },
        undefined);
    if (missingInputs.length === 0 && wrongNumberInputs.length === 0 && passwordIsValid && !firstNameNeededPositiveArray) {
        if (close) modal.find('.close').click();
        clearInvalidInputs(modal);
        clearErrorMsg(modal.find(':first-child'));
        $.ajax({
            url: path,
            data: Data,
            type: 'post',
            contentType: false,
            processData: false,
            cache: false,
            dataType: 'json',
            success: (data) => {
                if (data.success === false) {
                    $(modal).find('.error-msg').html(data.msg);
                } else {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }

                    if (table) {
                        table.ajax.reload(null, false);
                    }

                    // mise à jour des données d'en-tête après modification
                    if (data.entete) {
                        $('.zone-entete').html(data.entete)
                    }

                    if (clear) {
                        clearModal(modal);
                    }
                    droppedFiles = [];
                    if (callback !== null) callback(data);
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
        if (firstNameNeededPositiveArray) {
            msg += "Veuillez renseigner au moins un " + firstNameNeededPositiveArray + ".<br>";
            $('.data-array.needed-positiv[name="' + firstNameNeededPositiveArray + '"]').addClass('is-invalid');
        }
        modal.find('.error-msg').html(msg);
    }
}
