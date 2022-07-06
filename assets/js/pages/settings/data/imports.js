global.importTemplateChanged = importTemplateChanged;
global.displayFirstModal = displayFirstModal;
global.openConfirmCancelModal = openConfirmCancelModal;
global.deleteImport = deleteImport;
global.updateOptions = updateOptions;
global.launchImport = launchImport;

let $modalNewImport = $("#modalNewImport");
let $submitNewImport = $("#submitNewImport");
let tableImport;

export function initializeImports() {
    initDateTimePicker('#dateMin, #dateMax');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_IMPORT);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');

    let pathImport = Routing.generate('import_api');
    let tableImportConfig = {
        processing: true,
        serverSide: true,
        ajax: {
            "url": pathImport,
            "type": "POST"
        },
        columns: [
            {"data": 'actions', 'title': '', orderable: false, className: 'noVis'},
            {"data": 'id', visible: false},
            {"data": 'status', 'title': 'Statut'},
            {"data": 'startDate', 'title': 'Date début'},
            {"data": 'endDate', 'title': 'Date fin'},
            {"data": 'label', 'title': 'Nom import'},
            {"data": 'newEntries', 'title': 'Nvx enreg.'},
            {"data": 'updatedEntries', 'title': 'Mises à jour'},
            {"data": 'nbErrors', 'title': "Nombre d'erreurs"},
            {"data": 'user', 'title': 'Utilisateur'},
            {"data" : 'entity','title' : 'Type de données importées'},
        ],
        rowConfig: {
            needsRowClickAction: true
        },
        drawConfig: {
            callback: () => initDoubleClick('.status-planifié'),
            needsSearchOverride: true,
        },
        order: [['id', "desc"]],
    };
    tableImport = initDataTable('tableImport', tableImportConfig);
}

export function initializeExports() {
    console.log('huh');
    initDateTimePicker('#dateMin, #dateMax');
}

function displayFirstModal(importId = null) {
    let $inputImportId = $modalNewImport.find('[name="importId"]');

    clearModal($modalNewImport);
    $inputImportId.val('');
    $submitNewImport.off();
    let urlNewImportFirst = Routing.generate('import_new', true);
    InitModal(
        $modalNewImport,
        $submitNewImport,
        urlNewImportFirst,
        {tables: [tableImport], success: displaySecondModal, keepModal: true}
    );

    $.get(Routing.generate('get_first_modal_content', {importId: importId}, true), function (resp) {
        $modalNewImport.find('.modal-body').html(resp);
        if (importId) {
            $inputImportId.val(importId);
        }

        importTemplateChanged();

        $modalNewImport.modal({
            backdrop: 'static',
            show: true
        });
    });
}

function displaySecondModal(data) {
    if (data.success) {
        const importId = data.importId;
        $modalNewImport.find('.modal-body').html(data.html);
        $modalNewImport.find('[name="importId"]').val(importId);
        $submitNewImport.off();

        let urlNewImportSecond = Routing.generate('import_links', true);
        InitModal(
            $modalNewImport,
            $submitNewImport,
            urlNewImportSecond,
            {
                keepModal: true,
                success: (data) => displayConfirmationModal(importId, data),
                // WIIS-3187 validator: validateImport
            }
        );

        $(".import-options").each(function() {
            updateOptions($(this));
        });
    }
}

/*
WIIS-3187
Validator si sélection de doublons dans les champs

function validateImport() {
    const selects = $('.import-options');
    const first_select = selects.first();

    const required = first_select.children()
        .map(function() {
            return $(this);
        })
        .filter((_, option) => option.html().includes("*"))
        .map((_, option) => option.val())
        .toArray();

    selects.each(function() {
        const value = $(this).val();
        let index;

        if(value && (index = required.indexOf(value)) !== -1) {
            required.splice(index, 1);
        }
    });

    if(required.length) {
        let error = "Les valeurs suivantes ne sont pas renseignées : ";

        let iterations = required.length;
        for(let value of required) {
            error += $(`.import-options option[value="${value}"]`).html();

            if (--iterations) {
                error += ", ";
            }
        }

        $('.error-msg').text(error);
    }

    return false;
}
*/

