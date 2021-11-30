export default class WysiwygManager {

    static QUILL_CONFIG = {
        modules: {
            toolbar: [
                [{header: [1, 2, 3, false]}],
                ['bold', 'italic', 'underline', 'image'],
                [{'list': 'ordered'}, {'list': 'bullet'}]
            ]
        },
        formats: [
            'header',
            'bold', 'italic', 'underline', 'strike', 'blockquote',
            'list', 'bullet', 'indent', 'link', 'image'
        ],
        theme: 'snow'
    };

    static initializeWYSIWYG(container) {
        const initializer = function() {
            if(!$(this).is(`.ql-container`)) {
                new Quill(this, WysiwygManager.QUILL_CONFIG)
            }
        };

        container.find(`[data-wysiwyg]`).each(initializer);
        container.arrive(`[data-wysiwyg]`, initializer);
    }

    static initializeOneLineWYSIWYG($container) {
        const oneLineWysiwygSelector = '.wii-one-line-wysiwyg';

        const initializer = ($element) => {
            const wrapperClass = 'wii-one-line-wysiwyg-wrapper';
            const alreadyInit = $element.closest(`.${wrapperClass}`).exists();
            if (!alreadyInit) {
                const $dropdownButton = $('<button/>', {
                    class: 'dropdown-button',
                    html: '<i class="wii-icon wii-icon-wysiwyg-edit"></i>',
                    tabindex: '-1',
                    click: function() {
                        const $button = $(this);
                        const $wrapper = $button.parent();
                        $wrapper.toggleClass('active');
                        toggleWysiwygPopover($wrapper);
                    }
                });

                $element
                    .attr('contenteditable', 'true')
                    .addClass('data')
                    .attr('data-wysiwyg', 'comment')
                    .data('wysiwyg', 'comment')
                    .wrap(`<div class="${wrapperClass}"/>`);

                const $wrapper = $element.parent();
                const focusedClass = 'focused';

                $element
                    .on('focus', function () {
                        $wrapper.addClass(focusedClass);
                    })
                    .on('focusout', function () {
                        $wrapper.removeClass(focusedClass);
                    })
                    .on('keypress', function (event) {
                        if ((event.keypress || event.which) === 13) {
                            toggleWysiwygPopover($wrapper);
                            event.preventDefault();
                            return false;
                        }
                    });

                $wrapper
                    .append($dropdownButton);
            }
        };

        $container.find(oneLineWysiwygSelector).each(function() {
            initializer($(this));
        });

        $container.arrive(oneLineWysiwygSelector, function () {
            initializer($(this));
        });
    }

}

function toggleWysiwygPopover($wysiwyg) {
    const $input = $wysiwyg.find('.wii-one-line-wysiwyg');
    let popoverId = $wysiwyg.data('popover');

    const closePopover = () => {
        $input.attr('contenteditable', true);
        $wysiwyg.removeClass('active');
        $(`.wii-one-line-wysiwyg-popover`).remove();
    };

    if (!popoverId) {
        do {
            popoverId = `wii-one-line-wysiwyg-popover-${Math.floor(Math.random() * 10000)}`;
        }
        while ($(`.wii-one-line-wysiwyg-wrapper[data-popover="${popoverId}"]`).exists());
        $wysiwyg
            .data('popover', popoverId)
            .attr('data-popover', popoverId);
    }

    const $allPopovers = $('.wii-one-line-wysiwyg-popover');
    const $popover = $(`#${popoverId}`);

    if ($popover.exists()) {
        $allPopovers.remove();
    }
    else {
        $allPopovers.remove();

        const {x, y, height: inputHeight} = $wysiwyg[0].getBoundingClientRect()
        const xPopover = x;
        const yPopover = y + inputHeight;

        const $textarea = $('<div/>');

        const $newPopover = $('<div/>', {
            id: popoverId,
            class: 'wii-one-line-wysiwyg-popover',
            html: $textarea
        })
            .css({
                top: yPopover,
                left: xPopover
            });

        $textarea.html($input.html());

        let quill;

        const $validateButton = $('<button/>', {
            class: "validate-button btn btn-primary p-1",
            html: '<i class="wii-icon wii-icon-check"/>',
            click: () => {
                $input.html(quill.root.innerHTML);
                $input.trigger('focusout').trigger('focus');
                closePopover();
            }
        });
        $newPopover.append($validateButton);

        const $body = $('body');
        $body.append($newPopover);

        quill = new Quill($textarea[0], {
            modules: {
                toolbar: [
                    [{header: [1, 2, 3, false]}],
                    ['bold', 'italic', 'underline'],
                    [{'list': 'ordered'}, {'list': 'bullet'}]
                ]
            },
            theme: 'snow'
        });

        $textarea.find('.ql-editor').trigger('focus');
        $input.removeAttr('contenteditable');

        const popoverHeight = $newPopover.height();
        const bodyHeight = $body.height();

        // check if modal is outside body ?
        if ((yPopover + popoverHeight) > bodyHeight) {
            $newPopover.css('top', window.scrollY + yPopover - popoverHeight - inputHeight);
        }

        const bodyFocusoutEvent = 'focusout.oneLineWysiwyg';
        $body
            .off(bodyFocusoutEvent)
            .on(bodyFocusoutEvent, function (event) {
                const $elementFocusedOut = $(event.target);
                const $elementFocusedIn = event.relatedTarget ? $(event.relatedTarget) : null;
                if ($elementFocusedOut.hasClass('ql-editor')
                    && $elementFocusedOut.closest('.wii-one-line-wysiwyg-popover').exists()
                    && (!$elementFocusedIn || !$elementFocusedIn.closest('.wii-one-line-wysiwyg-popover').exists())) {
                    closePopover();
                    $body.off(bodyFocusoutEvent);
                }
            });
    }
}
