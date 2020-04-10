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
            const $dropdownMenuSub = $dropdownItem.siblings('.dropdown-menu-sub');
            if ($dropdownItem.length > 0
                && $dropdownMenuSub.length > 0) {
                const subMenuHeight = $dropdownMenuSub.height();
                const subMenuY = $dropdownItem[0].getBoundingClientRect().y;
                const windowHeight = $(window).height();

                const top = (subMenuY + subMenuHeight > windowHeight)
                    ? `-${subMenuHeight / 2}px`
                    : '0';

                $dropdownMenuSub.css('top', top);
                $dropdownMenuSub.addClass(classShow)
            }
        }
    });
});
