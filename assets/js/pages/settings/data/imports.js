global.importTemplateChanged = importTemplateChanged;
global.displayFirstModal = displayFirstModal;
global.openConfirmCancelModal = openConfirmCancelModal;
global.deleteImport = deleteImport;
global.updateOptions = updateOptions;
global.launchImport = launchImport;
global.toggleImportType = toggleImportType;
global.toggleFrequencyInput = toggleFrequencyInput;
global.selectHourlyFrequencyIntervalType = selectHourlyFrequencyIntervalType;
global.openConfirmForceModal = openConfirmForceModal;
global.displayEditImportModal = displayEditImportModal;

const TEMPLATES_DIRECTORY = `/template`;
const DOWNLOAD_TEMPLATES_CONFIG = {
    ART: {label: 'articles', url: `${TEMPLATES_DIRECTORY}/modele-import-articles.csv`},
    REF: {label: 'références', url: `${TEMPLATES_DIRECTORY}/modele-import-references.csv`},
    FOU: {label: 'fournisseurs', url: `${TEMPLATES_DIRECTORY}/modele-import-fournisseurs.csv`},
    ART_FOU: {label: 'articles fournisseurs', url: `${TEMPLATES_DIRECTORY}/modele-import-articles-fournisseurs.csv`},
    RECEP: {label: 'réceptions', url: `${TEMPLATES_DIRECTORY}/modele-import-receptions.csv`},
    USER: {label: 'utilisateurs', url: `${TEMPLATES_DIRECTORY}/modele-import-utilisateurs.csv`},
    DELIVERY: {label: 'livraisons', url: `${TEMPLATES_DIRECTORY}/modele-import-livraisons.csv`},
    LOCATION: {label: 'emplacements', url: `${TEMPLATES_DIRECTORY}/modele-import-emplacements.csv`},
    CUSTOMER: {label: 'clients', url: `${TEMPLATES_DIRECTORY}/modele-import-clients.csv`},
    PROJECT: {label: 'projets', url: `${TEMPLATES_DIRECTORY}/modele-import-projets.csv`},
    REF_LOCATION: {label: 'quantités référence par emplacement', url: `${TEMPLATES_DIRECTORY}/modele-import-reference-emplacement-quantites.csv`},
}

let tableImport;

export function initializeImports() {
    initDateTimePicker('#dateMin, #dateMax');

    getUserFiltersByPage(PAGE_IMPORT);

    let tableImportConfig = {
        processing: true,
        serverSide: true,
        ajax: {
            url: Routing.generate(`import_api`),
            type: `POST`
        },
        columns: [
            {data: `actions`, title: ``, orderable: false, className: `noVis`},
            {data: `id`, visible: false},
            {data: `unableToConnect`, title: ``, className: `noVis`, orderable: false},
            {data: `status`, title: `Statut`},
            {data: `createdAt`, title: `Date de création`},
            {data: `startDate`, title: `Date début`},
            {data: `endDate`, title: `Date fin`},
            {data: `nextExecutionDate`, title: `Prochaine exécution`, orderable: false},
            {data: `frequency`, title: `Fréquence`, orderable: false},
            {data: `label`, title: `Nom import`},
            {data: `newEntries`, title: `Nvx enreg.`},
            {data: `updatedEntries`, title: `Mises à jour`},
            {data: `nbErrors`, title: `Nombre d'erreurs`},
            {data: `user`, title: `Utilisateur`},
            {data: `type`, title: `Type`},
            {data: `entity`, title: `Type de données importées`},
        ],
        rowConfig: {
            needsRowClickAction: true
        },
        drawConfig: {
            callback: () => initDoubleClick(`.status-planifié`),
            needsSearchOverride: true,
        },
        order: [[`id`, `desc`]],
        page: `import`,
    };
    tableImport = initDataTable(`tableImport`, tableImportConfig);

    $(document).on(`click`, `button.edit-import`, function() {
        const $editImportModal = $(`#modal-edit-import`)
        const id = $(this).data(`id`);

        $editImportModal.modal(`show`);

        Form.create($editImportModal)
            .clearOpenListeners()
            .clearSubmitListeners()
            .onOpen(() => {
                Modal.load(`import_edit_api`, {id}, $editImportModal);
                toggleImportType($editImportModal.find('[name="unique-import-checkbox"][checked], [name="scheduled-import-checkbox"][checked]'));
                toggleFrequencyInput($editImportModal.find('[name="frequency-radio"]:checked'));
                importTemplateChanged($editImportModal.find('[name="entity"]'));
            })
            .submitTo(`POST`, `import_edit`, {
                tables: [tableImport],
                keepModal: true,
                success: (data) => {
                    displaySecondModalMaker($editImportModal, data)
                },
            });
    });

    $(document).on(`click`, `button.force-import`, function() {
        const $forceImportModal = $(`#modalConfirmForce`);
        const id = $(this).data(`id`);

        $forceImportModal.modal(`show`);

        Form.create($forceImportModal)
            .clearOpenListeners()
            .clearSubmitListeners()
            .onSubmit((data, form) => {
                form.loading(() => (
                    AJAX.route(AJAX.POST, `import_force`, {import: id})
                        .json()
                        .then(() => tableImport.ajax.reload())
                        .catch(() => Flash.add(Flash.ERROR, `Erreur lors du déclenchement forcé de l'import.`))
                ), true, true)
            });
    });
}

