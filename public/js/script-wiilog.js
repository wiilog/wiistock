$(document).ready(function () {
    $('.select2').select2();
});


/**
 * Initialise une fenêtre modale
 * 
 * @param {Document} modal la fenêtre modale selectionnée : $("#modal").
 * @param {Document} submit le bouton qui va envoyé les données au controller via Ajax.
 * @param {string} path le chemin pris pour envoyer les données.
 * @param {document} table le DataTable gérant les données
 * 
 */
function InitialiserModal(modal, submit, path, table) {
    submit.click(function () 
    {
        xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () 
        {
            if (this.readyState == 4 && this.status == 200)
            {
                data = JSON.parse(this.responseText);
                table.ajax.reload(function( json ) 
                {
                    $('#myInput').val( json.lastInput );
                    
                    if(data.redirect)
                    {
                        window.location.href = data.redirect;
                    }
                });
            }      
        };

        let inputs = modal.find(".data"); // On récupère toutes les données qui nous intéresse
        let Data = []; // Tableau de données

        inputs.each(function() {
            Data.push({
                [$(this).attr("name")]: $(this).val()
            }); 
        });

        Json = JSON.stringify(Data); // On transforme les données en JSON
        xhttp.open("POST", path, true);
        xhttp.send(Json);
    });
}