import {POST} from "../../ajax";

const $modalUpdateProductionRequestStatus = $('#modalUpdateProductionRequestStatus');

global.openModalUpdateProductionRequestStatus = openModalUpdateProductionRequestStatus;
$(function() {

});

function openModalUpdateProductionRequestStatus($container, prodId){
    Form.create($modalUpdateProductionRequestStatus, {clearOnOpen: true})
        .onOpen(() => {
            Modal.load('production_request_update_status_content',
                {
                    id: $container.closest('a').data('production-request-id') || ''
                },
                $modalUpdateProductionRequestStatus,
                $modalUpdateProductionRequestStatus.find('.modal-body')
            );
        })
        .submitTo(POST, 'production_request_update_status', {
            success: () => {
                console.log('Refresh le planning');
            }
        });

    $modalUpdateProductionRequestStatus.modal('show');
}
