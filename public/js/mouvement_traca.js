$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Operateur',
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
        { "data": 'date', 'name': 'date', 'title': 'Date' },
        { "data": "refArticle", 'name': 'refArticle', 'title': "Colis" },
        { "data": 'refEmplacement', 'name': 'refEmplacement', 'title': 'Emplacement' },
        { "data": 'type', 'name': 'type', 'title': 'Type' },
        { "data": 'operateur', 'name': 'operateur', 'title': 'Operateur' },
        { "data": 'Actions', 'name': 'Actions', 'title': 'Actions' },
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
    let demandeur = [];
    demandeur = $('#utilisateur').val()
    demandeurString = demandeur.toString();
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
        function (settings, data, dataIndex) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let dateInit = (data[0]).split(' ')[0].split('/').reverse().join('-') || 0;

            if (
                (dateMin == "" && dateMax == "")
                ||
                (dateMin == "" && moment(dateInit, 'DD-MM-YYYY').isSameOrBefore(dateMax))
                ||
                (moment(dateInit, 'DD-MM-YYYY').isSameOrAfter(dateMin) && dateMax == "")
                ||
                (moment(dateInit, 'DD-MM-YYYY').isSameOrAfter(dateMin) && moment(dateInit, 'DD-MM-YYYY').isSameOrBefore(dateMax))

            ) {
                return true;
            }
            return false;
        }

    );
    tableMvt
        .draw();
});