import AJAX from "@app/ajax";
import Form from "@app/form";
import Flash from "@app/flash";
import Modal from "@app/modal";
import Routing from '@app/fos-routing';
import {getUserFiltersByPage} from '@app/utils';
import {initDataTable} from "@app/datatable";

global.importTemplateChanged = importTemplateChanged;
global.displayFirstModal = displayFirstModal;
global.deleteImport = deleteImport;
global.updateOptions = updateOptions;
global.launchImport = launchImport;
global.toggleImportType = toggleImportType;

let tableImport;

export function initializeImports() {
    initDateTimePicker(`#dateMin, #dateMax`);
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
            {data: `information`, title: ``, className: `noVis`, orderable: false},
            {data: `lastErrorMessage`, title: ``, className: `noVis`, orderable: false},
            {data: `status`, title: `Statut`},
            {data: `createdAt`, title: `Date de création`},
            {data: `startDate`, title: `Date début`},
            {data: `endDate`, title: `Date fin`},
            {data: `nextExecution`, title: `Prochaine exécution`, orderable: false},
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
        order: [[`id`, `desc`]],
        page: `import`,
    };
    tableImport = initDataTable(`tableImport`, tableImportConfig);

    $(document).on(`click`, `button.force-import`, function() {
        const $modalForceImport = $(`#modalForceImport`);
        const id = $(this).data(`id`);

        $modalForceImport.find(`[name=importId]`).val(id);
        $modalForceImport.modal(`show`);
        launchImport($modalForceImport, id, true);
    });

    $(document).on(`click`, `button.delete-import`, function() {
        const $deleteImportModal = $(`#modalDeleteImport`);
        const id = $(this).data(`id`);

        $deleteImportModal.modal(`show`);

        Form.create($deleteImportModal)
            .clearSubmitListeners()
            .onSubmit((data, form) => {
                form.loading(() => deleteImport(undefined, id), true, {closeModal: true})
            });
    });

    $(document).on(`click`, `button.cancel-import`, function() {
        const $cancelImportModal = $(`#modalCancelImport`);
        const id = $(this).data(`id`);

        $cancelImportModal.modal(`show`);

        Form.create($cancelImportModal)
            .clearSubmitListeners()
            .onSubmit((data, form) => {
                form.loading(() => (
                    AJAX.route(AJAX.POST, `import_cancel`, {import: id})
                        .json()
                        .then(() => {
                            tableImport.ajax.reload();
                        })
                ), true, {closeModal: true})
            });
    });
}

function displayFirstModal($button, importId = null) {
    let $modalNewImport = $("#modalNewImport");
    let $submitNewImport = $("#submitNewImport");

    let $inputImportId = $modalNewImport.find(`[name="importId"]`);

    $inputImportId.val(``);
    $submitNewImport.off();

    Form.create($modalNewImport)
        .clearOpenListeners()
        .clearSubmitListeners()
        .onOpen(() => {
            Modal.load(`get_first_modal_content`, {import: importId}, $modalNewImport, $modalNewImport.find(`.modal-body`), {
                onOpen: () => {
                    if (importId) {
                        $inputImportId.val(importId);
                    }

                    toggleImportType($modalNewImport.find(`[value=unique-import-checkbox]:checked, [value=scheduled-import-checkbox]:checked`));
                    importTemplateChanged($modalNewImport.find(`[name=entity]`));
                    toggleFrequencyInput($modalNewImport.find(`[name=frequency]:checked`));
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
        backdrop: `static`,
        show: true
    });
}

function displayConfirmationModal($modal, importId, {success, msg, html}) {
    const $submitButton = $modal.find(`.submit-form`);
    $submitButton.off();
    if (html) {
        $modal.find(`.modal-body`).html(html);
        launchImport($modal, importId);
    }

    if (msg && success) {
        tableImport.ajax.reload();
        $modal.modal(`hide`);
    }
}

function deleteImport($button = undefined, id = undefined) {
    const importId = id || $button.closest(`.modal`).find(`[name=importId]`).val();

    if(importId) {
        return AJAX.route(AJAX.POST, `import_delete`, {import: importId})
            .json()
            .then(() => tableImport.ajax.reload());
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

function launchImport($modal, importId, force = false) {
    if (importId) {
        Form.create($modal)
            .clearSubmitListeners()
            .onSubmit((data, form) => {
                form.loading(() => (
                    AJAX.route(AJAX.POST, `import_launch`, {importId, force: Number(Boolean(force))})
                        .json()
                        .then(({success}) => {
                            if (success) {
                                tableImport.ajax.reload();
                            }
                        })
                ), true, {closeModal: true})
            });
    } else {
        Flash.add(Flash.ERROR, `Une erreur est survenue lors du lancement de votre import. Veuillez recharger la page et réessayer.`);
    }
}

function importTemplateChanged($dataTypeImport) {
    const $linkToTemplate = $(`.link-to-template`);

    $linkToTemplate.empty();



    const valTypeImport = $dataTypeImport.val();
    differentialDataToggle($dataTypeImport);

    const importTemplates = $dataTypeImport.find(`option`)
        .map((_, option) => ({
            value: $(option).val(),
            text: $(option).text().trim(),
        }))
        .toArray()
        .filter(Boolean);

    const templateConfig = importTemplates.find(({value}) => value === valTypeImport);
    if (valTypeImport && templateConfig) {
        const {text: label} = templateConfig;

        const url = Routing.generate(`import_template`, {entity: valTypeImport});
        $linkToTemplate
            .append(`
                <div class="col-12 wii-small-text">
                    Un <a class="underlined" href="${url}">fichier de modèle d'import</a> est disponible pour les ${label.toLowerCase()}.
                </div>
            `);
        if(valTypeImport === `USER`) {
            $linkToTemplate
                .append(`
                    <div class="col-12 mt-3">
                        <i class="fas fa-question-circle"></i>
                        <span class="wii-small-text">Les nouveaux utilisateurs seront créés avec un mot de passe aléatoire. Ils devront configurer ce dernier via la fonctionnalité "<strong>Mot de passe oublié</strong>".</span>
                    </div>
                `)
        }
    }
    else {
        $linkToTemplate.append(`<div class="col-12 wii-small-text">Des fichiers de modèles d'import sont disponibles. Veuillez sélectionner un type de données à importer.</div>`);
    }
}

function differentialDataToggle($dataTypeImport) {
    const valTypeImport = $dataTypeImport ? $dataTypeImport.val() : ``;
    const eraseData = valTypeImport !== `REF_LOCATION` && valTypeImport !== `ART_FOU`;
    if($dataTypeImport) {
        const $modal = $dataTypeImport.closest(`.modal`);
        $modal.find(`.delete-differential-data`)
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

    importTemplateChanged($modal.find('[entity]'));
    clearFormErrors($modal);
    $modal.find(`.attachement`).remove();
    $modal.find(`.isRight`).removeClass(`isRight`);
}

function displaySecondModalMaker($modal, data) {
    const $modalBody = $modal.find(`.modal-body`);
    const importId = data.importId;

    $modalBody.html(data.html);
    $modal.find(`[name="importId"]`).val(importId);

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
