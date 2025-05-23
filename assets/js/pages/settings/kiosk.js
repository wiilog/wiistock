import AJAX, {GET, POST, DELETE} from "@app/ajax";
import Flash, {ERROR} from "@app/flash";
import Routing from '@app/fos-routing';
import Form from '@app/form';
import Modal from "@app/modal";

let kioskTable;

$(function () {
    // Initialize the table
    kioskTable = initKiosksTable();
    const $editKioskModal = $('#editKioskModal');

    // listenners
    $(document)
        .on('click', '.redirect-to-kiosk', function () {
            redirectToKiosk($(this).data('id'));
        })
        .on('click', '.delete-kiosk', function () {
            deleteKiosk($(this).data('id'));
        })
        .on('click', '.unlink-kiosk', function () {
            unlinkKiosk($(this).data('id'), $(this));
        });

    // Initialize modals
    Form
        .create($('#newKioskModal'), {resetView: ['open', 'close']})
        .submitTo(POST,
            'kiosk_create',
            {tables: [kioskTable]})

    Form
        .create($editKioskModal)
        .onOpen(function (event) {
            const kioskId = $(event.relatedTarget).data('id');
            Modal.load('kiosk_edit_api', {id: kioskId}, $editKioskModal)
        })
        .submitTo(POST,
            'kiosk_edit',
            {
                tables: [kioskTable],
            })
});

export function initializeCollectRequestAndCreateRef($container) {
    Select2Old.init($container.find('select[name=referenceType]'));
    Select2Old.init($container.find('select[name=collectType]'));
    Select2Old.init($container.find('select[name=location]'));
    Select2Old.init($container.find('select[name=freeField]'));
    Select2Old.init($container.find('select[name=visibilityGroup]'));
    Select2Old.init($container.find('select[name=inventoryCategories]'));
    Select2Old.init($container.find('select[name=fournisseurLabel]'));
    Select2Old.init($container.find('select[name=fournisseur]'));

    if ($('#settingReferenceType').val()) {
        displayFreeFields($('#settingReferenceType').val());
    }

    $('select[name=TYPE_REFERENCE_CREATE]').on('change', function () {
        if ($(this).val()) {
            displayFreeFields($(this).val());
        }
    })
}

function displayFreeFields(typeId) {
    let $freeFieldSelect = $('select[name=FREE_FIELD_REFERENCE_CREATE]');
    $freeFieldSelect.empty();
    $.post(Routing.generate('free_fields_by_type', {type: typeId}), {}, function (data) {
        let freeFields = data.freeFields;
        freeFields.forEach(function (element) {
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
            type: POST
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

function unlinkKiosk(id, $this) {
    if (!id) {
        Flash.add(ERROR, 'Cette borne est déjà déconnectée.');
        return;
    }
    return AJAX.route(POST, `kiosk_unlink_token`, {kiosk: id}).json().then(() => {
        $this.closest('.dropdown-item').remove();
    })
}

function redirectToKiosk(id) {
    return AJAX.route(GET, `kiosk_token_generate`, {kiosk: id})
        .json()
        .then(({token}) => window.location.href = Routing.generate(`kiosk_index`, {token}, true));
}

function deleteKiosk(id) {
    Modal.confirm({
        ajax: {
            method: DELETE,
            route: 'kiosk_delete',
            params: {'kiosk': id},
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
