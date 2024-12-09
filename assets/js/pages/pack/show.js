import '@styles/details-page.scss';
import '@styles/pages/pack/timeline.scss';
import {initEditPackModal, deletePack, getTrackingHistory, reloadLogisticUnitTrackingDelay, addToCart, initUngroupModal} from "@app/pages/pack/common";


$(function() {
    const logisticUnitId = $(`[name="logisticUnitId"]`).val();
    getTrackingHistory(logisticUnitId, true);
    initEditPackModal({
        success: () => {
            window.location.reload();
        }
    });

    initUngroupModal({
        success: () => {
            window.location.reload();
        }
    });

    $('.delete-pack').on('click', function () {
        deletePack(
            {"pack": logisticUnitId},
            undefined,
            function () {
                // redirect to pack index after deletion
                // the timeout is used to force the redirection after the modal is closed. Otherwise, the modal will be closed and nothing will happen
                setTimeout(function(){
                    document.location.href = Routing.generate('pack_index');
                },100);
            }
        );
    });

    $('.reload-tracking-delay').on('click', function () {
        reloadLogisticUnitTrackingDelay($(this).data('id'), function () {
            window.location.reload();
        });
    });

    $('.add-cart-btn').on('click', function () {
        addToCart([logisticUnitId]);
    });

    registerCopyToClipboard(`Le numéro a bien été copié dans le presse-papiers.`);
});
