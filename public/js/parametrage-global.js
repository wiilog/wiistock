function  toggleActiveDemandeLivraison(switchButton, path) {
    $.post(path, JSON.stringify({val: switchButton.is(':checked')}), function () {
    }, 'json');
}