export function initializeExports() {
    initDateTimePicker('#dateMin, #dateMax');
}

function displayFirstModal($button, importId = null) {
    let $modalNewImport = $("#modalNewImport");
    let $submitNewImport = $("#submitNewImport");

    let $inputImportId = $modalNewImport.find('[name="importId"]');

    $inputImportId.val('');
    $submitNewImport.off();

    Form.create($modalNewImport)
        .clearOpenListeners()
        .clearSubmitListeners()
        .onOpen(() => {
            Modal.load('get_first_modal_content', {import: importId}, $modalNewImport, $modalNewImport.find(`.modal-body`), {
                onOpen: () => {
                    if (importId) {
                        $inputImportId.val(importId);
                    }

                    toggleImportType($modalNewImport.find('[value=unique-import-checkbox]:checked, [value=scheduled-import-checkbox]:checked'));
                    importTemplateChanged($modalNewImport.find('[name="entity"]'));
                    toggleFrequencyInput($modalNewImport.find('[name="frequency-radio"]:checked'));
                }});
        })
        .submitTo(`POST`, `import_new`, {
            tables: [tableImport],
            keepModal: true,
            success: (data) => {
                displaySecondModalMaker($modalNewImport, data)
            },
        });

    $modalNewImport.modal({
        backdrop: 'static',
        show: true
    });
}

function displayConfirmationModal($modal, importId, {success, msg, html}) {
    const $submitButton = $modal.find('.submit-form');
    $submitButton.off();
    if (html) {
        $modal.find('.modal-body').html(html);
        launchImport($modal, importId);
    }

    if (msg && success) {
        tableImport.ajax.reload();
        $modal.modal('hide');
    }
}

function openConfirmCancelModal(importId) { // TODO refacto avec le FORM
    let $submitCancelImport = $('#submitCancelImport');
    $submitCancelImport.off();
    $submitCancelImport.on('click', function () {
        $.post(Routing.generate('import_cancel'), {importId: importId}, function () {
            tableImport.ajax.reload();
        });
    });
    $('#modalConfirmCancel').modal('show');
}

function deleteImport($btn) { // TODO refacto avec le FORM
    let importId = $btn.closest('.modal').find('[name="importId"]').val();

    Form.create($(`#modalConfirmCancel`))
        .clearSubmitListeners()
        .onSubmit((data, form) => {
            form.loading(() => (
                AJAX.route(AJAX.POST, `import_delete`, {importId})
                    .json()
                    .then(() => tableImport.ajax.reload())
            ))
        });

    if (importId) {
        $.post(Routing.generate('import_delete'), {importId: importId}, () => {
            tableImport.ajax.reload();
        });
    }
}

function updateOptions($select) {
    let $tbody = $select.closest('tbody');
    let $allSelects = $tbody.find('select');
    let selectValue = $select.val();
    let selectedValues = [];

    $allSelects.each((index, element) => {
        $(element).find('option').removeAttr('disabled');
        let selectedValue = $(element).val();
        if (selectedValue !== '') {
            selectedValues.push('option[value="' + selectedValue + '"]');
        }
    });

    if (selectedValues.length > 0) {
        let $optionsToDisable = $tbody.find(selectedValues.join(','));
        $optionsToDisable.each(function () {
            if ($(this).closest('select').val() !== $(this).val()) {
                $(this).attr('disabled', 'disabled');
            }
        });
    }

    if (selectValue !== '') {
        $select.find('option[value="' + selectValue + '"]').removeAttr('disabled');
    }
}

function initDoubleClick(elem) {
    if ($(elem).length > 0) {
        document.querySelector(elem).addEventListener('click', function (e) {
            if (e.detail === 10) {
                let $modal = $('#modalLaunchPlanifiedImport');
                $modal.find('#importIdToLaunch').data('id', $(elem).data('id'));
                $modal.modal('show');
            }
        });
    }
}

