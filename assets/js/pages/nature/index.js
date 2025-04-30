import Routing from '@app/fos-routing';
import {initDataTable} from "@app/datatable";
import {POST} from "@app/ajax";
import Form from "@app/form";
import Modal from "@app/modal";
import {addEntryTimeInterval, initializeEntryTimeIntervals, onSegmentInputChange, deleteEntryTimeInterval} from "@app/pages/segment";


global.onNewModalShow = onNewModalShow;
global.toggleEntitiesContainer = toggleEntitiesContainer;
global.toggleTypes = toggleTypes;
global.selectAllTypes = selectAllTypes;
global.addEntryTimeInterval = addEntryTimeInterval;
global.onSegmentInputChange  = onSegmentInputChange;
global.deleteEntryTimeInterval  = deleteEntryTimeInterval;
global.initializeEntryTimeIntervals  = initializeEntryTimeIntervals;

$(function () {
    const table = initNatureTable();

    let $modalNewNature = $(`#modalNewNature`);
    Form.create($modalNewNature, {resetView: ['open', 'close']})
        .submitTo(POST, 'nature_new', {
            tables: [table],
            success: () => {
                $modalNewNature.find(`input[name=displayedOnForms]`).trigger(`change`);
            }
        });

    let $modalEditNature = $(`#modalEditNature`);
    Form.create($modalEditNature, {resetView: ['open', 'close']})
        .onOpen((event) => {
            Modal.load('nature_api_edit', {id: $(event.relatedTarget).data('id')}, $modalEditNature, $modalEditNature.find('.modal-body'), {
                onOpen: () => {
                    initializeEntryTimeIntervals($modalEditNature, true);
                }
            })
        })
        .submitTo(POST, 'nature_edit', {
            tables: [table],
        });

    let $modalDeleteNature = $("#modalDeleteNature");
    let $submitDeleteNature = $("#submitDeleteNature");
    let urlDeleteNature = Routing.generate(`nature_delete`, true)
    InitModal($modalDeleteNature, $submitDeleteNature, urlDeleteNature, {tables: [table]});
});

function initNatureTable() {
    let pathNature = Routing.generate(`nature_api`, true);
    let tableNatureConfig = {
        serverSide: true,
        processing: true,
        ajax: {
            url: pathNature,
            type: `POST`
        },
        order: [[`label`, `asc`]],
        columns: [
            {data: `actions`, title: ``, className: `noVis`, orderable: false},
            {data: `label`, title: `Libellé`},
            {data: `code`, title: `Code`},
            {data: `defaultQuantity`, title: `Quantité par défaut de l'arrivage`},
            {data: `quantityDefaultForDispatch`, title: `Quantité par défaut de l'acheminement`},
            {data: `prefix`, title: `Préfixe`},
            {data: `color`, title: `Couleur`},
            {data: `description`, title: `Description`},
            {data: `needsMobileSync`, title: `Synchronisation nomade`},
            {data: `displayedOnForms`, title: `Affichage sur les formulaires`},
            {data: `temperatures`, title: `Températures`, orderable: false},
        ],
        rowConfig: {
            needsRowClickAction: true
        },
    };
    return initDataTable(`tableNatures`, tableNatureConfig);
}

function onNewModalShow() {
    const $modal = $(`#modalNewNature`);
    const $entitiesContainer = $modal.find(`.entities-container`);

    $entitiesContainer.addClass(`d-none`);
    $entitiesContainer.find(`select`).prop(`disabled`, true);

    initializeEntryTimeIntervals($modal, true);
}

function toggleEntitiesContainer($input) {
    const $entitiesContainer = $input.closest(`.form-group`).siblings(`.entities-container`).first();
    $entitiesContainer.toggleClass(`d-none`, !$input.is(`:checked`));
    const $checkboxes = $entitiesContainer.find(`input[type=checkbox]`);
    $checkboxes.prop(`checked`, false);
    $checkboxes.each((index, checkbox) => toggleTypes($(checkbox)));
}

function toggleTypes($checkbox) {
    const $entityItem = $checkbox.parents(`.entity-item`);
    const $typeSelect = $entityItem.find(`select`);

    $typeSelect
        .val(null)
        .prop(`disabled`, !$checkbox.is(`:checked`))
        .trigger(`change`);
    $typeSelect.toggleClass(`needed`, $checkbox.is(`:checked`));
    $entityItem.find(`.select-all-types`).prop(`disabled`, !$checkbox.is(`:checked`));
}

function selectAllTypes($button) {
    const $select = $button.parent().siblings(`.types-container`).find(`select:not(:disabled)`)

    $select.find(`option`).each(function () {
        $(this).prop(`selected`, true);
    });

    $select.trigger(`change`);
}
