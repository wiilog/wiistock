const confirmationModalId = 'confirmation-modal'

//fixes the backdrop when multiple modals are open
$(document).on('show.bs.modal', '.modal', function() {
    const zIndex = 1040 + 10 * $('.modal:visible').length;
    $(this).css('z-index', zIndex);
    setTimeout(() => $('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack'));
});

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
                    <button type="button" class="btn btn-outline-secondary discard" data-dismiss="modal">
                        <span>Annuler</span>
                    </button>
                    <button name='request' type="button" class="btn data confirm">
                        <span>Supprimer</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
`);

export default class Modal {
    static confirm({ajax, message, title, validateButton, cancelButton, table, keepOnError, cancelled}) {
        keepOnError = keepOnError === undefined ? true : keepOnError;

        const {label: validateLabel, color: validateColor, click: validateClick} = validateButton || {};
        const {hidden: cancelHidden, label: cancelLabel} = cancelButton || {};
        let $modal = $(`#${confirmationModalId}`);

        let confirmed = false;

        if (!$modal.exists()) {
            $('body').append($CONFIRMATION_MODAL);
            $modal = $CONFIRMATION_MODAL;
        }

        const $validateButton = $modal.find('button.confirm');
        const $title = $modal.find('.modal-title');
        const $message = $modal.find('.modal-body');
        const $cancelButton = $modal.find('button.discard');

        if (title) {
            $title.text(title);
        }

        $message.html(message);

        $validateButton
            .addClass(`btn-${validateColor || 'success'}`)
            .html(validateLabel ? `<span>${validateLabel}</span>` : '<span>Confirmer</span>');

        $cancelButton.text(cancelLabel || 'Annuler');

        if (cancelHidden) {
            $cancelButton.addClass('d-none');
        }

        $modal.on('hidden.bs.modal', function() {
            $modal.remove();
            if (!confirmed && cancelled) {
                cancelled();
            }
        });

        $validateButton.off('click').on('click', () => {
            if (validateClick) {
                confirmed = true;
                validateClick();
            }
            if (ajax) {
                const {method, route, params} = ajax;
                wrapLoadingOnActionButton($validateButton, () => {
                    return AJAX.route(method, route, params)
                        .json()
                        .then((result) => {
                            if (result.success || !keepOnError) {
                                $modal.modal('hide');
                            }
                            if (!table && result.redirect) {
                                window.location.href = result.redirect;
                            }
                            if (table) {
                                table.ajax.reload();
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
