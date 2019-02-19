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

function addArticle(button){
    let modal = button.closest('.modal');
    let code = modal.find('#code');
    let quantity = modal.find('#quantity');
    let collecteId = modal.data('collecte-id');

    let params = {
        code: code,
        quantity: quantity,
        collecteId: collecteId
    }

    //TODO validation des données

    $.post("/collecte/ajouter-article", params, function() {
        modal.modal('hide');
    });
}

function displayQuantity(input) {
    let quantity = input.find(':selected').data('quantity');
    input.closest('form').find('#quantity').val(quantity);
}