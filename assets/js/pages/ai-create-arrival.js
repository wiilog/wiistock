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
    $aiFillableFieldsContainer.pushLoader(`black`);

    let files = $input[0].files;
    let file = files[0];
    let formData = new FormData();
    const displayScannedDeliveryNote = parseInt($('#displayScannedDeliveryNote').val());
    formData.append("file", file, file.name);

    AJAX.route(POST, `api_delivery_note_file`, {}).json(formData).then((data) => {
        let fields = data.values;
        for (const field in fields) {
            const $field = $(`[name=${field}]`);
            $field.addClass('ai-field');
            let score = fields[field].score;

            if ($field.prop('multiple')) {
                $field.empty();
                score = 0;
                const options = Array.isArray(fields[field]) ? fields[field] : [fields[field]];
                options.forEach((optionValue) => {
                    let option = new Option(optionValue.value, optionValue.id ? optionValue.id : optionValue.value, true, true);
                    score += optionValue.score;
                    $field.append(option);
                })
                score /= options.length;
            } else if ($field.next().hasClass('ql-toolbar')) {
                $field.parent().find('.ql-editor').find('p').html(fields[field].value);
            } else {
                $field.val(fields[field].value);
            }

            $field.trigger('change');
            if (score) {
                let $labelScore = $field.parent().find(".ai-score-text")
                if ($labelScore.length === 0) {
                    $labelScore = $field.parent().find("label").after('<span class="wii-small-text ai-score-text ml-2"></span>').next();
                }

                $labelScore.html(score * 100 + '%');
                let $coloredLabel = $field.next().hasClass('ql-toolbar')
                    ? $field.next()
                    : $field.next().find('.select2-selection');
                switch (true) {
                    case score >= 0.90:
                        $coloredLabel.addClass('score-high');
                        break;
                    case score < 0.90 && score > 0.60:
                        $coloredLabel.addClass('score-medium');
                        break;
                    case score >= 0 && score <= 0.60:
                        $coloredLabel.addClass('score-low');
                        break;
                    default:
                        break;
                }
            }
        }
        if (displayScannedDeliveryNote !== 0) {
            window.open(`/uploads/attachments/${data.file.name}`, '_blank');
        }
        $aiFillableFieldsContainer.popLoader();
    });

}
