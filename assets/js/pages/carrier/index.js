import Modal from "@app/modal";

import Form from '@app/form';
import AJAX, {GET, POST} from "@app/ajax";
import Flash from "@app/flash";

let tableTransporteur = null;
let $modalCarrier = $("#modalTransporteur");

global.displayCarrierModal = displayCarrierModal;
global.deleteCarrier = deleteCarrier;

$(document).ready(() => {
    createForm();
    initTransporteurTable();
});

function createForm() {
    Form.create($modalCarrier)
        .on('change', '[name=is-recurrent]', () => {
            let $recurrentSpan = $modalCarrier.find(".logo-container").find('label').find('.field-label');
            let $recurrentCheckbox = $modalCarrier.find("[name=is-recurrent]");
            const oldLabel = ($recurrentSpan.text() || '').trim();

            if ($recurrentCheckbox.is(':checked')) {
                $recurrentSpan.text(`${oldLabel}*`);
            } else {
                $recurrentSpan.text(oldLabel.slice(0, -1));
            }
        })
        .addProcessor((data, errors, $form) => {
            const logo = data.get('logo');

            if (!logo && $form.find('[name=is-recurrent]').is(':checked')) {
                errors.push({
                    message: `Vous devez ajouter un logo.`,
                    global: true,
                });
            }
        })
        .addProcessor((data, errors, $form) => {
            const minCharNumber = data.get('min-char-number');
            const maxCharNumber = data.get('max-char-number');

            if (minCharNumber && maxCharNumber && Number(minCharNumber) > Number(maxCharNumber)) {
                errors.push({
                    elements: [$form.find('input[name=min-char-number], input[name=max-char-number]')],
                    message: `Le nombre de caractères minimum ne peut pas être supérieur au nombre de caractères maximum.`,
                    global: true,
                });
            }
        })
        .onSubmit((data, form) => {
            form.loading(() => {
                const carrierId = $modalCarrier.find('[name=carrierId]').val();
                const params = carrierId ? {carrier: carrierId} : {};

                return AJAX.route(POST, 'transporteur_save', params)
                    .json(data)
                    .then(({success}) => {
                        if (success) {
                            $modalCarrier.modal(`hide`);
                            tableTransporteur.ajax.reload();
                        }
                    })
            });
        });
    //onChangeRecurrent();
}

function initTransporteurTable() {
    let pathTransporteur = Routing.generate('transporteur_api', true);
    let tableTransporteurConfig = {
        order: [['label', 'desc']],
        ajax: {
            "url": pathTransporteur,
            "type": "POST"
        },
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis', orderable: false},
            {data: 'label', name: 'label', title: 'Nom'},
            {data: 'code', name: 'code', title: 'Code'},
            {data: 'driversNumber', name: 'driversNumber', title: 'Nombre de chauffeurs'},
            {data: 'charNumbers', name: 'charNumbers', title: 'Nombre de caractères du n° de tracking', orderable: false},
            {data: 'isRecurrent', name: 'isRecurrent', title: 'Transporteur récurrent (max. 10)'},
            {data: 'logo', name: 'logo', title: 'Logo', orderable: false},
        ],
        rowConfig: {
            needsRowClickAction: true
        }
    };
    tableTransporteur = initDataTable('tableTransporteur_id', tableTransporteurConfig);
}

function displayCarrierModal(carrierId) {
    $modalCarrier.modal(`show`);
    const params = carrierId
        ? {carrier: carrierId}
        : {};
    const title = carrierId
        ? "Modifier un transporteur"
        : "Ajouter un transporteur";

    $modalCarrier.find('.modal-title')
        .text(title);

    $modalCarrier.find('.modal-body')
        .html(`
            <div class="row justify-content-center">
                <div class="col-auto">
                    <div class="spinner-border">
                        <span class="sr-only">Chargement...</span>
                    </div>
                </div>
            </div>
        `);

    $.get(Routing.generate('transporteur_template', params), function(resp){
        $modalCarrier.find('.modal-body').html(resp);
        //onChangeRecurrent();
    });

    $modalCarrier.modal('show');
}

function deleteCarrier(carrierId) {
    Modal.confirm({
        ajax: {
            method: POST,
            route: 'transporteur_delete',
            params: {
                carrier: carrierId
            },
        },
        message: 'Voulez-vous réellement supprimer ce transporteur ?',
        title: 'Supprimer le transporteur',
        validateButton: {
            color: 'danger',
            label: 'Supprimer',
        },
        cancelButton: {
            label: 'Annuler',
        },
        table: tableTransporteur,
    });
}

function onChangeRecurrent() {

}
