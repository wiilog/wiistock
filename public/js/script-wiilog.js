$(document).ready(function () {
    $('.select2').select2();
});0



//Fonction de traitement des donnees post ajax pour un tableau
function traitementDataArticle(reponse, id_table) {
    $(id_table).children().remove();
    let articles = JSON.parse(JSON.parse(reponse));
    var refTable = document.getElementById(id_table)
    if (articles !== "patate") {
        console.log(articles)

        var rowCount = refTable.rows.length;
        articles.forEach(function (article) {
            var nouvelleLigne = refTable.insertRow();

            var nouvelleCellule = nouvelleLigne.insertCell(0);
            var nouveauTexte = document.createTextNode(article.nom);
            nouvelleCellule.appendChild(nouveauTexte);

            var nouvelleCellule = nouvelleLigne.insertCell(1);
            var nouveauTexte = document.createTextNode(article.statut);
            nouvelleCellule.appendChild(nouveauTexte);

            var nouvelleCellule = nouvelleLigne.insertCell(2);
            var nouveauTexte = document.createTextNode(article.etat);
            nouvelleCellule.appendChild(nouveauTexte);

            var nouvelleCellule = nouvelleLigne.insertCell(3);
            var nouveauTexte = document.createTextNode(article.refArticle);
            nouvelleCellule.appendChild(nouveauTexte);

            var nouvelleCellule = nouvelleLigne.insertCell(4);
            var nouveauTexte = document.createTextNode(article.position);
            nouvelleCellule.appendChild(nouveauTexte);

            var nouvelleCellule = nouvelleLigne.insertCell(5);
            var nouveauTexte = document.createTextNode(article.direction);
            nouvelleCellule.appendChild(nouveauTexte);

            var nouvelleCellule = nouvelleLigne.insertCell(6);
            var nouveauTexte = document.createTextNode(article.quantite);
            nouvelleCellule.appendChild(nouveauTexte);

            var urlShow = 'http://localhost/WiiStock/WiiStock/public/index.php/articles/show/' + article.id;
            var urlEdit = 'http://localhost/WiiStock/WiiStock/public/index.php/articles/edite/' + article.id;
            var nouvelleCellule = nouvelleLigne.insertCell(7);
            var a = document.createElement("a");
            a.className = 'btn btn-xs btn-default command-edit';
            a.setAttribute('href', urlShow);
            var i = document.createElement("i");
            i.className = 'fas fa-eye fa-2x';
            nouvelleCellule.appendChild(a).appendChild(i);
            var a = document.createElement("a");
            a.className = 'btn btn-xs btn-default command-edit';
            a.setAttribute('href', urlEdit);
            var i = document.createElement("i");
            i.className = 'fas fa-pencil-alt fa-2x';
            nouvelleCellule.appendChild(a).appendChild(i);
        });
    }else {
        var nouvelleLigne = refTable.insertRow();
        var nouvelleCellule = nouvelleLigne.insertCell(0);
        var nouveauTexte = document.createTextNode("aucun résultat");
        nouvelleCellule.appendChild(nouveauTexte);
    }
}

//Fonction de recherche par input => str = valeur de l'input + url = url du traitement en php
function showHint(str, url) {
    if (str.length > 0) {
        var xmlhttp = new XMLHttpRequest()
        xmlhttp.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                traitementDataArticle(this.responseText)
            }
        }
        var myJSON = JSON.stringify(str)
        xmlhttp.open("POST", url, true)
        xmlhttp.send(myJSON)
    }
}


