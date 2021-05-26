
function toggleLocationSelect($type, $modal = null) {
    $modal = $modal || $type.closest('.modal');
    const $locationSelector = $modal.find(`select[name="destination"]`);
    const typeId = $type.val();
    if (typeId) {
        const defaultDeliveryLocations = $modal.find('[name="defaultDeliveryLocations"]').data('value');
        const userDropzone = $modal.find('[name="userDropzone"]').data('value');

        const preselectedLocation = (
            (defaultDeliveryLocations ? defaultDeliveryLocations[typeId] || defaultDeliveryLocations.all : null)
            || userDropzone
        );

        Select2Old.init(
            $locationSelector,
            '',
            1,
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
        if (!preselectedLocation) {
            $locationSelector.val(null).trigger('change');
        }
        $locationSelector.prop(`disabled`, false);
    } else {
        $locationSelector.val(null).trigger('change');
        $locationSelector.prop(`disabled`, true);
    }
}
