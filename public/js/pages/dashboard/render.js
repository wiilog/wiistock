function todo() { //TODO: remove todo
    console.error("To do");
}

const ONGOING_PACK = 'ongoing_packs';
const DAILY_ARRIVALS = 'daily_arrivals';
const LATE_PACKS = 'late_packs';
const CARRIER_INDICATOR = 'carrier_indicator';
const DAILY_ARRIVALS_AND_PACKS = 'daily_arrivals_and_packs';

const creators = {
    [ONGOING_PACK]: createOngoingPackElement,
    [DAILY_ARRIVALS]: todo,
    [LATE_PACKS]: todo,
    [CARRIER_INDICATOR]: createCarrierIndicatorElement,
    [DAILY_ARRIVALS_AND_PACKS]: todo,
};


/**
 *
 * @param {jQuery} $container
 * @param {string} meterKey
 * @param {boolean=false} isExample
 * @param {*} data
 * @return {boolean}
 */
function renderComponent(meterKey,
                         $container,
                         data,
                         isExample = false) {
    $container.html('');
    if (!creators[meterKey]) {
        console.error(`No creator function for ${meterKey} key.`);
        return false;
    }
    else {
        const $element = creators[meterKey](data, isExample);
        if ($element) {
            $container.html($element);
        }
        return !!$element;
    }
}

/**
 * @param {*} data
 * @param {boolean=false} isExample
 * @return {boolean|jQuery}
 */
function createCarrierIndicatorElement(data, isExample = false) {
    if (!data || data.carriers === undefined) {
        console.error(`Invalid data for carrier indicator element.`);
        return false;
    }

    let carriers = Array.isArray(data.carriers) ? data.carriers.join() : data.carriers;
    let tooltip = data.tooltip || "";
    let title = data.title || "";

    return $('<div/>', {
        class: `dashboard-box-container ${isExample ? 'flex-fill' : ''}`,
        html: $('<div/>', {
            class: `dashboard-box justify-content-around dashboard-stats-container`,
            html: `
                <div class="title">
                    ${title}
                </div>
                <div class="points has-tooltip" title="${tooltip}">
                    <i class="fa fa-question ml-1"></i>
                </div>
                <p>${carriers}</p>
            `,
        })
    });
}

/**
 * @param {*} data
 * @param {boolean=false} isExample
 * @return {boolean|jQuery}
 */
function createOngoingPackElement(data,
                                  isExample) {
    if (!data || data.count === undefined) {
        console.error(`Invalid data for ongoing pack element.`);
        return false;
    }

    return $('<div/>', {
        class: `dashboard-box-container ${isExample ? 'flex-fill' : ''}`,
        html: $('<div/>', {
            class: 'dashboard-box text-center justify-content-around dashboard-stats-container',
            html: [
                data.title
                    ? $('<div/>', {
                        class: 'text-center title ellipsis',
                        text: data.title
                    })
                    : undefined,
                data.subtitle
                    ? $('<div/>', {
                        class: 'location-label ellipsis small',
                        text: data.subtitle
                    })
                    : undefined,
                data.count !== undefined
                    ? $('<div/>', {
                        class: 'align-items-center',
                        html: `<div class="dashboard-stats dashboard-stats-counter">${data.count ? data.count : '-'}</div>`
                    })
                    : undefined,
                data.delay
                    ? $('<div/>', {
                        class: `text-center title dashboard-stats-delay-title ${data.delay < 0 ? 'red' : ''}`,
                        text: data.delay < 0
                            ? 'Retard : '
                            : 'A traiter sous :'
                    })
                    : undefined,
                data.delay
                    ? $('<div/>', {
                        class: `dashboard-stats dashboard-stats-delay ${data.delay < 0 ? 'red' : ''}`,
                        text: renderMillisecondsToDelay(Math.abs(data.delay), 'display')
                    })
                    : undefined,

            ].filter(Boolean)
        })
    });
}