function launchImport($modal, importId, force = false) {
    if (importId) {
        Form.create($modal)
            .clearSubmitListeners()
            .onSubmit((data, form) => {
                form.loading(() => (
                    AJAX.route(AJAX.POST, `import_launch`, {importId, force: Number(Boolean(force))})
                        .json()
                        .then(({success, message}) => {
                            if (!force) {
                                $modal.modal('hide');
                            }

                            Flash.add(success ? Flash.SUCCESS : Flash.ERROR, message);
                            tableImport.ajax.reload();
                        })
                ))
            });
    } else {
        Flash.add(Flash.ERROR, 'Une erreur est survenue lors du lancement de votre import. Veuillez recharger la page et réessayer.');
    }
}

function importTemplateChanged($dataTypeImport = null) {
    const $linkToTemplate = $('.link-to-template');

    $linkToTemplate.empty();

    const valTypeImport = $dataTypeImport ? $dataTypeImport.val() : '';
    differentialDataToggle($dataTypeImport);

    if (DOWNLOAD_TEMPLATES_CONFIG[valTypeImport]) {
        const {url, label} = DOWNLOAD_TEMPLATES_CONFIG[valTypeImport];
        $linkToTemplate
            .append(`
                <div class="col-12 wii-small-text">
                    Un <a class="underlined" href="${url}">fichier de modèle d\'import</a>  est disponible pour les ${label}.
                </div>`);
        if(valTypeImport === 'USER') {
            $linkToTemplate
                .append(`<div class="col-12 mt-3"><i class="fas fa-question-circle"></i>
                            <span class="wii-small-text">Les nouveaux utilisateurs seront créés avec un mot de passe aléatoire. Ils devront configurer ce dernier via la fonctionnalité "<strong>Mot de passe oublié</strong>".</span>
                        </div>`)
        }
    }
    else if (valTypeImport === '') {
        $linkToTemplate.append('<div class="col-12 wii-small-text">Des fichiers de modèles d\'import sont disponibles. Veuillez sélectionner un type de données à importer.</div>');
    }
    else {
        $linkToTemplate.append('<div class="col-12 wii-small-text">Aucun modèle d\'import n\'est disponible pour ce type de données.</div>');
    }
}

function differentialDataToggle($dataTypeImport) {
    const valTypeImport = $dataTypeImport ? $dataTypeImport.val() : '';
    const eraseData = valTypeImport !== `REF_LOCATION` && valTypeImport !== `ART_FOU`;
    if($dataTypeImport) {
        const $modal = $dataTypeImport.closest('.modal');
        $modal.find('.delete-differential-data')
            .toggleClass(`d-none`, eraseData)
            .html(`<input type="checkbox" name="deleteDifData" class="form-control data"/><p>Supprimer la donnée différentielle</p>`);
    }
}

function toggleImportType($input) {
    const $modal = $input.closest(`.modal`);
    const $attachmentLabel = $modal.find(`.attachment-label`);
    const $uniqueImportInput = $modal.find(`[name="unique-import-checkbox"]`);
    const $scheduledImportInput = $modal.find(`[name="scheduled-import-checkbox"]`);
    const $uniqueImportForm = $modal.find(`.unique-import`);
    const $scheduledImportForm = $modal.find(`.scheduled-import`);
    const $scheduledImportFilePath = $scheduledImportForm.find(`[name="path-import-file"]`);
    const $frequencies = $modal.find(`.frequency`);

    if($input.prop(`checked`)) {
        if($input.val() === `unique-import-checkbox`) {
            $attachmentLabel.text(`Fichier d'import`);
            $uniqueImportForm.removeClass(`d-none`);
            $scheduledImportForm.addClass(`d-none`);
            $scheduledImportFilePath.removeClass(`needed`);
            $scheduledImportInput.prop(`checked`, false);
            $frequencies
                .find(`select.frequency-data, input.frequency-data`)
                .removeClass(`data`)
                .removeClass(`needed`);
        }
        else if ($input.val() === `scheduled-import-checkbox`) {
            $attachmentLabel.text(`Fichier de paramétrage`);
            $scheduledImportForm.removeClass(`d-none`);
            $uniqueImportForm.addClass(`d-none`);
            $scheduledImportFilePath.addClass(`needed`);
            $uniqueImportInput.prop(`checked`, false);
            $scheduledImportForm.find(`.select2`).select2();
            $(document).on(`click`, `.select-all-options`, onSelectAll);
        }
    } else {
        if($input.val() === `unique-import-checkbox`) {
            $modal.find(`input[name!="${$input.val()}"]`).first().prop(`checked`, true);
            $uniqueImportForm.addClass(`d-none`);
            $scheduledImportForm.removeClass(`d-none`);
        } else if($input.val() === `scheduled-import-checkbox`) {
            $modal.find(`input[name!="${$input.val()}"]`).first().prop(`checked`, true);
            $scheduledImportForm.addClass(`d-none`)
            $uniqueImportForm.removeClass(`d-none`);
        }
    }

    importTemplateChanged();
    clearFormErrors($modal);
    $modal.find(`.attachement`).remove();
    $modal.find(`.isRight`).removeClass(`isRight`);
}

