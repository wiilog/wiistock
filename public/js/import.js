$(function () {
    initDateTimePicker('#dateMin, #dateMax');
    initSelect2($('#statut'), 'Statuts');
    ajaxAutoUserInit($('.filters .ajax-autocomplete-user'), 'Utilisateurs');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_IMPORT);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');
});

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
    ],
    rowConfig: {
        needsRowClickAction: true
    },
    drawConfig: {
        callback: () => {
            initTooltips($('.has-tooltip'));
            initDoubleClick('.status-planifié');
        },
        needsSearchOverride: true,
    },
    order: [[1, "desc"]],
};
let tableImport = initDataTable('tableImport', tableImportConfig);

let $modalNewImport = $("#modalNewImport");
let $submitNewImport = $("#submitNewImport");

function displayFirstModal(importId = null) {
    let $inputImportId = $modalNewImport.find('[name="importId"]');

    clearModal($modalNewImport);
    $inputImportId.val('');
    $submitNewImport.off();
    let urlNewImportFirst = Routing.generate('import_new', true);
    initModalWithAttachments($modalNewImport, $submitNewImport, urlNewImportFirst, tableImport, displaySecondModal, false);

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
        InitialiserModal($modalNewImport, $submitNewImport, urlNewImportSecond, null, (data) => displayConfirmationModal(importId, data), false);
    } else {
        $modalNewImport.find('.error-msg').html(data.msg);
    }
}

function displayConfirmationModal(importId, data) {
    $modalNewImport.find('.modal-body').html(data.html);
    $submitNewImport.off();

    $submitNewImport.click(() => {
        launchImport(importId);
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
        $.post(Routing.generate('import_launch'), params, (resp) => {
            if (!force) {
                $modalNewImport.modal('hide');
            }

            if (resp.success) {
                alertSuccessMsg(resp.message);
            } else {
                alertErrorMsg(resp.message);
            }

            tableImport.ajax.reload();
        });
    } else {
        alertErrorMsg('Une erreur est survenue lors du lancement de votre import. Veuillez recharger la page et réessayer.');
    }
}

function importTemplateChanged($dataTypeImport = null) {
    const $linkToTemplate = $('#linkToTemplate');

    $linkToTemplate.empty();

    const configDownloadLink = {
        ART: {label: 'articles', importTemplateType: 'articles'},
        REF: {label: 'références', importTemplateType: 'references'},
        FOU: {label: 'fournisseurs', importTemplateType: 'fournisseurs'},
        ART_FOU: {label: 'articles fournisseurs', importTemplateType: 'articles-fournisseurs'}
    };

    const valTypeImport = $dataTypeImport ? $dataTypeImport.val() : '';
    if (configDownloadLink[valTypeImport]) {
        url = Routing.generate('import_template', {type : configDownloadLink[valTypeImport].importTemplateType});
        $linkToTemplate
            .append(`<div class="col-12">Un fichier de modèle d\'import est disponible pour les ${configDownloadLink[valTypeImport].label}.</div>`)
            .append(`<div class="col-12"><a class="btn btn-primary" href="${url}">Télécharger</a></div>`);
    }
    else if (valTypeImport === '') {
        $linkToTemplate.append('<div class="col-12">Des fichiers de modèles d\'import sont disponibles. Veuillez sélectionner un type de données à importer.</div>');
    }
    else {
        $linkToTemplate.append('<div class="col-12">Aucun modèle d\'import n\'est disponible pour ce type de données.</div>');
    }
}
