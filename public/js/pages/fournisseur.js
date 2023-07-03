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
            {data: 'name', title: 'Nom'},
            {data: 'code', title: 'Code fournisseur'},
            {data: 'possibleCustoms', title: 'Possible douane'},
            {data: 'urgent', title: 'Urgent'},
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
