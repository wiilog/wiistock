import '@styles/details-page.scss';
import '@styles/pages/pack/timeline.scss';
import {initEditPackModal, deletePack, getTrackingHistory, reloadLogisticUnitTrackingDelay, addToCart, initializeGroupContentTable, initUngroupModal} from "@app/pages/pack/common";
import Routing from "@app/fos-routing";


$(function() {
    const logisticUnitId = $(`[name="logisticUnitId"]`).val();
    initTrackingDelayRecordSection(logisticUnitId);
    getTrackingHistory(logisticUnitId, true);
    initializeGroupContentTable(logisticUnitId, true);

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
        reloadLogisticUnitTrackingDelay($(this).data('id'));
    });

    $('.add-cart-btn').on('click', function () {
        addToCart([logisticUnitId]);
    });

    registerCopyToClipboard(`Le numéro a bien été copié dans le presse-papiers.`);
});

/**
 * @param {int} logisticUnitId
 */
function reloadTrackingDelayHistoryTable(logisticUnitId) {
    const $table = $('#trackingDelayHistoryTable');

    if ($table.exists()) {
        const $container = $table.closest('.wii-box');
        const $trackingDelayFilter = $container.find('[name="trackingDelayFilter"]');
        const trackingDelayFilter = $trackingDelayFilter.val();

        if (!trackingDelayFilter) {
            return;
        }

        const tableUrl = Routing.generate('pack_tracking_delay_history_api', {
            trackingDelay: trackingDelayFilter,
            pack: logisticUnitId
        }, true);

        if ($.fn.DataTable.isDataTable($table)) {
            const datatable = $table.DataTable();
            datatable.ajax.url(tableUrl);
            datatable.ajax.reload();
        }
        else {
            initDataTable($table, {
                serverSide: true,
                processing: true,
                order: [['date', "desc"]],
                ajax: {
                    "url": tableUrl,
                    "type": "POST"
                },
                columns: [
                    {data: 'date', title: 'Date', orderable: false},
                    {data: 'type', title: 'Type', orderable: false},
                    {data: 'event', title: 'Évènement', orderable: false},
                    {data: 'location', title: 'Emplacement', orderable: false},
                    {data: 'newNature', title: 'Nouvelle nature', orderable: false},
                    {data: 'remainingDelay', title: 'Délai restant', orderable: false},
                ],
                domConfig: {
                    removeTableHeader: true,
                }
            });
        }
    }
}

function initTrackingDelayRecordSection(logisticUnitId) {
    const $trackingDelayFilter = $('[name="trackingDelayFilter"]');
    if ($trackingDelayFilter.exists()) {
        $trackingDelayFilter
            .off('change.trackingDelayRecordSection')
            .on('change.trackingDelayRecordSection', () => {
                reloadTrackingDelayHistoryTable(logisticUnitId);
            })
            .trigger('change.trackingDelayRecordSection');
    }
}
