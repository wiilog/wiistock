$(document).ready(function() {
    $('.encours-table').each(function() {
        $(this).DataTable({
            "order": [[ 3, "desc" ]]
        });
    });
});
