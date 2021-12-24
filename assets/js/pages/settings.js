import '../../scss/pages/settings.scss';

const settings = JSON.parse($(`input#settings`).val());
let category = $(`input#category`).val();
let menu = $(`input#menu`).val();
let submenu = $(`input#submenu`).val();

$(document).ready(() => {
    updateTitle(submenu || menu);

    $(`.settings-item`).on(`click`, function() {
        const selectedMenu = $(this).data(`menu`);

        $(`.settings-item.selected`).removeClass(`selected`);
        $(this).addClass(`selected`);

        $(`.settings main .wii-box`).addClass(`d-none`);
        $(`.settings main .wii-box[data-menu="${selectedMenu}"]`).removeClass(`d-none`);

        updateTitle(selectedMenu);

        //TODO: gérer l'initialisation du nouveau conteneur jsp quel est le meilleur moyen de faire
    })
})

function getCategoryLabel() {
    return settings[category].label;
}

function getMenuLabel() {
    const menuData = settings[category].menus[menu];

    if(typeof menuData === `string`) {
        return menuData;
    } else {
        return menuData.label;
    }
}

function getSubmenuLabel() {
    if(!submenu) {
        return null;
    } else {
        return settings[category].menus[menu].menus[submenu];
    }
}

function updateTitle(selectedMenu) {
    if(!submenu) {
        menu = selectedMenu;
        $(`#page-title`).html(`${getCategoryLabel()} | <span class="bold">${getMenuLabel()}</span>`);
    } else {
        submenu = selectedMenu;
        $(`#page-title`).html(`${getCategoryLabel()} | ${getMenuLabel()} | <span class="bold">${getSubmenuLabel()}</span>`);
    }
    console.log(submenu);
}
