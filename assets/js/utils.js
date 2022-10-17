export const MAX_UPLOAD_FILE_SIZE = 10000000;
export const MAX_IMAGE_PIXELS = 1000000;
export const ALLOWED_IMAGE_EXTENSIONS = [`png`, `jpeg`, `jpg`, `svg`, `gif`];

global.MAX_UPLOAD_FILE_SIZE = MAX_UPLOAD_FILE_SIZE;
global.MAX_IMAGE_PIXELS = MAX_IMAGE_PIXELS;
global.ALLOWED_IMAGE_EXTENSIONS = ALLOWED_IMAGE_EXTENSIONS;

global.updateImagePreview = updateImagePreview;
global.resetImage = resetImage;
global.onSettingsItemSelected = onSettingsItemSelected;

function updateImagePreview(preview, upload, $title = null, $delete = null, $callback = null) {
    let $upload = $(upload)[0];
    let formats;

    if($(upload).is('[accept]')) {
        const inputAcceptedFormats = $(upload).attr('accept').split(',');
        formats = inputAcceptedFormats.map((format) => {
            format = format.split("/").pop();
            return format.indexOf('+') > -1 ? format.substring(0, format.indexOf('+')) : format;
        });
    } else {
        formats = ALLOWED_IMAGE_EXTENSIONS;
    }

    if ($upload.files && $upload.files[0]) {
        let fileNameWithExtension = $upload.files[0].name.split('.');
        let extension = fileNameWithExtension[fileNameWithExtension.length - 1];

        if ($upload.files[0].size < MAX_UPLOAD_FILE_SIZE) {
            if (formats.indexOf(extension.toLowerCase()) !== -1) {
                if ($title) {
                    $title.text(fileNameWithExtension.join('.').substr(0, 5) + '...');
                    $title.attr('title', fileNameWithExtension.join('.'));
                    if($title.siblings('input[name=titleComponentLogo]').length > 0) {
                        $title.siblings('input[name=titleComponentLogo]').last().val($upload.files[0].name);
                    }
                }

                let reader = new FileReader();
                reader.onload = function (e) {
                    let image = new Image();

                    image.onload = function() {
                        const pixels = image.height * image.width;
                        if (pixels <= MAX_IMAGE_PIXELS) {
                            if ($callback) {
                                $callback($upload);
                            }
                            $(preview)
                                .attr('src', e.target.result)
                                .removeClass('d-none');
                            if ($delete) {
                                $delete.removeClass('d-none');
                            }
                        } else {
                            showBSAlert('Veuillez choisir une image ne faisant pas plus de 1000x1000', 'danger');
                        }
                    };

                    image.src = e.target.result;
                };

                reader.readAsDataURL($upload.files[0]);
            } else {
                showBSAlert(`Veuillez choisir un format d'image valide (${formats.join(`, `)})`, 'danger')
            }
        } else {
            showBSAlert('La taille du fichier doit être inférieure à 10Mo', 'danger')
        }
    }
}

function resetImage($button) {
    const $defaultValue = $button.siblings('.default-value');
    const $image = $button.siblings('.preview-container').find('.image');
    const $keepImage = $button.siblings('.keep-image');
    const $input = $button.siblings('[type="file"]');
    const defaultValue = $defaultValue.val();

    $input.val('');
    $image
        .attr('src', defaultValue)
        .removeClass('d-none');
    $keepImage.val(0);
}

function onSettingsItemSelected($selected, $settingsItems, $settingsContents, options = {}) {
    const $buttons = $('main .save');
    const selectedKey = $selected.data('menu');

    $settingsItems.removeClass('selected');
    $settingsContents.addClass('d-none');

    $selected.addClass('selected');
    const $menu = $settingsContents.filter(`[data-menu="${selectedKey}"]`);
    $menu.removeClass('d-none');

    const $firstMenu = $menu.find('.menu').first();
    if ($firstMenu.length) {
        onSettingsItemSelected($firstMenu, $('.menu'), $('.menu-content'));
    }
    if (options.hideClass && options.hiddenElement){
        if ($selected.hasClass(options.hideClass)) {
            options.hiddenElement.addClass('d-none');
        }
        else {
            options.hiddenElement.removeClass('d-none');
        }
    }

    $buttons.removeClass('d-none');
}
