import AJAX, {GET, POST, DELETE} from '@app/ajax';
import Form from '@app/form';
import {getUserFiltersByPage} from '@app/utils';
import Flash, {ERROR} from "@app/flash";
import {initDataTable} from "@app/datatable";

global.getQuantityRefArticle = getQuantityRefArticle;

let $modalNewMvtStock = $('#modalNewMvtStock');
let tableMvt = null;

$(function() {
    initDateTimePicker();
    $('.select2').select2();
    AJAX.route(
        GET,
        "mouvement_stock_api_columns",
        {}
    )
        .json()
        .then((columns) => {
            let pathMvt = Routing.generate('mouvement_stock_api', true);
            let tableMvtStockConfig = {
                responsive: true,
                serverSide: true,
                processing: true,
                order: [['date', "desc"]],
                ajax: {
                    "url": pathMvt,
                    "type": POST
                },
                drawConfig: {
                    needsSearchOverride: true,
                    needsColumnHide: true,
                },
                hideColumnConfig: {
                    columns,
                    tableFilter: 'tableMvts'
                },
                columns: columns,
            };
            tableMvt = initDataTable('tableMvts', tableMvtStockConfig);
        })

    Form
        .create('#modalNewMvtStock',{resetView: ['open', 'close']})
        .submitTo(POST, "mvt_stock_new", {tables: () => {return tableMvt}})
        .on('change', '[name="reference-new-mvt"]', (event) => {
            resetNewModal($modalNewMvtStock);
            newMvtStockArticleChosen($(event.target));
        })
        .on('change', '[name="chosen-type-mvt"]', (event) => {
            newMvtStockTypeChanged($(event.target));
        })
        .on('change', '[name="chosen-art-barcode"]', (event) => {
            showFieldsAndFillOnArticleChange($(event.target));
        })
        .onOpen(() => {
            resetNewModal($modalNewMvtStock);
        });

    // filtres enregistrés en base pour chaque utilisateur
    getUserFiltersByPage(PAGE_MVT_STOCK);

});

$(document).on('click', '.deleteMvtStock', function (event) {
    deleteMvtStock($(event.target).data().id);
});

function newMvtStockArticleChosen($select) {
    newMvtStockReferenceChosen($select);
    const $artMvt = $('[name="chosen-art-barcode"]');
    const $refMvt = $('[name="chosen-ref-barcode"]');
    const $location = $('[name="chosen-ref-location"]');
    const $quantity = $('[name="chosen-ref-quantity"]');
    const $type = $('[name="chosen-type-mvt"]');
    const $locationTo = $('[name="chosen-mvt-location"]');
    const $quantityEntranceOut = $('[name="chosen-mvt-quantity"]');

    const selectedArticles = $select.select2('data');

    $artMvt.text("");
    $quantityEntranceOut.val("");
    $locationTo.text("");
    $type.val("")

    if (selectedArticles.length > 0) {
        const selectedArticle = selectedArticles[0];
        const typeQuantity = selectedArticle.typeQuantite;
        const $fieldToHide = typeQuantity === 'article' ? $refMvt : $artMvt;
        const $fieldToShow = typeQuantity === 'article' ? $artMvt : $refMvt;
        const elements = [$location, $quantity, $type, $locationTo, $quantityEntranceOut];

        $fieldToShow.addClass('needed').addClass('data');
        $fieldToShow.parent().parent().removeClass('d-none');
        $fieldToHide.parent().parent().addClass('d-none');
        elements.forEach((element) => {
            element.trigger('change').parent().parent().removeClass('needed').addClass('d-none');
        })

        if(typeQuantity === 'reference') {
            $location.parent().parent().addClass('needed').removeClass('d-none');
            $quantity.parent().parent().addClass('needed').removeClass('d-none');
            $type.trigger('change').parent().parent().addClass('needed').removeClass('d-none');
            $artMvt.removeClass('needed');
        }
    }
}

