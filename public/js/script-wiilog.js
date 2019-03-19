$(document).ready(function () {
    $('.select2').select2();
});


//NEW
/**
 * Initialise une fenêtre modale
 * 
 * @param {Document} modal la fenêtre modale selectionnée : document.getElementById("modal").
 * @param {Document} submit le bouton qui va envoyé les données au controller via Ajax.
 * @param {string} path le chemin pris pour envoyer les données.
 * @param {document} table le DataTable gérant les données
 * 
 */
function InitialiserModal(modal, submit, path, table) {
    submit.click(function () {
        xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                $('.errorMessage').html(JSON.parse(this.responseText))
                data = JSON.parse(this.responseText);
                table.ajax.reload(function (json) {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                    if (this.responseText !== undefined) {
                        $('#myInput').val(json.lastInput);
                    }
                    if (data.anomalie) {
                        $('#statutReception').text(data.anomalie);
                    }
                });
                let inputs = modal.find(".data"); // On récupère toutes les données qui nous intéresse
                console.log(inputs);
                inputs.each(function () {
                    $(this).val("");
                       
                });

            }
        };
        let inputs = modal.find(".data"); // On récupère toutes les données qui nous intéresse
        let Data = {}; // Tableau de données
        inputs.each(function () {
            Data[$(this).attr("name")] = $(this).val();
        });

        let checkboxes = modal.find('.checkbox');
        checkboxes.each(function () {
           Data[$(this).attr("name")] = $(this).is(':checked');
           alert($(this).is(':checked'));
        });

        Json = {};
        Json = JSON.stringify(Data); // On transforme les données en JSON
        xhttp.open("POST", path, true);
        xhttp.send(Json);
    });
}


//DELETE
function deleteRow(button, modal, submit) {
    let id = button.data('id');
    modal.find(submit).attr('value', id);
}


//SHOW
/**
 * Initialise une fenêtre modale
 * 
 * @param {Document} modal la fenêtre modale selectionnée : document.getElementById("modal").
 * @param {Document} button le bouton qui va envoyé les données au controller via Ajax.
 * @param {string} path le chemin pris pour envoyer les données.
 * 
 */
function showRow(modal, button, path) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            dataReponse = JSON.parse(this.responseText);
            modal.find('.modal-body').html(dataReponse);
        }
    }
    let json = button.data('id');
    xhttp.open("POST", path, true);
    xhttp.send(json);
}


//MODIFY
/**
 * La fonction modifie les valeurs d'une modale modifier avec les valeurs data-attibute.
 * Ces valeurs peuvent être trouvées dans datatableLigneArticleRow.html.twig
 * 
 * @param {Document} button
 * @param {string} path le chemin pris pour envoyer les données.
 * @param {Document} modal la modalde modification
 * @param {Document} submit le bouton de validation du form pour le edit
 *  
 */
function editRow(button, path, modal, submit) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            dataReponse = JSON.parse(this.responseText);
            modal.find('.modal-body').html(dataReponse);
        }
    }
    let json = button.data('id');
    modal.find(submit).attr('value', json);
    xhttp.open("POST", path, true);
    xhttp.send(json);
}


