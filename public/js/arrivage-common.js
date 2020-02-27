let arrivageUrgentLoading = false;

function arrivalCallback(isCreation, {alertConfig = {}, ...response}) {
    const {autoHide, message, modalType, arrivalId, iconType} = alertConfig;

    const buttonConfigs = [
        {
            class: 'btn btn-success m-0 btn-action-on-hide',
            text: (modalType === 'yes-no-question' ? 'Oui' : 'Continuer'),
            action: ($modal) => {
                if (modalType === 'yes-no-question') {
                    if (!arrivageUrgentLoading) {
                        arrivageUrgentLoading = true;
                        $modal.find('.modal-footer-wrapper').addClass('d-none');
                        loadSpinner($modal.find('.spinner'));
                        setArrivalUrgent(arrivalId, response, isCreation);
                    }
                }
                else {
                    if (isCreation) {
                        treatArrivalCreation(response);
                    }
                    $modal.modal('hide')
                }
            }
        }
    ];

    if (modalType === 'yes-no-question') {
        buttonConfigs.unshift({
            class: 'btn btn-secondary m-0',
            text: 'Non',
            action: () => {
                arrivalCallback(isCreation, {
                    alertConfig: {
                        autoHide: false,
                        message: 'Arrivage enregistré avec succès',
                        modalType: 'info',
                        iconType: 'success',
                        arrivalId
                    },
                    ...response
                });
            }
        });
    }

    displayAlertModal(
        undefined,
        $('<div/>', {
            class: 'text-center',
            text: message
        }),
        buttonConfigs,
        iconType,
        autoHide
    );
}

function setArrivalUrgent(newArrivalId, arrivalResponseCreation, isCreation) {
    const patchArrivalUrgentUrl = Routing.generate('patch_arrivage_urgent', {arrival: newArrivalId});
    $.ajax({
        type: 'PATCH',
        url: patchArrivalUrgentUrl,
        success: (secondResponse) => {
            arrivageUrgentLoading = false;
            if (secondResponse.success) {
                arrivalCallback(isCreation, {
                    alertConfig: secondResponse.alertConfig,
                    ...arrivalResponseCreation
                });
                $('.zone-entete').html(secondResponse.entete);
            }
            else {
                displayAlertModal(
                    undefined,
                    $('<div/>', {
                        class: 'text-center',
                        text: 'Erreur dans la mise en urgence de l\'arrivage.'
                    }),
                    [{
                        class: 'btn btn-secondary m-0',
                        text: 'OK',
                        action: ($modal) => {
                            $modal.modal('hide')
                        }
                    }],
                    'error'
                );
            }
        }
    });
}