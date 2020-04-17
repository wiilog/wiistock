$(function () {
    const classShow = 'show';
    const classActive = 'active';
    $('#main-nav .menu-button').on('click', function () {
        $('#main-nav .dropdown-menu-sub').removeClass(classShow);
        $('#main-nav .dropdown-item').removeClass(classActive);
    });

    $('.dropdown-item-sub').on('click', function () {
        const $dropdownItem = $(this);
        const $dropdownSub = $dropdownItem.siblings('.dropdown-menu-sub');
        const isAlreadyShown = $dropdownSub.is('.show');
        $('.dropdown-menu-sub').removeClass(classShow);

        if (!isAlreadyShown) {
            const $dropdownMenuSub = $dropdownItem.siblings('.dropdown-menu-sub');
            if ($dropdownItem.length > 0
                && $dropdownMenuSub.length > 0) {
                const subMenuHeight = $dropdownMenuSub.height();
                const subMenuY = $dropdownItem[0].getBoundingClientRect().y;
                const windowHeight = $(window).height();

                const delta = 20;
                const top = ((subMenuY + subMenuHeight + delta) > windowHeight)
                    ? `-${subMenuHeight / 2}px`
                    : '0';

                $dropdownMenuSub.css('top', top);
                $dropdownMenuSub.addClass(classShow);
                $dropdownItem.addClass(classActive);
            }
        }
    });
});
