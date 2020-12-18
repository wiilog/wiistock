SELECT
    urgence.id,
    date_start AS debut_delais_livraison, -- SED
    date_end AS fin_delais_livraison, -- SED
    commande AS no_commande, -- SED
    post_nb AS no_poste, -- SED
    utilisateur.username AS acheteur, -- SED
    fournisseur.code_reference AS fournisseur, -- SED
    transporteur.code AS transporteur, -- SED
    tracking_nb AS no_tracking, -- SED
    arrivage.date AS date_arrivage, -- SED
    arrivage.numero_arrivage, -- SED
    created_at AS date_creation -- SED

FROM urgence

     LEFT JOIN utilisateur ON urgence.buyer_id = utilisateur.id
     LEFT JOIN fournisseur ON urgence.provider_id = fournisseur.id
     LEFT JOIN transporteur ON urgence.carrier_id = transporteur.id
     LEFT JOIN arrivage ON urgence.last_arrival_id = arrivage.id
