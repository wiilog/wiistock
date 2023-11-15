global.onFilterTypeChange = onFilterTypeChange;
global.updateSelectedStatusesCount = updateSelectedStatusesCount;
global.checkAllInDropdown = checkAllInDropdown;
global.initFilterStatusMutiple = initFilterStatusMutiple;

function onFilterTypeChange($select) {
    let typesIds = $select.val();
    if(!Array.isArray(typesIds)) {
        typesIds = [typesIds];
    }

    $('.statuses-filter').find('.dropdown-item').each(function() {
        const type = $(this).data('type');
        const typeLabel = $(this).data('type-label');
        const $input = $(this).find('input');

        if($input.attr('name') !== 'all') {
            if(typesIds.length > 0 && !typesIds.includes(type.toString()) && !typesIds.includes(typeLabel) && !typesIds.includes('')){
                $(this).addClass('d-none');
                $input.prop('checked', false);
            } else {
                $(this).removeClass('d-none');
            }
        }
    });

    if(!$select.data('first-load')) {
        const $checkboxes = $('.statuses-filter .filter-status-multiple-dropdown').find('input[type=checkbox]');
        $checkboxes.prop('checked', false);
        updateSelectedStatusesCount(0);
    }

    $select.data('first-load', false);
}

function updateSelectedStatusesCount(length) {
    const plural = length > 1;
    $('.status-filter-title').html( !plural
        ? Translation.of('Demande', 'Services', null, '{1} statut sélectionné', false, {'1':length})
        : Translation.of('Demande', 'Services', null,  '{1} statuts sélectionnés', false, {'1':length}));
}

function checkAllInDropdown($checkbox) {
    const $parentMenu = $checkbox.parents('.dropdown-menu');
    const $checkboxes = $parentMenu.find(' .dropdown-item:not(.d-none) input[type=checkbox]:not(:first)');
    $checkboxes.each(function() {
        if(!$(this).parents('.dropdown-item').hasClass('d-none')) {
            $(this).prop('checked', $checkbox.is(':checked'));
        }
    });

    const checkboxesLength = $checkbox.is(':checked') ? $checkboxes.length : 0;
    updateSelectedStatusesCount(checkboxesLength);
}

function initFilterStatusMutiple(){
    $('.filter-status-multiple-dropdown .dropdown-item:not(:first-of-type)').on('click', event => {
        const $clicked = $(event.target);
        if(!$clicked.is(`input[type="checkbox"]`)) {
            event.preventDefault();
            event.stopImmediatePropagation();

            const $checkbox = $(event.currentTarget).find(`input[type="checkbox"]`);
            $checkbox.prop(`checked`, !$checkbox.is(`:checked`));

            if (!$checkbox.is(`:checked`)) {
                $(`.filter-status-multiple-dropdown input[type="checkbox"][name="all"]`).prop(`checked`, false);
            }
        }

        const $checkedCheckboxesLength = $(`.filter-status-multiple-dropdown .dropdown-item:not(.d-none) input[type=checkbox]:checked`).length;
        updateSelectedStatusesCount($checkedCheckboxesLength);
    });
}
