import FixedFieldEnum from "@generated/fixed-field-enum";

$(function () {
    $('.select2').select2();

    let pathSupplier = Routing.generate('supplier_api');
    let supplierTableConfig = {
        processing: true,
        serverSide: true,
        paging: true,
        order: [['name', 'desc']],
        ajax: {
            "url": pathSupplier,
            "type": "POST"
        },
        columns: [
            {data: 'Actions', title: '', className: 'noVis', orderable: false},
            {data: FixedFieldEnum.name.name, title: FixedFieldEnum.name.value},
            {data: 'code', title: 'Code fournisseur'},
            {data: 'possibleCustoms', title: 'Possible douane'},
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
    let supplierTable = initDataTable('supplierTable_id', supplierTableConfig);

    let modalNewSupplier = $("#modalNewFournisseur");
    let submitNewSupplier = $("#submitNewFournisseur");
    let urlNewSupplier = Routing.generate('supplier_new', true);
    InitModal(modalNewSupplier, submitNewSupplier, urlNewSupplier, {tables: [supplierTable], formData: true});

    let modalDeleteSupplier = $("#modalDeleteFournisseur");
    let submitDeleteSupplier = $("#submitDeleteFournisseur");
    let urlDeleteSupplier = Routing.generate('supplier_delete', true)
    InitModal(modalDeleteSupplier, submitDeleteSupplier, urlDeleteSupplier, {tables: [supplierTable]});

    let modalEditSupplier = $('#modalEditFournisseur');
    let submitEditSupplier = $('#submitEditFournisseur');
    let urlEditSupplier = Routing.generate('supplier_edit', true);
    InitModal(modalEditSupplier, submitEditSupplier, urlEditSupplier, {tables: [supplierTable]});
});
