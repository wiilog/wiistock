SELECT
    urgence.id,
    date_start AS debut_delais_livraison,
    date_end AS fin_delais_livraison,
    commande AS no_commande,
    post_nb AS no_poste,
    utilisateur.username AS acheteur,
    fournisseur.code_reference AS fournisseur,
    transporteur.code AS transporteur,
    tracking_nb AS no_tracking,
    arrivage.date AS date_arrivage,
    arrivage.numero_arrivage,
    created_at AS date_creation

FROM urgence

     LEFT JOIN utilisateur ON urgence.buyer_id = utilisateur.id
     LEFT JOIN fournisseur ON urgence.provider_id = fournisseur.id
     LEFT JOIN transporteur ON urgence.carrier_id = transporteur.id
     LEFT JOIN arrivage ON urgence.last_arrival_id = arrivage.id
