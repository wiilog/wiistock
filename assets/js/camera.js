import CameraPhoto, {FACING_MODES} from "jslib-html5-camera-photo";
import Flash, {ERROR} from "@app/flash";

export default class Camera {
    cameraPhoto;
    base64Image;

    static ENVIRONMENT_MODE = FACING_MODES.ENVIRONMENT;
    static USER_MODE = FACING_MODES.USER;
    static DEFAULT_RESOLUTION = {width: 640, height: 480};

    static init($filesInput) {
        this.enumerate().then((videoDevices) => {
            if (videoDevices.length > 0) {
                this.proceed($filesInput);
            } else {
                Flash.add(ERROR, `Votre système ne dispose pas d'entrée vidéo.`);
            }
        });
    }

    static proceed($filesInput) {
        const $takePictureModal = $(`#take-picture-modal`);
        const $takePictureButton = $takePictureModal.find(`.take-picture-button`);
        const $retryPictureButton = $takePictureModal.find(`.retry-picture-button`);
        const $imagePreview = $takePictureModal.find(`.image-preview`);

        const camera = new Camera();
        camera.cameraPhoto = new CameraPhoto($takePictureModal.find(`.camera-frame`)[0]);

        camera.start().then(() => {
            $takePictureModal.modal(`show`);

            $takePictureButton
                .off(`click`)
                .on(`click`, function () {
                    camera.base64Image = camera.cameraPhoto.getDataUri({});
                    camera.stop().then(() => {
                        $imagePreview.css(`background-image`, `url(${camera.base64Image})`);
                        $takePictureButton.addClass(`d-none`);
                        $retryPictureButton.removeClass(`d-none`);
                        $takePictureModal.find(`[type=submit]`).prop(`disabled`, false);
                    });
                });

            $retryPictureButton
                .off(`click`)
                .on(`click`, function () {
                    camera.start({}).then(() => {
                        $imagePreview.css(`background-image`, ``);

                        $retryPictureButton.addClass(`d-none`);
                        $takePictureButton.removeClass(`d-none`);
                        $takePictureModal.find(`[type=submit]`).prop(`disabled`, true);
                    });
                });

            $takePictureModal
                .off(`click.takePicture`)
                .on(`click.takePicture`, `[type=submit]:not([disabled])`, function () {
                    const date = moment().format(`DD-MM-YYYY-H-m-ss`);
                    const filename = `photo_${date}.png`;
                    const newFile = dataURLtoFile(camera.base64Image, filename);

                    saveInputFiles($filesInput, {files: [newFile]});

                    $takePictureModal.modal(`hide`);
                });

            $takePictureModal
                .on(`hidden.bs.modal`, function () {
                    $retryPictureButton.addClass(`d-none`);
                    $takePictureButton.removeClass(`d-none`);
                    $imagePreview.css(`background-image`, ``);
                    camera.stop();
                });
        });
    }

    /**
     * Start camera with parameters
     * @param {{
     *      device: string|undefined,
     *      resolution: {
     *          width: string,
     *          height: string
     *      }|undefined
     * }|{}} options
     */
    start(options = {}) {
        const device = options.device || Camera.USER_MODE;
        const resolution = options.resolution || Camera.DEFAULT_RESOLUTION;

        return this.cameraPhoto.startCamera(device, resolution)
            .catch((e) => {
                Flash.add(ERROR, `Vous n'avez pas autorisé l'application à utiliser la caméra.`);
                throw e;
            });
    }

    stop() {
        return this.cameraPhoto.stopCamera()
            .catch(() => {});
    }

    static async enumerate() {
        return navigator.mediaDevices.enumerateDevices()
            .then((cameras) => {
                return cameras.filter(({kind}) => kind === `videoinput`);
            });
    }
}
