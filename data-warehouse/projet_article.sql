SELECT
    article.id                          AS article_id,
    project.code                        AS projet,
    project_history_record.created_at   AS date_assignation

FROM project_history_record
         LEFT JOIN pack on project_history_record.pack_id = pack.id
         INNER JOIN article on pack.id = article.current_logistic_unit_id
         LEFT JOIN project on project_history_record.project_id = project.id
