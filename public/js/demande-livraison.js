function editRow(button) {
    let quantity = button.data('quantity');
    let name = button.data('name');
    let id = button.data('id');
    let modal = $('#modalModifyLigneArticle');
    let submit = modal.find('#modifySubmit');
    let path = Routing.generate('modifyLigneArticle', {id: id} , true);
    modal.find('.quantity').val(quantity);
    modal.find('.quantity').attr('max', quantity); //TODO CG il faudrait récupérer la valeur de la quantité de l'article
    modal.find('.ligne-article').html(name);
    modal.data('id', id); //TODO CG trouver + propre

    InitialiserModal(modal, submit, path, table);
}
