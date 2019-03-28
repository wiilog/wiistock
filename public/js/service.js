$('.select2').select2();

var pathService = Routing.generate('service_api', true);
var tableService = $('#tableService_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": pathService,
        "type": "POST"
    },
    columns: [
        { "data": 'Date' },
        { "data": 'Demandeur' },
        { "data": 'Libellé' },
        { "data": 'Statut' },
        { "data": 'Actions' },
    ],
});



$('#submitSearchService').on('click', function () {
    let statut = $('#statut').val()
    let demandeur = $('#demandeur').val()
    
   
    tableService
        .columns(3)
        .search(statut)
        .draw();

    tableService
        .columns(1)
        .search(demandeur)
        .draw();
        
});



let modalNewService = $("#modalNewService");
let submitNewService = $("#submitNewService");
let urlNewService = Routing.generate('creation_service', true);
InitialiserModal(modalNewService, submitNewService, urlNewService, tableService);

let modalModifyService = $('#modalEditService');
let submitModifyService = $('#submitEditService');
let urlModifyService = Routing.generate('service_edit', true);
InitialiserModal(modalModifyService, submitModifyService, urlModifyService, tableService);

// let min = ' ';
// $.fn.dataTable.ext.search.push(
//     function ( settings, data, dataIndex ) {
//         min = new Date($('#dateMin').val());
//         let dateMin = min.toISOString();
        
        
//         // console.log(dateMin);
        
//         let max = new Date($('#dateMax').val());
//         let dateMax = max.toISOString();
//         // console.log(dateMax);
        
//         let dateInit = new Date((data[0] ));
//         let date = dateInit.toISOString(); 
        
//         console.log(dateInit);
//         console.log(dateInit);
//         console.log(date);
//         console.log(data[0] );

//         if ( 
//             // ( isNaN( dateMin ) && isNaN( dateMax ) ) ||
             
//             // ( isNaN( dateMin ) && date <= dateMax ) ||
//             //  ( dateMin <= date   && isNaN( dateMax ) ) ||
//              ( dateMin <= date   && date <= dateMax ) )
//         {
//             return true;
//         }
//         return false;
//     }
// );


// $('#submitSearchService').on('click', function () {
//     tableService
//         .draw();
// } );



