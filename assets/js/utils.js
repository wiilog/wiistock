export const MAX_UPLOAD_FILE_SIZE = 10000000;
export const MAX_IMAGE_PIXELS = 1000000;
export const ALLOWED_IMAGE_EXTENSIONS = [`png`, `jpeg`, `jpg`, `svg`, `gif`];

export function initEditorInModal(modal) {
    initEditor(`${modal} .editor-container`);
}

export function initEditor(div) {
    // protection pour éviter erreur console si l'élément n'existe pas dans le DOM
    if ($(div).length) {
        return new Quill(div, {
            modules: {
                toolbar: [
                    [{header: [1, 2, 3, false]}],
                    ['bold', 'italic', 'underline', 'image'],
                    [{'list': 'ordered'}, {'list': 'bullet'}]
                ]
            },
            formats: [
                'header',
                'bold', 'italic', 'underline', 'strike', 'blockquote',
                'list', 'bullet', 'indent', 'link', 'image'
            ],
            theme: 'snow'
        });
    }
    return null;
}

function updateImagePreview(preview, upload, $title = null, $delete = null, $callback = null) {
    let $upload = $(upload)[0];

    if ($upload.files && $upload.files[0]) {
        let fileNameWithExtension = $upload.files[0].name.split('.');
        let extension = fileNameWithExtension[fileNameWithExtension.length - 1];

        if ($upload.files[0].size < MAX_UPLOAD_FILE_SIZE) {
            if (ALLOWED_IMAGE_EXTENSIONS.indexOf(extension.toLowerCase()) !== -1) {
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
                showBSAlert(`Veuillez choisir une image valide (${ALLOWED_IMAGE_EXTENSIONS.join(`, `)})`, 'danger')
            }
        } else {
            showBSAlert('La taille du fichier doit être inférieure à 10Mo', 'danger')
        }
    }
}

function resetImage($button) {
    const $defaultValue = $button.siblings('.default-value');
    const $image = $button.siblings('.preview-container').first().find('.image');
    const $input = $button.siblings('[type="file"]');
    const defaultValue = $defaultValue.val();

    $input.val('');
    $image.attr('src', defaultValue);
}

global.MAX_UPLOAD_FILE_SIZE = MAX_UPLOAD_FILE_SIZE;
global.MAX_IMAGE_PIXELS = MAX_IMAGE_PIXELS;
global.ALLOWED_IMAGE_EXTENSIONS = ALLOWED_IMAGE_EXTENSIONS;

global.initEditor = initEditor;
global.initEditorInModal = initEditorInModal;
global.updateImagePreview = updateImagePreview;
global.resetImage = resetImage;
