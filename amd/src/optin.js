import {call} from 'core/ajax';
import Notification from 'core/notification';

/** Initialise the GradebookXP radar-page switch. */
export const init = options => {
    const checkbox = document.getElementById('gradebookxp-cg-radar-optin');
    const status = document.getElementById('gradebookxp-cg-radar-status');
    if (!checkbox) {
        return;
    }
    checkbox.addEventListener('change', async() => {
        const requested = checkbox.checked;
        checkbox.disabled = true;
        status.textContent = requested ? 'Verbindung wird aktiviert …' : 'Verbindung wird deaktiviert …';
        try {
            const requests = call([{
                methodname: 'local_gradebookxp_cg_set_enabled',
                args: {courseid: Number(options.courseid), enabled: requested}
            }]);
            const response = await requests[0];
            checkbox.checked = Boolean(response.enabled);
            status.textContent = response.enabled ? 'Cockpit-Verbindung aktiviert.' : 'Cockpit-Verbindung deaktiviert.';
        } catch (error) {
            checkbox.checked = !requested;
            status.textContent = 'Die Einstellung konnte nicht gespeichert werden.';
            Notification.exception(error);
        } finally {
            checkbox.disabled = false;
        }
    });
};
