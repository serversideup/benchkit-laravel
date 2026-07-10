import { computed, ref, watch } from 'vue';

/**
 * Share flow for a results image. X web intents cannot attach media, so the
 * image is copied to the clipboard and the user pastes it into the composer —
 * the modal makes that two-step flow visible.
 *
 * Generic factory: `image` is a ref holding a PNG data URL, `shareText` a ref
 * (or computed) holding the post text. Callers own how both are produced.
 */
export function useShareResults({ image, shareText }) {
    const copyState = ref('idle');

    watch(image, (dataUrl) => {
        if( !dataUrl ) {
            copyState.value = 'idle';
        }
    });

    const intentUrl = computed(() => `https://x.com/intent/tweet?text=${encodeURIComponent(shareText.value)}`);

    // The clipboard API only exists in secure contexts (https or localhost),
    // and execCommand cannot copy images — the modal's failed state offers a
    // download instead. Safari only allows the write while the user gesture is
    // active, so ClipboardItem must receive the blob *promise* (not an awaited
    // blob) and this must be called synchronously from the click handler.
    const writeImageToClipboard = () => {
        if( !navigator.clipboard || typeof ClipboardItem === 'undefined' ) {
            return Promise.reject(new Error('Clipboard unavailable'));
        }

        const blob = fetch(image.value).then((response) => response.blob());

        return navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
    };

    // Also called when "Open X & paste" is clicked: the clipboard only holds
    // one item, so anything the user copied since the modal opened would have
    // replaced the image — re-copying at the last moment keeps it intact
    const recopyImage = () => {
        copyState.value = 'copying';

        writeImageToClipboard()
            .then(() => copyState.value = 'copied')
            .catch(() => copyState.value = 'failed');
    };

    return {
        copyState,
        intentUrl,
        recopyImage,
    };
}
