$(document).ready(function () {
    $('.select2').select2();
});

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
    submit.click( function () 
    {
        xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () 
        {
            if (this.readyState == 4 && this.status == 200)
            {
                table.ajax.reload(function( json ) 
                {
                    console.log('yolo');
                    data = JSON.parse(this.responseText);
                    $('#myInput').val( json.lastInput );
                    if(data.redirect)
                    {
                        window.location.href = data.redirect;
                    }
                });
            }      
        };
        let inputs = modal.find(".data"); // On récupère toutes les données qui nous intéresse avec le querySelectorAll
        let Data = {} ; // Tableau de données
        inputs.each(function() {
           Data[$(this).attr("name")] = $(this).val();
        });
        Json = [];
        Json.push( JSON.stringify(Data)); // On transforme les données en JSON
        xhttp.open("POST", path, true);
        xhttp.send(Json);
    });
}


function deleteRow(){
    let id = button.data('id');
    modal.data('id', id);
}


/**
 * Initialise une fenêtre modale
 * 
 * @param {Document} submit le bouton qui va envoyé les données au controller via Ajax.
 * @param {string} path le chemin pris pour envoyer les données.
 * @param {document} table le DataTable gérant les données
 * 
 */
function DeleteModal(submit, path, table) {
    submit.click( function () 
    {
        let id = $(this).parent().find('.dataDelete').html();
        xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () 
        {
            if (this.readyState == 4 && this.status == 200)
            {
                table.ajax.reload(function( json ) 
                {
                    data = JSON.parse(this.responseText);
                    $('#myInput').val( json.lastInput );
                    if(data.redirect)
                    {
                        window.location.href = data.redirect;
                    }
                });
            }      
        };
        
        
        let Data = {} ; // Tableau de données
        Data[$(this).attr("name")] = 3
        Json = [];
        Json.push( JSON.stringify(Data)); // On transforme les données en JSON
        xhttp.open("POST", path, true);
        console.log('hello');
        xhttp.send(Json);
    });
}