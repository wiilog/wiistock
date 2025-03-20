import {initDataTable} from "@app/datatable";
import {POST} from "@app/ajax";

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

    Form
        .create('.sleeping-stock-form')
        .addProcessor((data, errors, $form) => {
            let actions = [];
            $form.find('tr').each(function (index, row) {
                const $row = $(row);
                const id = $row.find('[name="id"]').val();
                if(id) {
                    actions.push({
                        id: id,
                        entity: $row.find('[name="entity"]').val(),
                        templateId: $row.find(`[name="template-${id}"]`).val(),
                    });
                }
            })
            data.append('actions', JSON.stringify(actions));
        })
        .submitTo(
            POST,
            'sleeping_stock_submit',
            {
                success: (data) => {

                    // wait 500ms before reloading
                    setTimeout(() => {
                        window.location.reload();
                    }, 5000);
                }
            }
        )

});
