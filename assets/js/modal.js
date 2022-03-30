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
                    <button type="button" class="btn btn-outline-secondary discard" data-dismiss="modal">Annuler</button>
                    <button name='request' type="button"
                            class="btn data confirm">Supprimer</button>
                </div>
            </div>
        </div>
    </div>
`);

export default class Modal {
    static confirm({ajax, message, title, action, discard, table, keepOnError}) {
        keepOnError = keepOnError === undefined ? true : keepOnError;
        discard = discard === undefined ? true : discard;
        const {label: actionLabel, color: actionColor} = action;
        let $modal = $(`#${confirmationModalId}`);
        if (!$modal.exists()) {
            $('body').append($CONFIRMATION_MODAL);
            $modal = $CONFIRMATION_MODAL;
        }

        const $action = $modal.find('button.confirm');
        const $title = $modal.find('.modal-title');
        const $message = $modal.find('.modal-body');
        const $discard = $modal.find('button.discard');

        if (title) {
            $title.text(title);
        }

        $message.html(message);

        $action
            .addClass(`btn-${actionColor}`)
            .text(actionLabel);

        if (!discard) {
            $discard.addClass('d-none');
        }

        $modal.on('hidden.bs.modal', function() {
            $modal.remove();
        });

        $action.off('click').on('click', () => {
            if (ajax) {
                const {method, route, params} = ajax;
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
            }
            else {
                $modal.modal('hide');
            }
        });

        $modal.modal('show');
    }
}
