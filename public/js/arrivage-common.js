let arrivageUrgentLoading = false;

function arrivalCallback(isCreation, {alertConfigs = [], ...response}) {
    if (alertConfigs.length > 0) {
        const alertConfig = alertConfigs[0];
        const {autoHide, message, modalType, arrivalId, iconType} = alertConfig;
        const nextAlertConfigs = alertConfigs.slice(1, alertConfigs.length);

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
                            setArrivalUrgent(arrivalId, {alertConfigs: nextAlertConfigs, ...response}, isCreation);
                        }
                    }
                    else {
                        // si c'est la dernière modale on ferme la modale d'alerte et on traite la création d'arrivage sinon
                        if (nextAlertConfigs.length === 0) {
                            if (isCreation) {
                                treatArrivalCreation(response);
                            }
                            $modal.modal('hide')
                        }
                        else {
                            arrivalCallback(isCreation, {alertConfigs: nextAlertConfigs, ...response});
                        }
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
                        alertConfigs: nextAlertConfigs.length > 0
                            ? nextAlertConfigs
                            : [{
                                    autoHide: false,
                                    message: 'Arrivage enregistré avec succès',
                                    modalType: 'info',
                                    iconType: 'success',
                                    arrivalId
                                }],
                        ...response
                    });
                }
            });
        }

        displayAlertModal(
            undefined,
            $('<div/>', {
                class: 'text-center',
                html: message
            }),
            buttonConfigs,
            iconType,
            autoHide
        );
    }
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
                    alertConfigs: arrivalResponseCreation.alertConfigs.length > 0
                        ? arrivalResponseCreation.alertConfigs
                        : secondResponse.alertConfigs,
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

function treatArrivalCreation({redirectAfterAlert, printColis, printArrivage, arrivageId, champsLibresBlock, statutConformeId}) {
    if (!redirectAfterAlert) {
        $modalNewArrivage.find('.champsLibresBlock').html(champsLibresBlock);
        $('.list-multiple').select2();
        $modalNewArrivage.find('#statut').val(statutConformeId);
        let isPrintColisChecked = $modalNewArrivage.find('#printColisChecked').val();
        $modalNewArrivage.find('#printColis').prop('checked', isPrintColisChecked);

        let params = {
            arrivage: arrivageId,
            printColis: printColis ? 1 : 0,
            printArrivage: printArrivage ? 1 : 0
        };

        window.location.href = Routing.generate('print_arrivage_bar_codes', params, true);
    }
    else {
        const arrivalShowUrl = createArrivageShowUrl(redirectAfterAlert, printColis, printArrivage);
        window.location.href = arrivalShowUrl;
    }
}

function createArrivageShowUrl(arrivageShowUrl, printColis, printArrivage) {
    const printColisNumber = (printColis === true) ? '1' : '0';
    const printArrivageNumber = (printArrivage === true) ? '1' : '0';
    return `${arrivageShowUrl}/${printColisNumber}/${printArrivageNumber}`;
}
