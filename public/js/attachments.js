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

function dropOnDiv(event, div) {
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

function openFENew() {
    $('#fileInputNew').click();
}

function openFEEdit() {
    $('#fileInput').click();
}

function uploadFE(span) {
    let files = span[0].files;
    let dropFrame = span.closest('.dropFrame');

    displayAttachements(files, dropFrame);
}