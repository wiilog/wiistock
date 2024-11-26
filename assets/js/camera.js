import CameraPhoto, {FACING_MODES} from "jslib-html5-camera-photo";
import Flash, {ERROR} from "@app/flash";
import {tooltipConfig} from "@app/tooltips";
import {dataURLtoFile} from "@app/utils";
import moment from "moment";

export default class Camera {
    cameraPhoto;
    base64Image;

    static USER_MODE = FACING_MODES.USER;
    static DEFAULT_RESOLUTION = {width: 640, height: 480};
    static VIDEO_INPUT_TYPE = `videoinput`;

    static async init($openModalButton, $inputFile) {
        const videoDevices = await enumerateVideoDevices();

        const videoDevicesLength = videoDevices.length;
        if (videoDevicesLength > 0) {
            const $takePictureModal = $(`#take-picture-modal`);
            const $videoDevice = $takePictureModal.find(`[name=videoDevice]`);

            const camera = new Camera();
            camera.cameraPhoto = new CameraPhoto($takePictureModal.find(`.camera-frame`)[0]);

            $videoDevice
                .empty()
                .append(
                    ...videoDevices.map(({deviceId, label}) => (
                        $('<option/>', {
                            value: deviceId,
                            text: label
                        })
                    ))
                );

            if(camera) {
                $openModalButton
                    .off(`click.cameraOpenModal`)
                    .on(`click.cameraOpenModal`, function () {
                        wrapLoadingOnActionButton($(this), () => camera.open($inputFile));
                    });
            }

            if (videoDevicesLength === 1) {
                $videoDevice.prop(`disabled`, videoDevicesLength === 1);
            }
            else { // if(videoDevicesLength > 1)
                $videoDevice
                    .off(`change.cameraOpenModal`)
                    .on(`change.cameraOpenModal`, function () {
                        const deviceId = $(this).val();

                        stopCamera(camera).then(() => {
                            wrapLoadingOnActionButton($takePictureModal.find(`.modal-body`), () => (
                                startCamera(camera, {device: deviceId}
                            )))
                        });
                    });
            }

            $videoDevice
                .val(videoDevices[0].deviceId)
                .trigger('change');

            return camera;
        } else {
            $openModalButton
                .prop(`disabled`, true)
                .attr(`title`, `Votre système ne dispose pas d'entrée vidéo.`)
                .tooltip(tooltipConfig);
            return undefined;
        }
    }

    async open($filesInput) {
        const $takePictureModal = $(`#take-picture-modal`);
        const $takePictureButton = $takePictureModal.find(`.take-picture-button`);
        const $retryPictureButton = $takePictureModal.find(`.retry-picture-button`);
        const $imagePreview = $takePictureModal.find(`.image-preview`);

        const camera = this;

        return startCamera(camera).then(() => {
            $takePictureModal.modal(`show`);

            $takePictureButton
                .off(`click.takePictureButton`)
                .on(`click.takePictureButton`, function () {
                    camera.base64Image = camera.cameraPhoto.getDataUri({});

                    stopCamera(camera).then(() => {
                        $imagePreview.css(`background-image`, `url(${camera.base64Image})`);
                        $takePictureButton.addClass(`d-none`);
                        $retryPictureButton.removeClass(`d-none`);
                        $takePictureModal.find(`[type=submit]`).prop(`disabled`, false);
                    });
                });

            $retryPictureButton
                .off(`click.retryPictureButton`)
                .on(`click.retryPictureButton`, function () {
                    wrapLoadingOnActionButton($imagePreview, () => (
                        startCamera(camera).then(() => {
                            $imagePreview.css(`background-image`, ``);

                            $retryPictureButton.addClass(`d-none`);
                            $takePictureButton.removeClass(`d-none`);
                            $takePictureModal.find(`[type=submit]`).prop(`disabled`, true);
                        })
                    ));
                });

            $takePictureModal
                .off(`click.takePictureModalSubmit`)
                .on(`click.takePictureModalSubmit`, `[type=submit]:not([disabled])`, function () {
                    const date = moment().format(`DD-MM-YYYY-H-m-ss`);
                    const filename = `photo_${date}.png`;
                    const newFile = dataURLtoFile(camera.base64Image, filename);

                    saveInputFiles($filesInput, {files: [newFile]});

                    $takePictureModal.modal(`hide`);
                });

            $takePictureModal
                .off(`hidden.bs.modal`)
                .on(`hidden.bs.modal`, function () {
                    $retryPictureButton.addClass(`d-none`);
                    $takePictureButton.removeClass(`d-none`);
                    $imagePreview.css(`background-image`, ``);
                    stopCamera(camera);
                });
        });
    }
}

/**
 * Start camera with parameters
 * @param {Camera} camera
 * @param {{
 *      device: string|undefined,
 *      resolution: {
 *          width: string,
 *          height: string
 *      }|undefined
 * }|{}} options
 */
function startCamera(camera, options = {}) {
    const device = options.device || Camera.USER_MODE;
    const resolution = options.resolution || Camera.DEFAULT_RESOLUTION;

    return camera.cameraPhoto.startCamera(device, resolution)
        .catch((e) => {
            Flash.add(ERROR, `Vous n'avez pas autorisé l'application à utiliser la caméra.`);
            throw e;
        });
}

/**
 * Start camera with parameters
 * @param {Camera} camera
 */
function stopCamera(camera) {
    return camera.cameraPhoto.stopCamera()
        .catch(() => {});
}

async function enumerateVideoDevices() {
    if (navigator.mediaDevices) {
        const enumerateDevices = await navigator.mediaDevices.enumerateDevices();
        return enumerateDevices.filter(({kind}) => kind === Camera.VIDEO_INPUT_TYPE);
    }
    return [];
}
