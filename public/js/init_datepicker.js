$('#dateMin').datetimepicker({
    format: 'DD/MM/YYYY',
    useCurrent: false,
    locale: moment.locale(),
    showTodayButton: true,
    showClear: true,
    icons: {
        clear: 'fas fa-trash',
    },
    tooltips: {
        today: 'Aujourd\'hui',
        clear: 'Supprimer',
        selectMonth: 'Choisir le mois',
        selectYear: 'Choisir l\'année',
        selectDecade: 'Choisir la décénie',
    },
});

$('#dateMax').datetimepicker({
    format: 'DD/MM/YYYY',
    useCurrent: false,
    locale: moment.locale(),
    showTodayButton: true,
    showClear: true,
    icons: {
        clear: 'fas fa-trash',
    },
    tooltips: {
        today: 'Aujourd\'hui',
        clear: 'Supprimer',
        selectMonth: 'Choisir le mois',
        selectYear: 'Choisir l\'année',
        selectDecade: 'Choisir la décénie',
    },
});
