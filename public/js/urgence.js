$(function() {
    ajaxAutoUserInit($('.ajax-autocomplete-user'));
    initPage();
    initDateTimePicker('#dateMin, #dateMax');
    initDateTimePicker('#dateStart, #dateEnd', 'DD/MM/YYYY HH:mm');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_URGENCES);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');
});

function initPage() {
    let pathUrgences = Routing.generate('urgence_api', true);
    let tableUrgence = $('#tableUrgences').DataTable({
        processing: true,
        serverSide: true,
        "language": {
            url: "/js/i18n/dataTableLanguage.json",
        },
        ajax:{
            "url": pathUrgences,
            "type": "POST"
        },
        order: [[1, "desc"]],
        columns:[
            { "data": 'actions', 'title': 'Actions' },
            { "data": 'start', 'name' : 'start','title' : $('#dateBeginTranslation').val() },
            { "data": 'end', 'name' : 'end', 'title' : $('#dateEndTranslation').val() },
            { "data": 'commande', 'name' : 'commande', 'title' : $('#numComTranslation').val() },
            { "data": 'buyer', 'name' : 'buyer', 'title' : $('#buyerTranslation').val() },
        ],
        drawCallback: function() {
            overrideSearch($('#tableUrgences_filter input'), tableUrgence);
        },
        headerCallback: function(thead) {
            $(thead).find('th').eq(1).attr('title', "date de début");
            $(thead).find('th').eq(2).attr('title', "date de fin");
            $(thead).find('th').eq(3).attr('title', "numéro de commande");
            $(thead).find('th').eq(4).attr('title', "acheteur");
        },
        columnDefs: [
            {
                "orderable" : false,
                "targets" : [0, 4]
            },
            {
                "type": "customDate",
                "targets": [1, 2]
            }
        ],
    });

    let modalNewUrgence = $('#modalNewUrgence');
    let submitNewUrgence = $('#submitNewUrgence');
    let urlNewUrgence = Routing.generate('urgence_new');
    InitialiserModal(modalNewUrgence, submitNewUrgence, urlNewUrgence, tableUrgence);

    let modalDeleteUrgence = $('#modalDeleteUrgence');
    let submitDeleteUrgence = $('#submitDeleteUrgence');
    let urlDeleteUrgence = Routing.generate('urgence_delete', true);
    InitialiserModal(modalDeleteUrgence, submitDeleteUrgence, urlDeleteUrgence, tableUrgence);

    let modalModifyUrgence = $('#modalEditUrgence');
    let submitModifyUrgence = $('#submitEditUrgence');
    let urlModifyUrgence = Routing.generate('urgence_edit', true);
    InitialiserModal(modalModifyUrgence, submitModifyUrgence, urlModifyUrgence, tableUrgence);
}

function callbackEditFormLoading($modal, buyerId, buyerName) {
    initDateTimePicker('#modalEditUrgence .datepicker', 'DD/MM/YYYY HH:mm');
    let $dateStartInput = $('#modalEditUrgence').find('.dateStart');
    let dateStart = $dateStartInput.data('date');

    let $dateEndInput = $('#modalEditUrgence').find('.dateEnd');
    let dateEnd = $dateEndInput.data('date');

    $dateStartInput.val(moment(dateStart, 'YYYY-MM-DD HH:mm').format('DD/MM/YYYY HH:mm'));
    $dateEndInput.val(moment(dateEnd, 'YYYY-MM-DD HH:mm').format('DD/MM/YYYY HH:mm'));

    ajaxAutoUserInit($modal.find('.ajax-autocomplete-user'));

    if (buyerId && buyerName) {
        let option = new Option(buyerName, buyerId, true, true);
        const $selectBuyer = $modal.find('.ajax-autocomplete-user[name="acheteur"]');
        $selectBuyer.append(option).trigger('change');
    }
}
