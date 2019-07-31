function prefixDemand(){
    let prefixe = $('#prefixeDemande').val();
    let typeDemande = $('#typeDemandePrefixageDemande').val();

    let path = Routing.generate('ajax_prefixe_demande',true);
    let params = JSON.stringify({prefixe: prefixe, typeDemande: typeDemande});

    $.post(path, params, function(data){
        // $('.justify-content-center').find('#prefixeForm').html(data);
    });
}