let arrivageUrgentLoading = false;

function arrivalCallback(isCreation, {success, alertConfigs = [], ...response}, arrivalsDatatable = null) {
    if (alertConfigs.length > 0) {
        const alertConfig = alertConfigs[0];
        const {autoHide, message, modalType, arrivalId, iconType, autoPrint} = alertConfig;
        const nextAlertConfigs = alertConfigs.slice(1, alertConfigs.length);
        const isLastModal = (modalType !== 'yes-no-question' && nextAlertConfigs.length === 0);

        const buttonConfigs = createButtonConfigs({
            modalType,
            arrivalId,
            alertConfig,
            nextAlertConfigs,
            response,
            isCreation,
            arrivalsDatatable,
            success,
            autoPrint
        });

        const displayCurrentModal = () => {
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

        if (isLastModal) {
            const $alertModal = getBSAlertModal();
            if ($alertModal.hasClass('show')) {
                $alertModal
                    .find('.modal-footer-wrapper')
                    .addClass('d-none');
                loadSpinner($alertModal.find('.spinner'));
            }

            const {arrivageId} = response;

            if (isCreation) {
                $.post(Routing.generate('post_arrival_tracking_movements', {arrival: arrivageId}))
                    .then(() => {
                        displayCurrentModal();

                        if (autoPrint) {
                            printArrival(response);
                        }
                    })
                    .catch(() => {
                        showBSAlert('Erreur lors de la création des mouvements de tracaçabilité', 'danger');
                    });
            }
            else {
                displayCurrentModal();

                if (autoPrint) {
                    printArrival(response);
                }
            }
        }
        else {
            displayCurrentModal();
        }
    }
}

function setArrivalUrgent(newArrivalId, numeroCommande, postNb, arrivalResponseCreation, isCreation, arrivalsDatatable) {
    const patchArrivalUrgentUrl = Routing.generate('patch_arrivage_urgent', {arrival: newArrivalId});
    $.ajax({
        type: 'PATCH',
        url: patchArrivalUrgentUrl,
        data: {numeroCommande, postNb, isCreation},
        success: (secondResponse) => {
            arrivageUrgentLoading = false;
            if (secondResponse.success) {
                arrivalCallback(
                    isCreation,
                    {
                        ...arrivalResponseCreation,
                        alertConfigs: arrivalResponseCreation.alertConfigs.length > 0
                            ? arrivalResponseCreation.alertConfigs
                            : secondResponse.alertConfigs,
                    },
                    arrivalsDatatable
                );
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

function treatArrivalCreation({redirectAfterAlert, printColis, printArrivage, arrivageId}, arrivalsDatatable, success = null) {
    if (!redirectAfterAlert) {
        if (arrivalsDatatable) {
            arrivalsDatatable.ajax.reload();
        }

        if (success) {
            success();
        }
    }
    else {
        window.location.href = createArrivageShowUrl(redirectAfterAlert, printColis, printArrivage);
    }
}

function createArrivageShowUrl(arrivageShowUrl, printColis, printArrivage) {
    const printColisNumber = (printColis === true) ? '1' : '0';
    const printArrivageNumber = (printArrivage === true) ? '1' : '0';
    return `${arrivageShowUrl}/${printColisNumber}/${printArrivageNumber}`;
}

function printArrival({arrivageId, printColis, printArrivage}) {
    if (printArrivage || printColis) {
        let params = {
            arrivage: arrivageId,
            printColis: printColis ? 1 : 0,
            printArrivage: printArrivage ? 1 : 0
        };
        window.location.href = Routing.generate('print_arrivage_bar_codes', params, true);
    }
}

function createButtonConfigs({modalType, arrivalId, alertConfig, nextAlertConfigs, response, isCreation, arrivalsDatatable, success, autoPrint}) {

    const buttonConfigs = [
        {
            class: 'btn btn-primary m-0 btn-action-on-hide',
            text: (modalType === 'yes-no-question' ? 'Oui' : 'Continuer'),
            action: ($modal) => {
                if (modalType === 'yes-no-question') {
                    if (!arrivageUrgentLoading) {
                        arrivageUrgentLoading = true;
                        $modal.find('.modal-footer-wrapper').addClass('d-none');
                        loadSpinner($modal.find('.spinner'));
                        setArrivalUrgent(
                            arrivalId,
                            alertConfig.numeroCommande,
                            alertConfig.postNb,
                            {alertConfigs: nextAlertConfigs, success, ...response},
                            isCreation,
                            arrivalsDatatable
                        );
                    }
                }
                else {
                    // si c'est la dernière modale on ferme la modale d'alerte et on traite la création d'arrivage sinon
                    if (nextAlertConfigs.length === 0) {
                        if (isCreation) {
                            treatArrivalCreation(response, arrivalsDatatable, success);
                        }
                        $modal.modal('hide');
                    }
                    else {
                        arrivalCallback(isCreation, {alertConfigs: nextAlertConfigs, ...response}, arrivalsDatatable);
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
                arrivalCallback(
                    isCreation,
                    {
                        alertConfigs: nextAlertConfigs.length > 0
                            ? nextAlertConfigs
                            : [{
                                autoHide: false,
                                autoPrint,
                                message: 'Arrivage enregistré avec succès',
                                modalType: 'info',
                                iconType: 'success',
                                arrivalId
                            }],
                        success,
                        ...response
                    },
                    arrivalsDatatable
                );
            }
        });
    }

    return buttonConfigs;
}
