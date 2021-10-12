SELECT purchase_request.id                      AS id,
       purchase_request.number                  AS numero,
       purchase_request.creation_date           AS date_creation,
       purchase_request.validation_date         AS date_validation,
       purchase_request.consideration_date      AS date_prise_en_compte,
       purchase_request.processing_date         AS date_traitement,
       demandeur.username                       AS demandeur,
       acheteur.username                        AS acheteur,
       statut.nom                               AS statut,
       reference_article.reference              AS reference,
       reference_article.libelle                AS libelle,
       purchase_request_line.requested_quantity AS quantite_demandee,
       reference_article.quantite_stock         AS quantite_stock,
       purchase_request_line.ordered_quantity   AS quantite_commandee,
       reception.order_number                   AS numero_commande,
       reception.number                         AS numero_reception,
       fournisseur.nom                          AS fournisseur,
       reception.date_commande                  AS date_commande,
       reception.date_attendue                  AS date_attendue

FROM purchase_request

         LEFT JOIN utilisateur AS demandeur on purchase_request.requester_id = demandeur.id
         LEFT JOIN utilisateur AS acheteur on purchase_request.buyer_id = acheteur.id
         LEFT JOIN statut ON purchase_request.status_id = statut.id
         LEFT JOIN purchase_request_line ON purchase_request.id = purchase_request_line.purchase_request_id
         LEFT JOIN reference_article ON purchase_request_line.reference_id = reference_article.id
         LEFT JOIN reception ON purchase_request_line.reception_id = reception.id
         LEFT JOIN fournisseur ON reception.fournisseur_id = fournisseur.id
