import { computed, ref, watch } from 'vue';

/**
 * Share flow for a results image. X web intents cannot attach media, so on
 * desktop the image is copied to the clipboard and the user pastes it into the
 * composer — the modal makes that two-step flow visible. On touch devices the
 * Web Share API attaches the image to the native share sheet directly.
 *
 * Generic factory: `image` is a ref holding a PNG data URL, `shareText` a ref
 * (or computed) holding the post text. Callers own how both are produced.
 */
export function useShareResults({ image, shareText }) {
    const isModalOpen = ref(false);
    const copyState = ref('idle');
    const shareFile = ref(null);

    // Pre-built so share() can call navigator.canShare() synchronously
    // inside the click gesture
    watch(image, async (dataUrl) => {
        if( !dataUrl ) {
            shareFile.value = null;
            copyState.value = 'idle';
            isModalOpen.value = false;
            return;
        }

        const blob = await (await fetch(dataUrl)).blob();
        shareFile.value = new File([blob], 'benchkit-results.png', { type: 'image/png' });
    }, { immediate: true });

    const intentUrl = computed(() => `https://x.com/intent/tweet?text=${encodeURIComponent(shareText.value)}`);

    const imageBlob = async () => {
        return (await fetch(image.value)).blob();
    };

    // The clipboard API only exists in secure contexts (https or localhost),
    // and execCommand cannot copy images — the modal's failed state offers a
    // download instead. Safari only allows the write while the user gesture is
    // active, so ClipboardItem must receive the blob *promise* (not an awaited
    // blob) and this must be called synchronously from the click handler.
    const writeImageToClipboard = () => {
        if( !navigator.clipboard || typeof ClipboardItem === 'undefined' ) {
            return Promise.reject(new Error('Clipboard unavailable'));
        }

        return navigator.clipboard.write([new ClipboardItem({ 'image/png': imageBlob() })]);
    };

    const copyImageToClipboard = () => writeImageToClipboard();

    // Also called when "Open X & paste" is clicked: the clipboard only holds
    // one item, so anything the user copied since the modal opened would have
    // replaced the image — re-copying at the last moment keeps it intact
    const recopyImage = () => {
        copyState.value = 'copying';

        writeImageToClipboard()
            .then(() => copyState.value = 'copied')
            .catch(() => copyState.value = 'failed');
    };

    const openModal = () => {
        recopyImage();
        isModalOpen.value = true;
    };

    // Desktop browsers also pass canShare({files}), but their OS share sheets
    // can't reach X — the guided modal is the better flow there, so the native
    // path is gated to touch devices
    const share = () => {
        const isTouchDevice = window.matchMedia('(hover: none) and (pointer: coarse)').matches;

        if( shareFile.value && navigator.canShare?.({ files: [shareFile.value] }) && isTouchDevice ) {
            navigator.share({ files: [shareFile.value], text: shareText.value })
                .catch((error) => {
                    if( error.name !== 'AbortError' ) {
                        openModal();
                    }
                });

            return;
        }

        openModal();
    };

    const closeModal = () => {
        isModalOpen.value = false;
    };

    return {
        isModalOpen,
        copyState,
        shareText,
        intentUrl,
        share,
        closeModal,
        recopyImage,
        copyImageToClipboard,
        imageBlob,
    };
}
