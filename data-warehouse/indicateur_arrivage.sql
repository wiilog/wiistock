SELECT
    (SELECT COUNT(DISTINCT arrivage.fournisseur_id) FROM arrivage) AS nb_fournisseurs_differents,
    ((SELECT COUNT(arrivage.id)
      FROM arrivage
      WHERE arrivage.is_urgent = 1) / (SELECT COUNT(arrivage.id) FROM arrivage)) AS pourcentage_urgence,
    ((SELECT COUNT(arrivage.id)
      FROM arrivage
      WHERE arrivage.destinataire_id IS NOT NULL) /
     (SELECT COUNT(arrivage.id)
      FROM arrivage)) AS analyse_destinataire
