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

    $('.test-print-btn').on('click', function() {
        let $button = $(this);

        let $serialNumber = $("[name='PRINTER_SERIAL_NUMBER']")
        let $getLabelWidth = $("[name='PRINTER_LABEL_WIDTH']")
        let $getLabelHeight = $("[name='PRINTER_LABEL_HEIGHT']")
        let $printerDPI = $("[name='PRINTER_DPI']");

        let inputs = [$serialNumber, $getLabelWidth, $getLabelHeight, $printerDPI];
        inputs.forEach(function (input) {
            if (input.val() ) {
                input.removeClass('is-invalid');
            } else {
                input.addClass('is-invalid');
            }
        });

        if (inputs.every(function (input) { return input.val() })) {
            $button.pushLoader(`white`);
            const {token} = GetRequestQuery();
            AJAX.route(GET, `print_article`, {
                token,
                testPrint: true,
                serialNumber: $serialNumber.val(),
                labelWidth: $getLabelWidth.val(),
                labelHeight: $getLabelHeight.val(),
                printerDPI: $printerDPI.val(),
            }).json().then((response) => {
                $button.popLoader()
                if(response.success) {
                    Flash.add(SUCCESS, 'Test d\'impression en cours.', true, true);
                }
                else {
                    Flash.add(ERROR, 'L\'envoi du test d\'impression à échoué.', true, true);
                }
            });
        }
    });
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
