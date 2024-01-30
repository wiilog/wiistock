const $modalUpdateProductionRequestStatus = $('#modalUpdateProductionRequestStatus');

$(function() {

});

function openModalUpdateProductionRequestStatus($container){
    Form.create($modalUpdateProductionRequestStatus, {clearOnOpen: true})
        .onOpen(() => {
            Modal.load('',//TODO faire la route permettant de récupérer le contenu à afficher dans la modale
                {
                    id: $container.closest('a').data('production-request-id') || ''
                },
                $modalUpdateProductionRequestStatus,
                $modalUpdateProductionRequestStatus.find('.modal-body')
            );
        })
        .submitTo();//TODO faire la route d'enregistrement de la modale
}
