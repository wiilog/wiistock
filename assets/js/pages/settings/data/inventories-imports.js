window.importInventoryFile = importInventoryFile;

function importInventoryFile() {
    let importExcel = $('#importExcel')[0];
    let formData = new FormData();
    let files = importExcel.files;
    let fileToSend = files[0];
    let fileName = importExcel.files[0]['name'];
    let extension = fileName.split('.').pop();
    if (extension === "csv") {
        formData.append('file', fileToSend);
        $.ajax({
            url: Routing.generate('update_category', true),
            data: formData,
            type: "post",
            contentType: false,
            processData: false,
            cache: false,
            dataType: "json",
            success: function (data) {
                if (data.success === true) {
                    showBSAlert('Les catégories ont bien été modifiées.', 'success');
                } else if (data.success === false) {
                    let url = window.location.protocol+'//'+window.location.host+'/uploads/log/'+data.nameFile;
                    let link = document.createElement("a");
                    link.setAttribute("href", url);
                    link.setAttribute("download", 'log_error.txt');
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    showBSAlert("Le fichier ne s'est pas importé correctement. Veuillez ouvrir le fichier ('log-error.txt') qui vient de se télécharger.", 'danger');
                }
            }
        });
    }
}
