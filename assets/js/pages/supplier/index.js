import FixedFieldEnum from "@generated/fixed-field-enum";
import Form from "@app/form";
import Modal from "@app/modal";
import {POST, DELETE} from "@app/ajax";

$(function () {
    $('.select2').select2();

    let pathSupplier = Routing.generate('supplier_api');
    let supplierTableConfig = {
        processing: true,
        serverSide: true,
        paging: true,
        order: [[FixedFieldEnum.name.name, 'desc']],
        ajax: {
            "url": pathSupplier,
            "type": POST,
        },
        columns: [
            {data: 'Actions', title: '', className: 'noVis', orderable: false},
            {data: FixedFieldEnum.name.name, title: FixedFieldEnum.name.value},
            {data: FixedFieldEnum.code.name, title: FixedFieldEnum.code.value},
            {data: FixedFieldEnum.possibleCustoms.name, title: FixedFieldEnum.possibleCustoms.value},
            {data: FixedFieldEnum.urgent.name, title: FixedFieldEnum.urgent.value},
            {data: FixedFieldEnum.address.name, title: FixedFieldEnum.address.value},
            {data: FixedFieldEnum.receiver.name, title: FixedFieldEnum.receiver.value},
            {data: FixedFieldEnum.phoneNumber.name, title: FixedFieldEnum.phoneNumber.value},
            {data: FixedFieldEnum.email.name, title: FixedFieldEnum.email.value},
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
        drawConfig: {
            needsSearchOverride: true
        }
    };
    let supplierTable = initDataTable('supplier-table', supplierTableConfig);

    let modalNewSupplier = $("#modalNewFournisseur");
    const newSupplierForm = Form
        .create(modalNewSupplier)
        .submitTo(
            POST,
            'supplier_new',
            {
                tables: supplierTable,
                success: function () {
                    newSupplierForm.clear();
                }
            }
        )

    $(document).on('click', '.delete-supplier', function (event) {

        const supplier = $(event.target).data('id') || $(event.target).parent('.delete-supplier').data('id');
        Modal.confirm({
            ajax: {
                method: DELETE,
                route: 'supplier_delete',
                params: { supplier },
            },
            message: 'Voulez-vous réellement supprimer ce fournisseur ?',
            title: 'Supprimer le fournisseur',
            validateButton: {
                color: 'danger',
                label: 'Supprimer'
            },
            table: supplierTable,
        })

    })

    let modalEditSupplier = $('#modalEditFournisseur');
    Form
        .create(modalEditSupplier)
        .onOpen(function (event) {
            const supplier = $(event.relatedTarget).data('id');
            Modal.load('supplier_api_edit', {supplier}, modalEditSupplier)
        })
        .submitTo(
            POST,
            'supplier_edit',
            {
                tables: supplierTable,
            }
        )
});
