import {initDataTable} from "@app/datatable";

$(function() {
    const table = initDataTable('tableSleepingStockForm', {
        serverSide: false,
        processing: false,
        searching: false,
        paging: false,
        order: [['maxStorageDate', "desc"]],
        columns: [
            {data: 'actions', name: 'actions', title: 'Actions', orderable: false,  className: 'full-width'},
            {data: 'label', name: 'label', title: Translation.of('Stock', 'Références', 'Général', 'Libellé')},
            {data: 'reference', name: 'reference', title: Translation.of('Stock', 'Références', 'Général', 'Référence')},
            {data: 'quantityStock', name: 'quantityStock', title: Translation.of('Stock', 'Références', 'Général', 'Quantité')},
            {data: 'maxStorageDate', name: 'maxStorageDate', title: Translation.of('Stock', 'Références', 'Email stock dormant', 'Date max de stockage')},
        ],
    });

    $(document).on('click', '.delete-row', function (event) {
        table.row($(event.target).closest('tr')).remove().draw();
    });
});
