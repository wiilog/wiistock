import $ from "jquery";

export const INFO = `info`;
export const SUCCESS = `success`;
export const ERROR = `danger`;

export const LABELS = {
    [INFO]: `Information`,
    [SUCCESS]: `Succès`,
    [ERROR]: `Erreur`,
};

export default class Flash {

    static serverError(error = null, unique = false) {
        if(error) {
            console.error(`%cServer error: %c${error}`, ...[
                `font-weight: bold;`,
                `font-weight: normal;`,
            ]);
        }

        Flash.add(ERROR, `Une erreur est survenue lors du traitement de votre requête par le serveur`, true, unique);
    }

    static add(type, message, remove = true, unique = false) {
        const $alertContainer = $('#alerts-container');
        if(unique) {
            Flash.clear();
        }

        const $alert = $('#alert-template')
            .clone()
            .removeAttr('id')
            .addClass(`wii-alert-${type}`)
            .removeClass('d-none');

        $alert
            .find('.content')
            .html(message);

        $alert
            .find('.alert-content')
            .find('.type')
            .html('<strong>' + Translation.of(`Général`, '', `Zone liste`, LABELS[type]) + '</strong>');

        $alertContainer.append($alert);

        if (remove) {
            $alert.delay(5500).fadeOut(500);

            setTimeout(() => {
                if ($alert.parent().length) {
                    $alert.remove();
                }
            }, 6000);
        }
    }

    static clear() {
        $('#alerts-container').empty();
    }
}

global.Flash = Flash;
