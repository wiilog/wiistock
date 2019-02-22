
$('#test').on('click', console.log('hello')) 


function addArticle(){
    console.log("patatete");
}

InitialiserModal

/**
 * Initialise une fenêtre modale
 * 
 * @param {Document} modal la fenêtre modale selectionnée : document.getElementById("modal").
 * @param {Document} submit le bouton qui va envoyé les données au controller via Ajax.
 * @param {string} path le chemin pris pour envoyé les données.
 * 
 */
function InitialiserModal(modal, submit, path) {
    submit.addEventListener("click", function () 
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

        let inputs = modal.querySelectorAll(".data"); // On récupère toutes les données qui nous intéresse avec le querySelectorAll
        let Data = []; // Tableau de données

        inputs.forEach(input => { 
            Data.push({
                [input.name]: input.value
            });
        });

        Json = JSON.stringify(Data); // On transforme les données en JSON
        xhttp.open("POST", path, true);
        xhttp.send(Json);
    });
}