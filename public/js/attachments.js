const FILE_MAX_SIZE = 2000000;

let droppedFiles = [];

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

function InitModalWithAttachments($modal, $submit, path, options = {}) {
    InitModal($modal, $submit, path, {
        getFiles: () => droppedFiles,
        ...options,
        success: (data) => {
            if (data && data.success) {
                if (options.success) {
                    options.success(data);
                }
                resetDroppedFiles();
            }
        }
    });
}

function resetDroppedFiles() {
    droppedFiles = [];
}