function showFieldsAndFillOnArticleChange($select) {
    let currentArticle = $select.select2('data')[0];
    const $elements = $('[name="chosen-ref-location"], [name="chosen-ref-quantity"], [name="chosen-type-mvt"]');
    const $barcodeInput = $('input[name="chosen-ref-barcode"]');

    if (currentArticle) {
        $elements.parent().parent().addClass('needed').removeClass('d-none');

        const $articleLocation = $modalNewMvtStock.find('[name="chosen-ref-location"]');
        const $articleQuantity = $modalNewMvtStock.find('[name="chosen-ref-quantity"]');

        $barcodeInput.val(currentArticle.text);
        $articleLocation.val(currentArticle.location);
        $articleQuantity.val(currentArticle.quantity);
    }
}

async function newMvtStockReferenceChosen($select) {
    let referenceArticle = $select.select2('data')[0];

    if (!referenceArticle) {
        return;
    }

    const $referenceLibelle = $modalNewMvtStock.find('[name="chosen-ref-label"]');
    const $referenceBarCode = $modalNewMvtStock.find('[name="chosen-ref-barcode"]');
    const $referenceLocation = $modalNewMvtStock.find('[name="chosen-ref-location"]');
    const $referenceQuantite = $modalNewMvtStock.find('[name="chosen-ref-quantity"]');
    const $typeMvt = $modalNewMvtStock.find('[name="chosen-type-mvt"]');

    $referenceLibelle.val(referenceArticle.label);
    $referenceBarCode.val(referenceArticle.barCode);
    $referenceLocation.val(referenceArticle.location);

    /* Make another request to get the quantity because the value dumped in the back is good but the value logged in the front is not good
     * Maybe it come from select2 cache ?
     * To prevent this, we make a request to get the quantity of the reference article
     */
    const refArticleQte = await getQuantityRefArticle(referenceArticle.id);
    $referenceQuantite.val(refArticleQte);

    $modalNewMvtStock.find('.is-hidden-by-ref').removeClass('d-none');
    $typeMvt.addClass('needed');
}

function resetNewModal($modal) {
    const $typeMvt = $modal.find('[name="chosen-type-mvt"]');
    $modal.find('.is-hidden-by-ref').addClass('d-none');
    $modal.find('.is-hidden-by-type').addClass('d-none');
    $modal.find('.chosen-art-barcode').parent().parent().addClass('d-none');
    $typeMvt.removeClass('needed');
}

function newMvtStockTypeChanged($select) {
    const $locationMvt = $('[name="chosen-mvt-location"]');
    const $quantityMvt = $('[name="chosen-mvt-quantity"]');

    const $selectOption = $select.find('option:selected');
    if ($selectOption.data('needs-location')) {
        Select2Old.location($locationMvt);
        $locationMvt.addClass('needed');
        $locationMvt.parents('.form-group').removeClass('d-none');
        $locationMvt.parents('.labelLocationMovement').removeClass('d-none');

        $quantityMvt.removeClass('needed');
        $quantityMvt.parents('.form-group').addClass('d-none');
    } else if ($selectOption.data('needs-quantity')) {
        $quantityMvt.addClass('needed');
        $quantityMvt.parents('.form-group').removeClass('d-none');
        $quantityMvt.parents('.labelQuantityMovement').removeClass('d-none');

        $locationMvt.removeClass('needed');
        $locationMvt.parents('.form-group').addClass('d-none');

        if ($selectOption.data('needs-quantity-cap')) {
            Select2Old.location($locationMvt);
            $quantityMvt.attr('max', $('[name="chosen-ref-quantity"]').val());
            $locationMvt.addClass('needed');
            $locationMvt.parents('.form-group').removeClass('d-none');
            $locationMvt.parents('.labelLocationMovement').removeClass('d-none');
        } else {
            $quantityMvt.removeAttr('max');
        }
    }
}

function deleteMvtStock(id) {
    if(id === undefined){
        Flash.add(ERROR, `Une erreur est survenue lors de la suppression de votre mouvement de stock. Veuillez recharger la page et réessayer.`);
    }
    Modal.confirm({
        ajax: {
            method: DELETE,
            route: `mvt_stock_delete`,
            params: {mvtStock: id},
        },
        message: `Voulez-vous réellement supprimer ce mouvement ?`,
        title: `Supprimer le mouvement`,
        validateButton: {
            color: `danger`,
            label: `Supprimer`,
        },
        table: tableMvt,
    })
}

function getQuantityRefArticle(refArticleId) {
    return AJAX
        .route(
            GET,
            "get_quantity_ref_article",
            {
                referenceArticle: refArticleId,
            }
        )
        .json()
}
