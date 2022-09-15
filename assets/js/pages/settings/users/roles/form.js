import {LOADING_CLASS} from "../../../../loading";
import Flash, {INFO} from "../../../../flash";

global.discardChanges = discardChanges;
global.saveSettings = saveSettings;

$(function() {
    const $settingsItems = $('aside .settings-item');
    const $settingsContent = $('main .settings-content');
    if (!$settingsItems.filter('.selected').exists()) {
        const $selectedItem = $settingsItems.first();
        onSettingsItemSelected($selectedItem, $settingsItems, $settingsContent);
    }
});

function saveSettings($saveButton) {
    const role = $saveButton.data('role-id');
    const url = role
        ? Routing.generate('settings_role_edit', {role})
        : Routing.generate('settings_role_new');

    $saveButton.pushLoader('white');
    const formData = createRoleFormData();

    if (formData) {
        $.ajax({
            url,
            data: formData,
            type: "post",
            contentType: false,
            processData: false,
            cache: false,
            dataType: "json",
        })
            .then(({success, redirect, message}) => {
                if (success) {
                    window.location.href = redirect;
                }
                else {
                    Flash.add('danger', message);
                    $saveButton.popLoader();
                }
            })
            .catch(() => {
                $saveButton.popLoader();
            })
    }
    else {
        $saveButton.popLoader();
    }
}

function discardChanges() {
    const $saveButton = $('.save-settings');

    if ($saveButton.hasClass(LOADING_CLASS)) {
        Flash.add(INFO, `Une opération est en cours de traitement`);
        return;
    }

    window.location.href = Routing.generate(`settings_item`, {
        menu: 'roles',
        submenu: null,
        category: 'utilisateurs'
    });
}

function createRoleFormData() {
    const form = Form.process($('main .role-header'));

    if (form instanceof FormData) {
        // get all checkbox pages even hidden
        const actions = $('.settings-content .data[type="checkbox"]:checked')
            .toArray()
            .map((checkbox) => $(checkbox).attr('name'))
            .map((name) => {
                const [_, actionId] = name.match(/action-(\d+)/) || [];
                return Number(actionId);
            })
            .filter((actionId) => actionId);

        form.appendAll({actions});
    }

    return form;
}
