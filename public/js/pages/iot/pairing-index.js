let sensorWrappersSelectValue = '';

$(function () {
    pairingList();

    const $search = $('.search-bar').find('input[name=search]');
    $search.on('keyup', delay(() => {
        const activeButtons = getActiveButtonsValues();
        pairingList($search.val(), sensorWrappersSelectValue, activeButtons.activeTypeButtons, activeButtons.activeElementButtons);
    }, 500));

    $('.sensor-types-container, .categories-container').find('button').click(function() {
        $(this).toggleClass('active');
        const searchValue = getSearchValue();
        const activeButtons = getActiveButtonsValues();

        pairingList(searchValue, sensorWrappersSelectValue, activeButtons.activeTypeButtons, activeButtons.activeElementButtons)
    });
});

function filter() {
    const searchValue = getSearchValue();
    sensorWrappersSelectValue = getFilterValue();
    const activeButtons = getActiveButtonsValues();

    pairingList(searchValue, sensorWrappersSelectValue, activeButtons.activeTypeButtons, activeButtons.activeElementButtons);
}

function pairingList(search = '', filter = '', types = '', elements = '') {
    const $pairings = $('.pairings');
    const path = Routing.generate('pairing_api', {
        search: search,
        filter: filter,
        types: types,
        elements: elements
    }, true);

    $.get(path, (data) => {
        if (data) {
            $pairings.removeClass('justify-content-center')
            $pairings.empty();
            if (data.length > 0) {
                const pairings = Object.values(data);
                pairings.forEach((pairing) => {
                    const temperature = parseFloat(pairing.temperature);
                    const lowTemperatureThreshold = parseFloat(pairing.lowTemperatureThreshold);
                    const highTemperatureThreshold = parseFloat(pairing.highTemperatureThreshold);

                    const lowTemperatureAlert = temperature < lowTemperatureThreshold;
                    const highTemperatureAlert = temperature > highTemperatureThreshold;

                    const $pairingContainer = `
                    <div class="col-lg-3 col-md-4 col-12 pairing-container">
                        <a class="card wii-card request-card pointer shadow-sm bg-white pairing-card" href="${Routing.generate('pairing_show', {pairing: pairing.id})}">
                            <div class="d-flex sensor-details">
                                <div class="type d-flex justify-content-center align-items-center ${lowTemperatureAlert ? 'low-temperature-bg' : highTemperatureAlert ? 'high-temperature-bg' : ''}">
                                    <span class="wii-icon wii-icon-iot-${pairing.typeIcon} ${lowTemperatureAlert ? 'low-temperature-icon' : highTemperatureAlert ? 'high-temperature-icon' : ''}"></span>
                                </div>
                                <div class="name col-10 d-flex justify-content-center align-items-center ${lowTemperatureAlert ? 'low-temperature-font' : highTemperatureAlert ? 'high-temperature-font' : ''}">
                                    ${pairing.name}
                                </div>
                            </div>
                            <div class="element d-flex justify-content-center align-items-center">
                                <span class="wii-icon wii-icon-iot-${pairing.elementIcon} mr-2"></span>
                                ${pairing.element}
                            </div>
                        </a>
                    </div>`;

                    $pairings.append($pairingContainer);
                });
            } else {
                $pairings.addClass('d-flex justify-content-center');
                const $emptyResult = $(`<div/>`, {
                    class: `d-flex flex-column align-items-center`,
                    html: $(`<p/>`, {
                        class: `h4`,
                        text: `Aucune association ne correspond à votre recherche`
                    })
                });

                const $icon = $(`<i/>`, {
                    class: `fas fa-frown fa-4x`
                });

                $emptyResult.append($icon);
                $pairings.append($emptyResult);
            }
        } else {
            showBSAlert('Une erreur est survenue lors du chargement des données', 'warning');
        }
    });
}

function delay(fn, ms) {
    let timer = 0
    return function(...args) {
        clearTimeout(timer)
        timer = setTimeout(fn.bind(this, ...args), ms || 0)
    }
}

function getActiveButtonsValues() {
    const $buttonContainer = $('.pairing-button-container');
    const $activeTypeButtons = $buttonContainer.find('.sensor-types-container').children('button.active');
    const $activeElementButtons = $buttonContainer.find('.categories-container').children('button.active');
    let activeTypeButtons = [];
    let activeElementButtons = [];

    $activeTypeButtons.each(function() {
        activeTypeButtons.push($(this).data('id'));
    });

    $activeElementButtons.each(function() {
        activeElementButtons.push($(this).data('id'));
    });

    return {activeTypeButtons, activeElementButtons}
}

function getSearchValue() {
    return $('.search-bar').find('input[name=search]').val();
}

function getFilterValue() {
    return $('.filter-select2[name=sensorWrappers]').val();
}
