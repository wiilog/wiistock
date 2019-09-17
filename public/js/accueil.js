function goToFilteredDemande(type, filter){
    let path = '';

    if (type === 'livraison'){
        path = 'demande_index';
    } else if (type === 'collecte') {
        path = 'collecte_index';
    } else if (type === 'manutention'){
        path = 'manutention_index';
    }

    let params = {
        filter: filter
    };
    let route = Routing.generate(path, params);
    window.location.href = route;
}