import {createPopper} from '@popperjs/core';

export default class WysiwygManager {

    static QUILL_CONFIG = {
        modules: {
            toolbar: [
                [{header: [1, 2, 3, false]}],
                ['bold', 'italic', 'underline', 'image'],
                [{'list': 'ordered'}, {'list': 'bullet'}],
            ],
        },
        formats: [
            'header',
            'bold', 'italic', 'underline', 'strike', 'blockquote',
            'list', 'bullet', 'indent', 'link', 'image',
        ],
        theme: 'snow',
    };

    static initializeWYSIWYG(container) {
        const initializer = function() {
            if(!$(this).is(`.ql-container`) && !$(this).is(`.wii-one-line-wysiwyg`)) {
                new Quill(this, WysiwygManager.QUILL_CONFIG);
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
            if(!alreadyInit) {
                const $dropdownButton = $('<button/>', {
                    class: `dropdown-button`,
                    html: `<i class="wii-icon wii-icon-wysiwyg-edit"></i>`,
                    tabindex: `-1`,
                    click: function() {
                        toggleWysiwygPopover($(this).parent());
                    },
                });

                $element
                    .attr(`contenteditable`, 'true')
                    .wrap(`<div class="${wrapperClass}"/>`);

                const $wrapper = $element.parent();
                const focusedClass = 'focused';

                $element
                    .on('focus', function() {
                        $wrapper.addClass(focusedClass);
                    })
                    .on('focusout', function() {
                        $wrapper.removeClass(focusedClass);
                    })
                    .on(`keypress`, function(event) {
                        if(event.key === `Enter`) {
                            event.preventDefault();
                            event.stopPropagation();

                            toggleWysiwygPopover($wrapper);
                        }
                    });

                $wrapper.append($dropdownButton);
            }
        };

        $container.find(oneLineWysiwygSelector).each(function() {
            initializer($(this));
        });

        $container.arrive(oneLineWysiwygSelector, function() {
            initializer($(this));
        });
    }

}

function toggleWysiwygPopover($wysiwyg) {
    const $input = $wysiwyg.find('.wii-one-line-wysiwyg');
    let popoverId = $wysiwyg.data('popover');

    const closePopover = ($popover) => {
        const $wrapper = $(`[data-popover="${$popover.attr(`id`)}"]`);
        const $input = $wrapper.find(`.wii-one-line-wysiwyg`);

        $input.attr(`contenteditable`, true);
        $wrapper.removeClass('active');
        $popover.remove();

        $input.trigger(`focusout`);
    };

    if(!popoverId) {
        do {
            popoverId = `wii-one-line-wysiwyg-popover-${Math.floor(Math.random() * 10000)}`;
        }
        while($(`.wii-one-line-wysiwyg-wrapper[data-popover="${popoverId}"]`).exists());
        $wysiwyg
            .data('popover', popoverId)
            .attr('data-popover', popoverId);
    }

    $(`.wii-one-line-wysiwyg-wrapper`).removeClass(`active`);

    const $popover = $(`#${popoverId}`);
    if($popover.exists()) {
        closePopover($popover);
    } else {
        $('.wii-one-line-wysiwyg-popover').each(function() {
            closePopover($(this));
        });

        $wysiwyg.addClass('active');

        const $textarea = $(`<div/>`).html($input.html());

        const $newPopover = $('<div/>', {
            id: popoverId,
            class: 'wii-one-line-wysiwyg-popover',
            html: $textarea,
        });


        $textarea.find('.ql-editor').trigger('focus');
        $input.removeAttr('contenteditable');

        const $body = $(`body`);
        $body.append($newPopover);

        createPopper($wysiwyg[0], $newPopover[0], {
            placement: `bottom-start`,
        });

        const quill = new Quill($textarea[0], {
            modules: {
                toolbar: [
                    [{header: [1, 2, 3, false]}],
                    ['bold', 'italic', 'underline'],
                    [{'list': 'ordered'}, {'list': 'bullet'}],
                ],
            },
            theme: 'snow',
        });

        $newPopover.append($('<button/>', {
            class: "validate-button btn btn-primary p-1",
            html: '<i class="wii-icon wii-icon-check"/>',
            click: () => {
                $input.html(quill.root.innerHTML);
                closePopover($newPopover);

                focusEditable($input);
            },
        }));

        focusEditable($newPopover.find(`.ql-editor`));

        const bodyClickEvent = 'click.oneLineWysiwyg';
        $body.off(bodyClickEvent)
            .on(bodyClickEvent, function(event) {
                const $elementClicked = $(event.target);
                const selector = `#${popoverId}, [data-popover="${popoverId}"]`;

                if(!$elementClicked.is(selector) && !$elementClicked.closest(selector).exists()) {
                    closePopover($newPopover);
                    $body.off(bodyClickEvent);
                }
            });
    }
}

function focusEditable(contentEditableElement) {
    if(contentEditableElement instanceof jQuery) {
        contentEditableElement = contentEditableElement[0];
    }

    const range = document.createRange();//Create a range (a range is a like the selection but invisible)
    range.selectNodeContents(contentEditableElement);//Select the entire contents of the element with the range
    range.collapse(false);//collapse the range to the end point. false means collapse to end rather than the start

    const selection = window.getSelection();//get the selection object (allows you to change selection)
    selection.removeAllRanges();//remove any selections already made
    selection.addRange(range);//make the range you have just created the visible selection
}
