$(document).ready(function() {
    $('.encours-table').each(function() {
        $(this).DataTable({
            "language": {
                url: "/js/i18n/dataTableLanguage.json",
            },
            "order": [[ 2, "desc" ]]
        });
    });
});
