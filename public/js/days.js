let pathDays = Routing.generate('days_param_api', true);
let tableDays = $('#tableDays').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathDays,
        "type": "POST"
    },
    columns:[
        { "data": 'Day', 'title' : 'Jour' },
        { "data": 'Worked', 'title' : 'Travaillé ou non' },
        { "data": 'Times', 'title' : 'Horaires de travail' },
        { "data": 'Order', 'title' : 'Ordre' },
        { "data": 'Actions', 'title' : 'Actions' },
    ],
    order: [
        [3, 'asc']
    ],
    columnDefs: [
        {
            'targets': [3],
            'visible': false
        }
    ],
});

let modalEditDays = $('#modalEditDays');
let submitEditDays = $('#submitEditDays');
let urlEditDays = Routing.generate('days_edit', true);
InitialiserModal(modalEditDays, submitEditDays, urlEditDays, tableDays, errorEditDays, false, false);

function errorEditDays(data) {
    let modal = $("#modalEditDays");
    if (data.success === false) {
        displayError(modal, data.msg, data.success);
    } else {
        modal.find('.close').click();
        alertSuccessMsg(data.msg);
    }
}