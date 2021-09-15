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

global.initEditor = initEditor;
global.initEditorInModal = initEditorInModal;
