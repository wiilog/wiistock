function updatePrefixDemand(){
    let prefixe = $('#prefixeDemande').val();
    let typeDemande = $('#typeDemandePrefixageDemande').val();

    let path = Routing.generate('ajax_update_prefix_demand',true);
    let params = JSON.stringify({prefixe: prefixe, typeDemande: typeDemande});

    let msg = '';
    if(typeDemande === 'aucunPrefixe'){
        $('#typeDemandePrefixageDemande').addClass('is-invalid');
        msg += 'Veuillez sélectionner un type de demande.';
    } else {
        $('#typeDemandePrefixageDemande').removeClass('is-invalid');
        $('#buttonModalPrefixageSet').click();
        $.post(path, params);
    }
    $('.error-msg').html(msg);
}

function getPrefixDemand(select) {
    let typeDemande = select.val();

    let path = Routing.generate('ajax_get_prefix_demand', true);
    let params = JSON.stringify(typeDemande);

    $.post(path, params, function(data) {
        $('#prefixeDemande').val(data);
    }, 'json');
}