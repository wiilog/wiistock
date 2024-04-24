import AJAX, {GET, POST, DELETE} from "@app/ajax";
import Flash, {ERROR} from "@app/flash";
import Routing from '@app/fos-routing';
import Form from '@app/form';
import Modal from "@app/modal";

global.redirectToKiosk = redirectToKiosk;
global.deleteKiosk = deleteKiosk;
global.unlinkKiosk = unlinkKiosk;

let kioskTable;

$(function () {
    // Initialize the table
    kioskTable = initKiosksTable();

    // Initialize the modal
    Form.create($('#newKioskModal'), {clearOnOpen : true} ).submitTo(POST, 'create_kiosk', {tables: [kioskTable]})
    Form.create($('#editKioskModal')).submitTo(POST, 'edit_kiosk', {tables: [kioskTable]})
});

export function initializeCollectRequestAndCreateRef($container){
    Select2Old.init($container.find('select[name=referenceType]'));
    Select2Old.init($container.find('select[name=collectType]'));
    Select2Old.init($container.find('select[name=location]'));
    Select2Old.init($container.find('select[name=freeField]'));
    Select2Old.init($container.find('select[name=visibilityGroup]'));
    Select2Old.init($container.find('select[name=inventoryCategories]'));
    Select2Old.init($container.find('select[name=fournisseurLabel]'));
    Select2Old.init($container.find('select[name=fournisseur]'));

    if($('#settingReferenceType').val()){
        displayFreeFields($('#settingReferenceType').val());
    }

    $('select[name=TYPE_REFERENCE_CREATE]').on('change', function (){
        if($(this).val()){
            displayFreeFields($(this).val());
        }
    })
}

function displayFreeFields(typeId){
    let $freeFieldSelect = $('select[name=FREE_FIELD_REFERENCE_CREATE]');
    $freeFieldSelect.empty();
    $.post(Routing.generate('free_fields_by_type', {type: typeId}), {}, function (data) {
        let freeFields = data.freeFields;
        freeFields.forEach(function(element){
            $freeFieldSelect.append(element);
        });
    }, 'json');
}

function initKiosksTable() {
    return initDataTable(`tablekiosks`, {
        processing: true,
        serverSide: false,
        paging: true,
        order: [[`name`, `desc`]],
        ajax: {
            url: Routing.generate(`kiosk_api`, true),
            type: `POST`
        },
        columns: [
            {data: `actions`, title: ``, className: `noVis`, orderable: false},
            {data: `pickingType`, title: `Type de collecte`},
            {data: `name`, title: `Nom borne`},
            {data: `pickingLocation`, title: `Point de collecte`},
            {data: `requester`, title: `Demandeur`},
            {data: `externalLink`, title: `Lien externe généré`, orderable: false},
        ],
        rowConfig: {
            needsRowClickAction: false,
        },
        drawConfig: {
            needsSearchOverride: true
        }
    });
}

function unlinkKiosk(id, $this){
    if(!id) {
        Flash.add(ERROR, 'Cette borne est déjà déliée.');
        return;
    }
    return AJAX.route(POST, `remove_token`, {kiosk: id}).json().then(() => {
        $this.closest('.dropdown-item').remove();
    })
}

function redirectToKiosk(id){
    return AJAX.route(GET, `generate_kiosk_token`, {kiosk:id})
        .json()
        .then(({token}) => window.location.href = Routing.generate(`kiosk_index`, {token}, true));
}

function deleteKiosk(id){
    Modal.confirm({
        ajax: {
            method: DELETE,
            route: 'delete_kiosk',
            params: { 'kiosk' : id },
        },
        message: 'Voulez-vous réellement supprimer cette borne',
        title: 'Suppression de borne',
        validateButton: {
            color: 'danger',
            label: 'Supprimer'
        },
        table: kioskTable,
    })
}
