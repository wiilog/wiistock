SELECT
    demande.id AS id,
    demande.numero AS numero,
    demande.date AS date_creation,
    preparation.date AS date_validation,
    demandeur.username AS demandeur,
    type.label AS type,
    statut.nom AS statut,

    (SELECT GROUP_CONCAT(preparation.numero SEPARATOR ' / ')
     FROM demande AS sub_demande
     LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
     WHERE sub_demande.id IN (demande.id)) AS codes_preparations,

    (SELECT GROUP_CONCAT(livraison.numero SEPARATOR ' / ')
     FROM demande AS sub_demande
              LEFT JOIN preparation ON sub_demande.id = preparation.demande_id
              LEFT JOIN livraison on preparation.id = livraison.preparation_id
     WHERE sub_demande.id IN (demande.id)) AS codes_livraisons,

    destination.label AS destination,
    demande.cleaned_comment AS commentaire,
    ligne_article_reference_article.reference AS reference_article,
    article.label AS libelle_article,
    article.bar_code AS code_barre_article,
    ligne_article_reference_article.bar_code AS code_barre_reference,
    ligne_article_reference_article.quantite_disponible AS quantite_disponible,
    IF(ligne_article.id IS NOT NULL, ligne_article.quantite,
       IF(article.id IS NOT NULL, article.quantite_aprelever, NULL)) AS quantite_a_prelever

FROM demande

LEFT JOIN preparation ON demande.id = preparation.demande_id
LEFT JOIN utilisateur AS demandeur ON demande.utilisateur_id = demandeur.id
LEFT JOIN statut ON demande.statut_id = statut.id
LEFT JOIN emplacement AS destination ON demande.destination_id = destination.id
LEFT JOIN type ON demande.type_id = type.id
LEFT JOIN article ON demande.id = article.demande_id

LEFT JOIN ligne_article ON demande.id = ligne_article.demande_id
    LEFT JOIN reference_article AS ligne_article_reference_article ON ligne_article.reference_id = ligne_article_reference_article.id

WHERE
    article.id IS NOT NULL OR ligne_article_reference_article.id IS NOT NULL

GROUP BY
    article.id,
    ligne_article_reference_article.id,
    demande.id
