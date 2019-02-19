function saveCollecte(button) {
    let modal = button.closest('.modal');
    let demandeur = modal.find('.demandeur').val();
    let objet = modal.find('.objet').val();
    let pointCollecte = modal.find('.pointCollecte').val();

    // validation des données
    let formIsValid = true;

    // vide les messages d'erreurs
    modal.find('.error-msg').text('');

    if (typeof(pointCollecte) === "undefined" || pointCollecte === null) {
        modal.find('.error-msg-pointCollecte').text('Veuillez sélectionner un point de collecte.');
        formIsValid = false;
    }
    if (typeof(objet) === "undefined" || objet === '') {
        modal.find('.error-msg-objet').text('Veuillez renseigner un objet.');
        formIsValid = false;
    }
    if (formIsValid) {
        let params = {
            demandeur: demandeur,
            objet: objet,
            pointCollecte: pointCollecte
        };

        $.post("/collecte/creer", params, function() {
            modal.modal('hide');
        }, 'json');
    }
}

function addRow(button){
    let modal = button.closest('.modal');
    let articleId = modal.find('#code').val();
    let quantity = modal.find('#quantity').val();
    let collecteId = modal.data('collecte-id');

    //TODO validation des données

    let params = {
        articleId: articleId,
        quantity: quantity,
        collecteId: collecteId
    }

    $.post("/collecte/ajouter-article", params, function(data) {
        $('#table-list-articles').DataTable().row.add(data).draw();
    });
}

function displayQuantity(input) {
    let quantity = input.find(':selected').data('quantity');
    let inputQuantity = input.closest('form').find('#quantity');
    inputQuantity.val(quantity);
    inputQuantity.attr('max', quantity);
}