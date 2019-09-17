let pathCategories = Routing.generate('invParam_api', true);
let tableCategories = $('#tableCategories').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathCategories,
        "type": "POST"
    },
    columns:[
        { "data": 'Label', 'title' : 'Label' },
        { "data": 'Frequence', 'title' : 'Fréquence' },
        { "data": 'Actions', 'title' : 'Actions' }
    ],
});