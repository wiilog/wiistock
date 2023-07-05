import AJAX, {GET, POST} from "@app/ajax";
import Flash, {ERROR, SUCCESS} from "@app/flash";

$(function () {
    $(`.kiosk-link`).on(`click`, function() {
        const $settingsContent = $(this).closest('.settings-content');
        if(Form.process($settingsContent)){
            wrapLoadingOnActionButton($(this), () => {
                return AJAX.route(GET, `generate_kiosk_token`)
                    .json()
                    .then(({token}) => window.location.href = Routing.generate(`kiosk_index`, {token}, true));
            });
        } else {
            Flash.add('danger', 'Tous les paramètres obligatoires doivent être renseignés.')
        }
    });

    $(`.kiosk-unlink`).on(`click`, function () {
        wrapLoadingOnActionButton($(this), () => {
            return AJAX.route(POST, `kiosk_unlink`)
                .json()
                .then(() => $(this).prop(`disabled`, true));
        });
    });
});

export function initializeTouchTerminal($container){
    Select2Old.init($container.find('select[name=referenceType]'));
    Select2Old.init($container.find('select[name=collectType]'));
    Select2Old.init($container.find('select[name=location]'));
    Select2Old.init($container.find('select[name=freeField]'));
    Select2Old.init($container.find('select[name=visibilityGroup]'));
    Select2Old.init($container.find('select[name=inventoryCategories]'));
    Select2Old.init($container.find('select[name=fournisseurLabel]'));
    Select2Old.init($container.find('select[name=fournisseur]'));

    if($('#settingReferenceType').val()){
        displayFreeFields($('#settingReferenceType').val());
    }

    $('select[name=TYPE_REFERENCE_CREATE]').on('change', function (){
        if($(this).val()){
            displayFreeFields($(this).val());
        }
    })
}

function displayFreeFields(typeId){
    let $freeFieldSelect = $('select[name=FREE_FIELD_REFERENCE_CREATE]');
    $freeFieldSelect.empty();
    $.post(Routing.generate('free_fields_by_type', {type: typeId}), {}, function (data) {
        let freeFields = data.freeFields;
        freeFields.forEach(function(element){
            $freeFieldSelect.append(element);
        });
    }, 'json');
}
