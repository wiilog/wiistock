$(function () {
    $(document).on('change', `input[name='uploadAndScan']`, function () {
        scanDeliveryNoteFile($(this));
    })
})

function scanDeliveryNoteFile($input) {
    let files = $input[0].files;
    let file = files[0];
    let formData = new FormData();
    const displayScannedDeliveryNote = parseInt($('#displayScannedDeliveryNote').val());
    formData.append("file", file, file.name);
    //TO DO AJAX.route
    $.ajax({
        url: Routing.generate('api_delivery_note_file', true),
        data: formData,
        type: AJAX.POST,
    // AJAX.route(AJAX.POST, `api_delivery_note_file`, {
        // file: formData,
        // formData,
        // data: formData,
        contentType: false,
        processData: false,
        dataType: 'json',
    // })
        success: function (data) {

            let fields = data.values;
            for (const field in fields) {
                let $select = $(`[name=${field}]`);
                let score = fields[field].score;
                if ($select.prop('multiple')) {
                    $select.empty();
                    score = 0;
                    const options = Array.isArray(fields[field]) ? fields[field] : [fields[field]];
                    options.forEach((optionValue) => {
                        let option = new Option(optionValue.value, optionValue.id ? optionValue.id : optionValue.value, true, true);
                        score += optionValue.score;
                        $select.append(option);
                    })
                    score /= options.length;
                } else if ($select.next().hasClass('ql-toolbar')) {
                    $select.parent().find('.ql-editor').find('p').html(fields[field].value);
                } else {
                    $select.val(fields[field].value);
                }
                $select.trigger('change');

                if (score) {
                    let $labelScore = $select.parent().find(".ai-score-text");
                    $labelScore.html(score * 100 + '%');
                    let $coloredLabel = $select.next().hasClass('ql-toolbar')
                        ? $select.next()
                        : $select.next().find('.select2-selection');
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
        },
        error: () => {
            Flash.add('danger', 'Une erreur est survenue lors de l\'import et/ou traitement du fichier');
        },
    // });
    });
}
