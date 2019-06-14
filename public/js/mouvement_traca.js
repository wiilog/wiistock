$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Opérateur',
    }
});

$('#emplacement').select2({
    placeholder: {
        id: 0,
        text: 'Emplacement',
    }
});

let pathMvt = Routing.generate('mvt_traca_api', true);
let tableMvt = $('#tableMvts').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    "order": [[0, "desc"]],
    ajax: {
        "url": pathMvt,
        "type": "POST"
    },
    columns: [
        {"data": 'date', 'name': 'date', 'title': 'Date'},
        {"data": "refArticle", 'name': 'refArticle', 'title': "Colis"},
        {"data": 'refEmplacement', 'name': 'refEmplacement', 'title': 'Emplacement'},
        {"data": 'type', 'name': 'type', 'title': 'Type'},
        {"data": 'operateur', 'name': 'operateur', 'title': 'Operateur'},
        {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'},
    ],

});
let modalDeleteArrivage = $('#modalDeleteMvtTraca');
let submitDeleteArrivage = $('#submitDeleteMvtTraca');
let urlDeleteArrivage = Routing.generate('mvt_traca_delete', true);
InitialiserModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage, tableMvt);

$('#submitSearchMvt').on('click', function () {

    let statut = $('#statut').val();
    let emplacement = $('#emplacement').val();
    let article = $('#colis').val();
    let demandeur = $('#utilisateur').val()
    let demandeurString = demandeur.toString();
    demandeurPiped = demandeurString.split(',').join('|')

    tableMvt
        .columns('type:name')
        .search(statut)
        .draw();

    tableMvt
        .columns('operateur:name')
        .search(demandeurPiped ? '^' + demandeurPiped + '$' : '', true, false)
        .draw();
    tableMvt
        .columns('refEmplacement:name')
        .search(emplacement)
        .draw();
    tableMvt
        .columns('refArticle:name')
        .search(article)
        .draw();

    $.fn.dataTable.ext.search.push(
        function (settings, data) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let indexDate = tableMvt.column('date:name').index();
            let dateInit = (data[indexDate]).split(' ')[0].split('/').reverse().join('-') || 0;

            if (
                (dateMin == "" && dateMax == "")
                ||
                (dateMin == "" && moment(dateInit).isSameOrBefore(dateMax))
                ||
                (moment(dateInit).isSameOrAfter(dateMin) && dateMax == "")
                ||
                (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))
            ) {
                return true;
            }
            return false;
        }
    );
    tableMvt
        .draw();
});