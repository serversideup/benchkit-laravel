// Submitting a run to the community gallery.
//
// The document that leaves the machine — the allow-list deciding what is and
// isn't published — is built on the server (App\Actions\Runs\
// BuildSubmissionDocument) and packed into a single token, so this file only
// moves it. That split is deliberate: the allow-list is the thing standing
// between someone's public IP and a public repo, and it belongs where the test
// suite can hold it.
//
// A bot (.github/workflows/action_process-result-submission.yml) reads the
// token from the issue, records the issue author as the submitter, validates
// it, opens the PR, and closes the issue. The submitter never forks, edits a
// file, or types their username — GitHub already knows who they are.

import { jsonHeaders } from '@/http';

/**
 * Everything needed to submit one run: the public document (for the review
 * step), the token that carries it, and the GitHub URL to open.
 */
export const fetchSubmission = async (runId) => {
    const response = await fetch(`/runs/${runId}/submission`, {
        headers: { Accept: 'application/json' },
    });

    if( !response.ok ) {
        throw new Error(`Preparing the submission failed (HTTP ${response.status}).`);
    }

    return response.json();
};

/**
 * Put the token on the clipboard so it can be pasted if the pre-filled issue
 * doesn't carry it, or if the popup is blocked.
 *
 * navigator.clipboard only exists in a secure context, and BenchKit is often
 * reached over plain HTTP on a disposable box — hence the execCommand path,
 * which has no such requirement. Must be called synchronously from the click
 * handler: both APIs want a live user gesture.
 */
export const copyToken = (token) => {
    if( navigator.clipboard?.writeText ) {
        return navigator.clipboard.writeText(token).then(() => true).catch(() => legacyCopy(token));
    }

    return Promise.resolve(legacyCopy(token));
};

const legacyCopy = (text) => {
    try {
        const field = document.createElement('textarea');

        field.value = text;
        field.setAttribute('readonly', '');
        // Off-screen rather than hidden: a display:none field can't be selected.
        field.style.cssText = 'position:fixed;top:0;left:-9999px;opacity:0;';

        document.body.appendChild(field);
        field.select();

        const copied = document.execCommand('copy');

        document.body.removeChild(field);

        return copied;
    } catch {
        return false;
    }
};

export const openIssue = (url) => window.open(url, '_blank', 'noopener');

/**
 * Remember that a submission was opened for this run, so returning to it warns
 * instead of sending the submitter to GitHub to be told the bot already has it.
 *
 * Fire and forget: the popup must open in the same gesture, so nothing here may
 * be awaited, and a failed write costs a warning rather than a submission.
 */
export const markSubmitted = (runId) => {
    fetch(`/runs/${runId}/submission`, { method: 'POST', headers: jsonHeaders() }).catch(() => {});
};
