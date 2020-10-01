$('.select2').select2();

const urlApiChampLibre = Routing.generate('champ_libre_api', {'id': $('#cl-type-id').val()}, true);
let tableChampLibreConfig = {
    order: [1, 'asc'],
    ajax: {
        "url": urlApiChampLibre,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
        {"data": 'Label', 'title': 'Libellé'},
        {"data": "S'applique à", 'title': "S'applique à"},
        {"data": 'Typage', 'title': 'Typage'},
        {"data": 'Valeur par défaut', 'title': 'Valeur par défaut'},
        {"data": 'Elements', 'title': 'Éléments', className: 'noVis'},
        {"data": 'Affiché à la création', 'title': 'Affiché à la création'},
        {"data": 'Obligatoire à la création', 'title': 'Obligatoire à la création'},
        {"data": 'Obligatoire à la modification', 'title': 'Obligatoire à la modification'},
    ],
    rowConfig: {
        needsRowClickAction: true,
    },
};
let tableChampLibre = initDataTable('tableChamplibre_id', tableChampLibreConfig);

let $modalNewChampLibre = $("#modalNewChampLibre");
let $submitChampLibreNew = $("#submitChampLibreNew");
let urlChampLibreNew = Routing.generate('champ_libre_new', true);
InitModal($modalNewChampLibre, $submitChampLibreNew, urlChampLibreNew, {tables: [tableChampLibre]});

let $modalDeleteChampLibre = $("#modalDeleteChampLibre");
let $submitChampLibreDelete = $("#submitChampLibreDelete");
let urlChampLibreDelete = Routing.generate('champ_libre_delete', true);
InitModal($modalDeleteChampLibre, $submitChampLibreDelete, urlChampLibreDelete, {tables: [tableChampLibre]});

let $modalEditChampLibre = $("#modalEditChampLibre");
let $submitEditChampLibre = $("#submitEditChampLibre");
let urlEditChampLibre = Routing.generate('champ_libre_edit', true);
InitModal($modalEditChampLibre, $submitEditChampLibre, urlEditChampLibre, {tables: [tableChampLibre]});

function defaultValueForTypage($select) {
    const $modal = $select.closest('.modal');
    let valueDefault = $modal.find('.valueDefault');
    let typage = $select.val();
    let inputDefaultBlock;
    let name = 'valeur';
    let label = "Valeur par défaut&nbsp;";
    let typeInput = typage;

    // cas modification
    let existingValue = $select.data('value');
    let existingElem = $select.data('elem');

    if (typage === 'booleen') {
        let checked = existingValue === 1 ? "checked" : "";
        inputDefaultBlock =
            `<label class="switch">
                <input type="checkbox" class="data checkbox"
                name="valeur" value="` + existingValue + `" ` + checked + `>
                <span class="slider round"></span>
            </label>`;
    } else {
        if (typage === 'list' || typage === 'list multiple') {
            label = "Éléments (séparés par ';')";
            name = 'elem';
            existingValue = existingElem ? existingElem : '';
        } else if (typage === 'datetime') {
            typeInput = 'datetime-local';
        } else if (typage === 'date') {
            typeInput = 'date';
        }

        inputDefaultBlock =
            `<input type="` + typeInput + `" class="form-control cursor-default data ` + typeInput + `" name="` + name + `" value="` + (existingValue ? existingValue : '') + `">`
    }



    let defaultBlock =
        `<div class="form-group">
           ` + inputDefaultBlock + ` <label>` + label + `</label>` +
        `</div>`;

    valueDefault.html(defaultBlock);
}

function toggleCreationMandatory($switch) {
    if(!$switch.is(':checked')) {
        $('input[name="requiredCreate"]').prop('checked', 0);
    }
}
