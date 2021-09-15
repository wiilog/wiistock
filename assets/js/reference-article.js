import '../scss/article_reference.scss';
import {initEditor} from './utils';

$(document).ready(() => {
    initEditor(`.editor-container`);

    $(`.add-supplier-article`).click(function() {
        console.log("ok", $(this).siblings(`.supplier-articles`))
        $(this).siblings(`.supplier-articles`).append($(`#supplier-article-template`).html());
    });

    $(document).on(`click`, `.delete-supplier-article`, function() {
        $(this).closest(`.ligneFournisseurArticle`).remove();
    });

    $(`.save`).click(function() {
        const $button = $(this);
        processSubmitAction($(`.ra-form`), $button, $button.data(`submit`), {
            onSuccess: () => window.location.href = Routing.generate('reference_article_index')
        });
    });
});
