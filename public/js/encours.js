$(function() {
   $.post(Routing.generate('check_time_worked_is_defined', true), (data) => {
       if (data === false) {
           alertErrorMsg('Veuillez définir les horaires travaillés dans Paramétrage/Paramétrage global.', true);
       }
       initDatatables();
   });
});

function initDatatables() {
    let routeForApi = Routing.generate('en_cours_api', true);
    $('.encours-table').each(function () {
        let that = $(this);
        that.DataTable({
            processing: true,
            responsive: true,
            "language": {
                url: "/js/i18n/dataTableLanguage.json",
            },
            ajax: {
                "url": routeForApi,
                "contentType": "application/json",
                "type": "POST",
                "data": function (data) {
                    return JSON.stringify({
                        id: that.attr('id')
                    });
                }
            },
            columns: [
                {"data": 'colis', 'name': 'colis', 'title': 'Colis'},
                {"data": 'date', 'name': 'date', 'title': 'Date de dépose'},
                {"data": 'time', 'name': 'delai', 'title': 'Délai'},
                {"data": 'max', 'name': 'max', 'title': 'max'},
                {"data": 'late', 'name': 'late', 'title': 'late'},
            ],
            "columnDefs": [
                {
                    "targets": [3, 4],
                    "visible": false,
                    "searchable": false
                },
            ],
            rowCallback: function (row, data) {
                console.log(data);
                if (data.success === false) {
                    console.log('false');
                } else {
                    $(row).addClass(data.late ? 'table-danger' : '');
                }
            },
            "order": [[2, "desc"]]
        });
    });
}
