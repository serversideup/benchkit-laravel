// The clipboard API only exists in secure contexts (https or localhost) —
// plain-http homelab setups fall back to a hidden textarea + execCommand
export const writeTextToClipboard = (text) => {
    if (navigator.clipboard) {
        return navigator.clipboard.writeText(text)
    }

    return new Promise((resolve, reject) => {
        const textarea = document.createElement('textarea')
        textarea.value = text
        textarea.style.position = 'fixed'
        textarea.style.opacity = '0'
        document.body.appendChild(textarea)
        textarea.select()
        const succeeded = document.execCommand('copy')
        document.body.removeChild(textarea)
        succeeded ? resolve() : reject(new Error('Clipboard unavailable'))
    })
}
