import JSZip from 'jszip';
import { router } from '@inertiajs/vue3';

const xsrfToken = () => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const jsonHeaders = () => ({
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-XSRF-TOKEN': xsrfToken(),
});

export const updateRunMeta = async (id, attributes) => {
    const response = await fetch(`/runs/${id}`, {
        method: 'PATCH',
        headers: jsonHeaders(),
        body: JSON.stringify(attributes),
    });

    if( !response.ok ) {
        throw new Error(`Updating the run failed (HTTP ${response.status}).`);
    }

    return (await response.json()).run;
};

export const deleteRun = async (id) => {
    const response = await fetch(`/runs/${id}`, {
        method: 'DELETE',
        headers: jsonHeaders(),
    });

    if( !response.ok ) {
        throw new Error(`Deleting the run failed (HTTP ${response.status}).`);
    }
};

export const deleteRunAndRefresh = async (id) => {
    await deleteRun(id);
    router.reload();
};

const fileTimestamp = (isoString) => {
    const date = new Date(isoString);

    return `${date.getFullYear()}-${date.getMonth() + 1}-${date.getDate()}-${date.getHours()}${date.getMinutes()}${date.getSeconds()}`;
};

const buildLogText = (run) => {
    const sections = [
        { key: 'yabs', title: 'HARDWARE TESTS' },
        { key: 'cfspeedtest', title: 'NETWORK TESTS' },
        { key: 'http', title: 'WEB SERVER TESTS' },
        { key: 'php', title: 'PHP TESTS' },
    ];

    let text = '';

    sections.forEach(({ key, title }) => {
        if( run.logs?.[key]?.length ) {
            text += '################################################################################\n';
            text += `# ${title}\n`;
            text += '################################################################################\n';
            text += run.logs[key].join('\n');
            text += '\n';
        }
    });

    return text;
};

const downloadBlob = (blob, filename) => {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
};

// The snapshot is self-contained, so the zip works for historical runs too —
// no dependency on in-memory queue state
export const downloadRunResults = async (run, imageDataUrl = null) => {
    const zip = new JSZip();
    const stamp = fileTimestamp(run.created_at);

    zip.file(`benchkit-results-${stamp}.txt`, buildLogText(run));
    zip.file(`benchkit-results-${stamp}.json`, JSON.stringify(run, null, 2));

    if( imageDataUrl ) {
        zip.file(`benchkit-results-${stamp}.png`, await (await fetch(imageDataUrl)).blob());
    }

    downloadBlob(await zip.generateAsync({ type: 'blob' }), `benchkit-results-${stamp}.zip`);
};
