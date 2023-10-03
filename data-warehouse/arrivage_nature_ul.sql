SELECT pack.arrivage_id AS arrivage_id,
       nature.label     AS nature_ul,
       pack.quantity    AS quantite_ul

FROM pack
         INNER JOIN nature ON pack.nature_id = nature.id

WHERE pack.arrivage_id IS NOT NULL
