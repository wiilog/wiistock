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
    submit.click( function () 
    {
        xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () 
        {
            if (this.readyState == 4 && this.status == 200)
            {
                table.ajax.reload(function( json ) 
                {
                    if(this.responseText !== undefined){
                        data = JSON.parse(this.responseText);
                        $('#myInput').val( json.lastInput );
                        if(data.redirect)
                        {
                            window.location.href = data.redirect;
                        }
                    }
                });
            }      
        };
        let inputs = modal.find(".data"); // On récupère toutes les données qui nous intéresse
        let Data = {} ; // Tableau de données
        inputs.each(function() {
           Data[$(this).attr("name")] = $(this).val();
        });
        console.log(Data)
        Json = {};
        Json = JSON.stringify(Data); // On transforme les données en JSON
        console.log(Json);
        xhttp.open("POST", path, true);
        xhttp.send(Json);
    });
}


//DELETE
function deleteRow(button, modal, submit){
    let id = button.data('id');
    console.log(id)
    modal.find(submit).attr('value', id);
}


//SHOW
function showRow() {
    
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () 
    {
        if (this.readyState == 4 && this.status == 200)
        {
            console.log('hello')
        }
    }
    xhttp.open("POST", path, true);
    xhttp.send(Json);
}


//MODIFY
/**
 * La fonction modifie les valeurs d'une modale modifier avec les valeurs data-attibute.
 * Ces valeurs peuvent être trouvées dans datatableLigneArticleRow.html.twig
 * 
 * @param {Document} button 
 */
function editRow(button) {
    let data = button.data();
    let Data = {};

    for(var key in data) {
        Data[key] = data[key];
    }

    let modal = $('#modalModify');
  
    for(var key in Data) {
        if(key === "id") {
            modal.data("id", Data[key]);
        }
        modal.find('.'+ key).val(Data[key]);
        modal.find('.'+ key).html(Data[key]);
    }
}


/**
 * Initialise une fenêtre modale, le path est généré à l'aide d'un data-id dans la modal.
 * La fonction doit être utilisé avec la fonction editRow();
 * 
 * @param {Document} modal la fenêtre modale selectionnée : $("#modal").
 * @param {Document} submit le bouton qui va envoyé les données au controller via Ajax.
 * @param {Document} table le DataTable gérant les données
 * @param {String} pathName le nom du chemin "monExempleChemin"
 * 
 */
function modifyModal(modal, submit, table, pathName) 
{
    submit.click(function() {
        let inputs = modal.find('.data');
        let json = {};

        inputs.each(function() {
            json[$(this).attr("name")] = $(this).val();
         });

        Json = [];

        Json.push(JSON.stringify(json));
        let id = modal.data('id');
        let path = Routing.generate(pathName, {id: id} , true);

        xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () 
        {
            if (this.readyState == 4 && this.status == 200)
            {
                data = JSON.parse(this.responseText);
                table.ajax.reload(function( json ) 
                {
                    $('#myInput').val( json.lastInput );
                });
                if(data.redirect)
                {
                    window.location.href = data.redirect;
                }
            }      
        };
        xhttp.open("POST", path, true);
        xhttp.send(Json);
    });
}