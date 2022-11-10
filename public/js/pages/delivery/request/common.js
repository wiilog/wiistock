function onDeliveryTypeChange($type, mode) {
    toggleLocationSelect($type);
    toggleRequiredChampsLibres($type, mode);
}

function toggleLocationSelect($type, $modal = null) {
    $modal = $modal || $type.closest('.modal');
    const $locationSelector = $modal.find(`select[name="destination"]`);
    const $restrictedResults = $modal.find(`input[name="restrictedLocations"]`);
    const typeId = $type.val();

    if (typeId) {
        const defaultDeliveryLocations = $modal.find('[name="defaultDeliveryLocations"]').data('value');
        const userDropzone = $modal.find('[name="userDropzone"]').data('value');

        const preselectedLocation = (
            (defaultDeliveryLocations ? defaultDeliveryLocations[typeId] || defaultDeliveryLocations.all : null)
            || userDropzone
        );
        Select2Old.project($('.ajax-autocomplete-project'));

        Select2Old.init(
            $locationSelector,
            '',
            $restrictedResults.val() ? 0 : 1,
            {
                route: 'get_locations_by_type',
                param: {
                    type: $type.val(),
                }
            },
            {},
            preselectedLocation
                ? {value: preselectedLocation.id, text: preselectedLocation.label}
                : {}
        );
        $locationSelector.prop(`disabled`, false);
    } else {
        $locationSelector.val(null).trigger('change');
        $locationSelector.prop(`disabled`, true);
    }
}
