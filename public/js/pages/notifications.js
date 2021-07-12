let pathNotifications = Routing.generate("notifications_api", true);
let tableNotificationsConfig = {
    processing: true,
    serverSide: true,
    ajax: {
        "url": pathNotifications,
        "type": "POST",
    },
    drawConfig: {
        needsSearchOverride: true,
        needsResize: true
    },
    columns: [
        { "data": 'content', 'name': 'content', 'title': '', 'orderable': false},
    ],
};
let tableNotifications = initDataTable('tableNotifications', tableNotificationsConfig);

$(function() {
    initDateTimePicker();
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ALERTE);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
    });
});
