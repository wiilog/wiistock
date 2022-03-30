const confirmationModalId = 'confirmation-modal'

const $CONFIRMATION_MODAL = $(`
    <div class="modal fade" role="dialog" aria-hidden="true" id="${confirmationModalId}">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title wii-title"></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Annuler</button>
                    <button name='request' type="button"
                            class="btn btn-danger data confirm">Supprimer</button>
                </div>
            </div>
        </div>
    </div>
`);

export default class Modal {
    static confirm({method, route, message, title, params, actionLabel, table, keepOnError}) {
        keepOnError = keepOnError === undefined ? true : keepOnError;

        let $modal = $(`#${confirmationModalId}`);
        if (!$modal.exists()) {
            $('body').append($CONFIRMATION_MODAL);
            $modal = $CONFIRMATION_MODAL;
        }

        const $action = $modal.find('button.confirm');
        const $title = $modal.find('.modal-title');
        const $message = $modal.find('.modal-body');

        $title.text(title);
        $message.text(message);
        $action.text(actionLabel);

        $modal.on('hidden.bs.modal', function() {
            $modal.remove();
        });

        $action.off('click').on('click', () => {
            wrapLoadingOnActionButton($action, () => {
                return AJAX.route(method, route, params)
                    .json()
                    .then((result) => {
                        if (result.success || !keepOnError) {
                            $modal.modal('hide');
                        }
                        if (result.redirect) {
                            window.location.href = result.redirect;
                        }
                    });
            });
        });

        $modal.modal('show');
    }
}
