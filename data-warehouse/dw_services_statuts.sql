SELECT h.id as service_id,
       h.number as numero,
       s.nom as statut,
       DATE_FORMAT(sh.date, '%d/%m/%Y %H:%i') as date_statut,
       u.username as utilisateur
FROM status_history as sh
         INNER JOIN handling h ON h.id = sh.handling_id
         INNER JOIN utilisateur u on h.requester_id = u.id
         INNER JOIN statut s on sh.status_id = s.id
WHERE sh.handling_id IS NOT NULL;
