$(function () {
    const classShow = 'show';
    $('.menu-button').on('click', function () {
        $('.dropdown-menu-sub').removeClass(classShow);
    });
    $('.dropdown-item-sub').on('click', function () {
        const $dropdownItem = $(this);
        const $dropdownSub = $dropdownItem.siblings('.dropdown-menu-sub');
        const isAlreadyShown = $dropdownSub.is('.show');
        $('.dropdown-menu-sub').removeClass(classShow);

        if (!isAlreadyShown) {
            $dropdownItem.siblings('.dropdown-menu-sub').addClass(classShow)
        }
    });
});
