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

let modalNewService = $("#modalNewService");
let submitNewService = $("#submitNewService");
let urlNewService = Routing.generate('creation_service', true);
InitialiserModal(modalNewService, submitNewService, urlNewService, tableService);

let modalModifyService = $('#modalEditService');
let submitModifyService = $('#submitEditService');
let urlModifyService = Routing.generate('service_edit', true);
InitialiserModal(modalModifyService, submitModifyService, urlModifyService, tableService);

$.fn.dataTable.ext.search.push(
    function( settings, data, dataIndex ) {
        console.log('1étape');
        var min =  $('#dateMin').val();
        console.log(min);
        var max =  $('#dateMax').val();
        console.log(max);
        var date = data[0]  || 0; // use data for the date column
        console.log(date);
        if ( ( isNaN( min ) && isNaN( max ) ) ||
             ( isNaN( min ) && date <= max ) ||
             ( min <= date   && isNaN( max ) ) ||
             ( min <= date   && date <= max ) )
        {
            return true;
        }
        return false;
    }
);
 
$(document).ready(function() {
    var table = $('#tableService_id').DataTable();
   
     
    // Event listener to the two range filtering inputs to redraw on input
    $('#dateMin, #dateMax').keyup( function() {
        console.log('2étape');
        table.draw();
    } );
} );