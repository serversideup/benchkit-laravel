import { jsonHeaders } from '@/http';

// Endpoints for the run in progress. A run is owned by a detached process
// on the server, so these are how every client — this tab, another tab,
// another machine — starts it, follows it, and stops it.

const json = async (response) => {
    const data = await response.json().catch(() => ({}));

    if( !response.ok ) {
        const error = new Error(data.message ?? `The request failed (HTTP ${response.status}).`);
        error.status = response.status;
        error.data = data;

        throw error;
    }

    return data;
};

export const startRun = (settings, preset, hostDetails) => fetch('/run', {
    method: 'POST',
    headers: jsonHeaders(),
    body: JSON.stringify({ settings, preset, host_details: hostDetails ?? {} }),
}).then(json);

/**
 * The run's state plus every console event written since `offset`. Offsets
 * are byte positions in the run's log, so a client that has been away —
 * a reload, a tab opened halfway through — resumes exactly where it left
 * off, and asking for 0 replays the run from the beginning.
 */
export const fetchRunLog = (offset = 0) => fetch(`/run/log?offset=${offset}`, {
    headers: jsonHeaders(),
}).then(json);

export const cancelRun = () => fetch('/run/cancel', {
    method: 'POST',
    headers: jsonHeaders(),
}).then(json);

export const saveRun = () => fetch('/run/save', {
    method: 'POST',
    headers: jsonHeaders(),
}).then(json);

export const dismissRun = () => fetch('/run', {
    method: 'DELETE',
    headers: jsonHeaders(),
}).then(json);
