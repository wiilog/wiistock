import AJAX, {GET} from "@app/ajax";

export function initializeTransportRequest() {

    $('.button-launch-import')
        .off('click')
        .on('click', function () {
            wrapLoadingOnActionButton($(this), () => (
                AJAX.route(GET, 'transport_rounds_launch_ftp_export')
                    .json()
            ));
        });
}
