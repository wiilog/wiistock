$(function () {
    initDateTimePicker('#dateMin, #dateMax');
    initSelect2($('#statut'), 'Statut');
    ajaxAutoUserInit($('.filters .ajax-autocomplete-user'), 'Utilisateurs');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_IMPORT);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');
});

let pathImport = Routing.generate('import_api');
let tableImport = $('#tableImport').DataTable({
    processing: true,
    serverSide: true,
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
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
    rowCallback: function (row, data) {
        initActionOnRow(row);
    },
    order: [[1, "desc"]],
    drawCallback: function () {
        overrideSearch($('#tableImport_filter input'), tableImport);
        initTooltips($('.has-tooltip'));
        initDoubleClick('.status-planifié');
    }
});

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
