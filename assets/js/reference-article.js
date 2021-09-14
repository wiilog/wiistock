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
        processSubmitAction($(`.ra-form`), $(this), Routing.generate('reference_article_new', true), {
            onSuccess: () => window.location.href = Routing.generate('reference_article_index')
        });
    });
});
