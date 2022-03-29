export default class Modal {
    static registerDeletionConfirmationModal($modal, route, message, title, params, table, keepOnError = true) {
        const $confirmButton = $modal.find('button.confirm');
        const $title = $modal.find('.modal-title');
        const $message = $modal.find('.modal-body');

        $title.text(title);
        $message.text(message);

        $confirmButton.off('click');
        $confirmButton.on('click', () => {
            wrapLoadingOnActionButton($confirmButton, () => {
                return AJAX.route(`DELETE`, route, params)
                    .json()
                    .then((result) => {
                        if (result.success || !keepOnError) {
                            $modal.modal('hide');
                        }
                    })
            })
        });

        $modal.modal('show');
    }
}
