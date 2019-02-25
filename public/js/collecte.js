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

        $.post("/collecte/creer", params, function(data) {
            $('#table-list-collects').DataTable().row.add(data).draw();
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

function deleteCollecte(button) {
    let modal = button.closest('.modal');
    let status = modal.data('status');

    if (status !== 'demande de collecte') { //TODO CG passer par constante ?
        let alerts = $('#alerts');
        alerts.addClass('alert alert-danger');
        alerts.html('La collecte ne peut pas être supprimée (statut ' + status + ').');
//TODO CG optimiser (msg d'erreur + tôt)
    } else {
        let collecteId = modal.data('collecte-id');
        $.post("/collecte/" + collecteId + "/delete", function() {
            window.location.replace('/collecte');
        });
    }
}

function deleteRow(button) {
    let articleId = button.data('id');
    let row = button.closest('tr');
    let collecteId = button.closest('table').data('collecte-id');

    let params = {
        articleId: articleId,
        collecteId: collecteId
    };

    $.post("/collecte/retirer-article", params, function(data) {
        if (data === true) {
            $('#table-list-article').DataTable().row(row).remove().draw();
        } // TODO else message erreur

    });
}

function editRow(button) {
    let quantity = button.data('quantity');
    let name = button.data('name');
    let id = button.data('id');
    let modal = $('#modalModifyArticle');
    modal.find('.quantity').val(quantity);
    modal.find('.quantity').attr('max', quantity); //TODO CG il faudrait récupérer la valeur de la quantité de l'article
    modal.find('.article').html(name);
    modal.data('id', id); //TODO CG trouver + propre
}

function modifyArticle(button) {
    let modal = button.closest('.modal');
    let quantity = modal.find('.quantity').val();
    let articleId = modal.data('id');

    let params = {
        articleId: articleId,
        quantity: quantity
    };

    $.post("/article/modifier-quantite", params, function() {
        //TODO CG edit row datatable
    });
}