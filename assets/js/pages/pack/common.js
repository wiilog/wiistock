import {POST, DELETE} from "@app/ajax";
import Form from "@app/form";
import Modal from "@app/modal";


export function initEditPackModal(options) {
    const $modalEditPack = $('#modalEditPack');
    Form
        .create($modalEditPack)
        .onOpen((event) => {
            Modal.load('pack_edit_api', {pack: $(event.relatedTarget).data('id')}, $modalEditPack, $modalEditPack.find('.modal-body'), {
                onOpen: () => {
                    initializeEntryTimeIntervals($modalEditPack, true);
                }
            })
        })
        .submitTo(
            POST,
            'pack_edit',
            options
        );
}

export function deletePack(params, table){
    Modal.confirm({
        ajax: {
            method: DELETE,
            route: 'pack_delete',
            params: params,
        },
        message: Translation.of('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', 'Voulez-vous réellement supprimer cette UL ?'),
        title:  Translation.of('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', 'Supprimer l\'UL', false),
        validateButton: {
            color: 'danger',
            label: Translation.of('Général', null, 'Modale', 'Supprimer'),
        },
        table: table,
    })
}
