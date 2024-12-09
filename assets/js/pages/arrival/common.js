let arrivageUrgentLoading = false;

export function arrivalCallback(isCreation, {success, alertConfigs = [], ...response}, arrivalsDatatable = null) {
    if (alertConfigs.length > 0) {
        const alertConfig = alertConfigs[0];
        const {autoHide, message, modalType, arrivalId, iconType, autoPrint, title, modalKey} = alertConfig;
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
            autoPrint,
            modalKey
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

            const {arrivalId} = response;

            if (isCreation) {
                $.post(Routing.generate('post_arrival_tracking_movements', {arrival: arrivalId}))
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

export function printArrival({arrivalId, printPacks, printArrivage, packs, printAll}) {
    let templates;
    try {
        templates = JSON.parse($('#tagTemplates').val());
    } catch (error) {
        templates = [];
    }
    let params = {
        arrivage: arrivalId,
        printPacks: printPacks ? 1 : 0,
        printArrivage: printArrivage ? 1 : 0,
        packs: packs || [],
    };
    const printAllPacks = printAll || false;
    if (templates.length > 0 && !printAllPacks) {
        Promise.all(
            [AJAX.route('GET', `print_arrivage_bar_codes`, {forceTagEmpty: true, ...params}).file({})]
                .concat(templates.map(function(template) {
                    params.template = template;
                    return AJAX
                        .route('GET', `print_arrivage_bar_codes`, params)
                        .file({})
                }))
        ).then(() => Flash.add('success', 'Impression des étiquettes terminée.'));
    } else {
        if (printArrivage || printPacks) {
            window.location.href = Routing.generate('print_arrivage_bar_codes', params, true);
        }
    }
}

export function checkPossibleCustoms($modal) {
    const isCustoms = $modal.find('[name="customs"]').is(':checked');
    const $select = $modal.find('[name="fournisseur"]')

    const $selectedSupplier = $select.find(':selected');
    const possibleCustom = $selectedSupplier.data('possible-customs');

    return new Promise((resolve) => {
        if (!isCustoms && possibleCustom){
            displayAlertModal(
                undefined,
                $('<div/>', {
                    class: 'text-center',
                    html: `Attention, ce fournisseur livre habituellement des unités logistiques sous douanes.`
                        + ` Voulez-vous modifier votre saisie pour déclarer l'unité logistique' sous douanes ?`
                }),
                [
                    {
                        class: 'btn btn-outline-secondary m-0',
                        text: 'Non',
                        action: ($modal) => {
                            resolve(true);
                            $modal.modal('hide');
                        }
                    },
                    {
                        class: 'btn btn-success m-0',
                        text: 'Oui',
                        action: ($modal) => {
                            resolve(false);
                            $modal.modal('hide');
                        }
                    }
                ],
                'warning'
            );
        }
        else {
            resolve(true);
        }
    });

}

function redirectWithReserve(newArrivalId) {
    $.post(Routing.generate('post_arrival_tracking_movements', {arrival: newArrivalId}))
        .then(() => {
            window.location.href = Routing.generate('arrivage_show', {
                id: newArrivalId,
                reserve: true
            }, true)
        })
        .catch(() => {
            showBSAlert('Erreur lors de la création des mouvements de tracaçabilité', 'danger');
        });
}

function setArrivalUrgent(newArrivalId, numeroCommande, postNb, arrivalResponseCreation, isCreation, arrivalsDatatable) {
    $.ajax({
        type: 'PATCH',
        url: Routing.generate('patch_arrivage_urgent', {arrival: newArrivalId}),
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
                        class: 'btn btn-outline-secondary m-0',
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

function treatArrivalCreation({redirectAfterAlert, printPacks, printArrivage, arrivageId}, arrivalsDatatable, success = null) {
    if (!redirectAfterAlert) {
        if (arrivalsDatatable) {
            arrivalsDatatable.ajax.reload();
        }

        if (success) {
            success();
        }
    }
    else {
        window.location.href = createArrivageShowUrl(redirectAfterAlert, printPacks, printArrivage);
    }
}

function createArrivageShowUrl(arrivageShowUrl, printPacks, printArrivage) {
    const printPacksNumber = (printPacks === true) ? '1' : '0';
    const printArrivageNumber = (printArrivage === true) ? '1' : '0';
    return `${arrivageShowUrl}?printPacks=${printPacksNumber}&printArrivage=${printArrivageNumber}`;
}

function createButtonConfigs({modalType,
                                 arrivalId,
                                 alertConfig,
                                 nextAlertConfigs,
                                 response,
                                 isCreation,
                                 arrivalsDatatable,
                                 success,
                                 autoPrint,
                                 modalKey}) {

    const buttonConfigs = [
        {
            class: 'btn btn-success m-0 btn-action-on-hide',
            text: (modalType === 'yes-no-question' ? (modalKey === 'reserve' ? 'Confirmer' : 'Oui') : 'Continuer'),
            action: ($modal) => {
                if (modalKey === 'reserve') {
                    redirectWithReserve(arrivalId);
                } else if (modalType === 'yes-no-question') {
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
            class: 'btn btn-outline-secondary m-0',
            text: (modalKey === 'reserve' ? 'Passer' : 'Non'),
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
