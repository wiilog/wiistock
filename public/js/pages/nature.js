$(function () {
    const table = initNatureTable();

    let $modalNewNature = $(`#modalNewNature`);
    let $submitNewNature = $(`#submitNewNature`);
    let urlNewNature = Routing.generate(`nature_new`, true);
    InitModal($modalNewNature, $submitNewNature, urlNewNature, {
        tables: [table],
        clearOnClose: true,
        success: () => {
            $modalNewNature.find(`input[name=displayedOnForms]`).trigger(`change`);
        }
    });

    let $modalEditNature = $(`#modalEditNature`);
    let $submitEditNature = $(`#submitEditNature`);
    let urlEditNature = Routing.generate(`nature_edit`, true);
    InitModal($modalEditNature, $submitEditNature, urlEditNature, {tables: [table]});

    let $modalDeleteNature = $("#modalDeleteNature");
    let $submitDeleteNature = $("#submitDeleteNature");
    let urlDeleteNature = Routing.generate(`nature_delete`, true)
    InitModal($modalDeleteNature, $submitDeleteNature, urlDeleteNature, {tables: [table]});
});

function initNatureTable() {
    let pathNature = Routing.generate(`nature_param_api`, true);
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
            {data: `defaultQuantity`, title: `Quantité par défaut`},
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