function toggleFrequencyInput($input) {
    const $modal = $input.closest(`.modal`);
    const $globalFrequencyContainer = $modal.find(`.frequency-content`);
    const inputName = $input.attr(`name`);
    const $inputChecked = $modal.find(`[name="${inputName}"]:checked`);
    const inputCheckedVal = $inputChecked.val();
    const $frequencyInput = $modal.find(`[name="frequency"]`);

    $frequencyInput.val(inputCheckedVal);

    $globalFrequencyContainer.addClass(`d-none`);
    $globalFrequencyContainer.find(`.frequency`).addClass(`d-none`);
    $globalFrequencyContainer
        .find(`input.frequency-data, select.frequency-data`)
        .removeClass(`data`)
        .removeClass(`needed`);
    $globalFrequencyContainer.find(`.is-invalid`).removeClass(`is-invalid`);

    if(inputCheckedVal) {
        $globalFrequencyContainer.removeClass(`d-none`);
        const $frequencyContainer = $globalFrequencyContainer.find(`.frequency.${inputCheckedVal}`);
        $frequencyContainer.removeClass(`d-none`);
        $frequencyContainer
            .find(`input.frequency-data, select.frequency-data`)
            .addClass(`needed`)
            .addClass(`data`);

        $frequencyContainer.find(`input[type="date"]`).each(function() {
            const $input = $(this);
            $input.attr(`type`, `text`);
            initDateTimePicker({dateInputs: $input, minDate: true, value: $input.val()});
        });
    }
}

function selectHourlyFrequencyIntervalType($select) {
    const $selectedOptionValue = $select.find(":selected").val();
    const $frequencyContent = $select.closest(`.frequency-content`);
    let $frequencyPeriodInput = $frequencyContent.find(`input[name="hourly-frequency-interval-period"]`);

    if ($selectedOptionValue === `minutes`) {
        $frequencyPeriodInput.attr(`max`, 30);
    } else if ($selectedOptionValue === `hours`) {
        $frequencyPeriodInput.attr(`max`, 12);
    }
}

function openConfirmForceModal(importId) {
    let $submitForceImport = $('#submitForceImport');
    $submitForceImport.off();
    $submitForceImport.on('click', function () {
        $.post(Routing.generate('import_force', {import: importId}))
            .then(function () {
                tableImport.ajax.reload();
                showBSAlert("L'import va être exécuté dans les prochaines minutes", "success");
            })
            .catch(function () {
                showBSAlert("Erreur lors du déclenchement forcé de l'import", "danger");
            });
    });
    $('#modalConfirmForce').modal('show');
}

function displayEditImportModal() {
    let $modalEditImport = $("#modal-edit-import");

    Form.create($modalEditImport)
        .clearOpenListeners()
        .clearSubmitListeners()
        .onOpen(() => {
            toggleImportType($modalEditImport.find('[value=unique-import-checkbox]:checked, [value=scheduled-import-checkbox]:checked'));
            toggleFrequencyInput($modalEditImport.find('[name="frequency-radio"]:checked'));
            importTemplateChanged($modalEditImport.find('[name="entity"]'));
        })
        .submitTo(`POST`, `import_edit`, {
            tables: [tableImport],
            keepModal: true,
            success: (data) => {
                displaySecondModalMaker($modalEditImport, data)
            },
        });
}

function displaySecondModalMaker($modal, data) {
    const $submitButton = $modal.find('.submit-button');
    const $modalBody = $modal.find('.modal-body');
    const importId = data.importId;

    $modalBody.html(data.html);
    $modal.find('[name="importId"]').val(importId);
    $submitButton.off();

    $(".import-options").each(function() {
        updateOptions($(this));
    });

    Form.create($modal)
        .clearOpenListeners()
        .clearSubmitListeners()
        .submitTo(`POST`, `import_links`, {
            keepModal: true,
            success: (data) => {
                displayConfirmationModal($modal, importId, data);
            },
        });
}