function displayConfirmationModal(importId, data) {
    $modalNewImport.find('.modal-body').html(data.html);
    $submitNewImport.off();

    $submitNewImport.click(() => {
        wrapLoadingOnActionButton($submitNewImport, () => launchImport(importId));

    });
}

function openConfirmCancelModal(importId) {
    let $submitCancelImport = $('#submitCancelImport');
    $submitCancelImport.off();
    $submitCancelImport.on('click', function () {
        $.post(Routing.generate('import_cancel'), {importId: importId}, function () {
            tableImport.ajax.reload();
        });
    });
    $('#modalConfirmCancel').modal('show');
}

function deleteImport($btn) {
    let importId = $btn.closest('.modal').find('[name="importId"]').val();

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
        if (selectedValue != '') {
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

    if (selectValue != '') {
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

function launchImport(importId, force = false) {
    if (importId) {
        const params = {
            importId,
            force: Number(Boolean(force))
        };

        return $.post(Routing.generate('import_launch'), params, (resp) => {
            if (!force) {
                $modalNewImport.modal('hide');
            }

            showBSAlert(resp.message, (resp.success ? 'success' : 'danger'));

            tableImport.ajax.reload();
        });
    } else {
        showBSAlert('Une erreur est survenue lors du lancement de votre import. Veuillez recharger la page et réessayer.', 'danger');
        return new Promise(() => {});
    }
}

function importTemplateChanged($dataTypeImport = null) {
    const $linkToTemplate = $('#linkToTemplate');

    $linkToTemplate.empty();

    const templateDirectory = '/modele';
    const configDownloadLink = {
        ART: {label: 'articles', url: `${templateDirectory}/modele-import-articles.csv`},
        REF: {label: 'références', url: `${templateDirectory}/modele-import-references.csv`},
        FOU: {label: 'fournisseurs', url: `${templateDirectory}/modele-import-fournisseurs.csv`},
        ART_FOU: {label: 'articles fournisseurs', url: `${templateDirectory}/modele-import-articles-fournisseurs.csv`},
        RECEP: {label: 'réceptions', url: `${templateDirectory}/modele-import-receptions.csv`},
        USER: {label: 'utilisateurs', url: `${templateDirectory}/modele-import-utilisateurs.csv`},
        DELIVERY: {label: 'livraisons', url: `${templateDirectory}/modele-import-livraisons.csv`},
        LOCATION: {label: 'emplacements', url: `${templateDirectory}/modele-import-emplacements.csv`},
    };

    const valTypeImport = $dataTypeImport ? $dataTypeImport.val() : '';
    if (configDownloadLink[valTypeImport]) {
        const {url, label} = configDownloadLink[valTypeImport];
        $linkToTemplate
            .append(`<div class="col-12">Un fichier de modèle d\'import est disponible pour les ${label}.</div>`)
            .append(`<div class="col-12"><a class="btn btn-primary" href="${url}">Télécharger</a></div>`);
        if(valTypeImport === 'USER') {
            $linkToTemplate
                .append(`<div class="col-12 mt-3"><i class="fas fa-question-circle"></i>
                            <span class="italic">Les nouveaux utilisateurs seront créés avec un mot de passe aléatoire. Ils devront configurer ce dernier via la fonctionnalité "<strong>Mot de passe oublié</strong>".</span>
                        </div>`)
        }
    }
    else if (valTypeImport === '') {
        $linkToTemplate.append('<div class="col-12">Des fichiers de modèles d\'import sont disponibles. Veuillez sélectionner un type de données à importer.</div>');
    }
    else {
        $linkToTemplate.append('<div class="col-12">Aucun modèle d\'import n\'est disponible pour ce type de données.</div>');
    }
}
