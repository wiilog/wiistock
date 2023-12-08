import AJAX, {POST} from "@app/ajax";

$(function () {
    $(document)
        .on('change', `input[name='uploadAndScan']`, function () {
            scanDeliveryNoteFile($(this));
        })
        .on('change', '.ai-field', function () {
            const $field = $(this);
            $field.parent().find('.score-low, .score-medium, .score-high').removeClass('score-low score-medium score-high');
            let aiScoreText = $(this).prev().hasClass('ai-score-text') ? $(this).prev() : $(this).prev().find('.ai-score-text');
            aiScoreText.html('');
        })
        .on('click', '.ql-editor', function () {
            $(this).parent().prev().removeClass('score-low score-medium score-high');
            $(this).parent().parent().find('.ai-score-text').html('');
        })
})

function scanDeliveryNoteFile($input) {
    const $modal = $input.closest('.modal');
    const $aiFillableFieldsContainer = $.merge($modal.find('.ai-fillable-fields-container'), $input.parent().find('button'));
    $aiFillableFieldsContainer.each(function () {
        $(this).pushLoader();
    });

    let files = $input[0].files;
    let file = files[0];
    let formData = new FormData();
    const displayScannedDeliveryNote = parseInt($('#displayScannedDeliveryNote').val());
    formData.append("file", file, file.name);

    AJAX
        .route(POST, `api_delivery_note_file`, {}).json(formData).then(({success, data}) => {
            if(success) {
                let fields = data.values;
                for (const [fieldName, fieldData] of Object.entries(fields)) {
                    const $field = $(`[name=${fieldName}]`);
                    $field.addClass('ai-field');
                    const score = fieldData.score;
                    $field.empty();
                    for (const [index, valueData] of Object.entries(fieldData.values)) {
                        if ($field.hasClass('select2-hidden-accessible')) {
                            let option = new Option(valueData.label, valueData.value || valueData.label, true, true);
                            $field.append(option);
                        } else if ($field.next().hasClass('ql-toolbar')) {

                            $field.parent().find('.ql-editor').append(`<p>${valueData.value}</p>`);
                        } else {
                            $field.val(valueData.value);
                        }
                        $field.trigger('change');
                        if (score) {
                            let $labelScore = $field.parent().find(".ai-score-text")
                            if ($labelScore.length === 0) {
                                $labelScore = $field.parent().find("label").after('<span class="wii-small-text ai-score-text ml-2"></span>').next();
                            }
                            $labelScore.html((score) + '%');
                            let $coloredLabel = $field.next().hasClass('ql-toolbar')
                                ? $field.next()
                                : $field.next().find('.select2-selection');
                            let colorClass = '';
                            switch (true) {
                                case score >= 90:
                                    colorClass = 'score-high';
                                    break;
                                case score < 90 && score > 60:
                                    colorClass = 'score-medium';
                                    break;
                                case score >= 0 && score <= 60:
                                    colorClass = 'score-low';
                                    break;
                                default:
                                    break;
                            }
                            $coloredLabel.addClass(colorClass);
                        }
                    }
                }
                if (displayScannedDeliveryNote !== 0) {
                    window.open(`/uploads/attachments/${data.file.name}`, '_blank');
                }
            }
            $aiFillableFieldsContainer.each(function () {
                $(this).popLoader();
            });
        })

}
