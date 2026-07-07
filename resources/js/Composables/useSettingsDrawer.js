import { ref } from 'vue'

const isOpen = ref(false)

export const useSettingsDrawer = () => {
    return {
        isOpen,
        open: () => isOpen.value = true,
        close: () => isOpen.value = false,
        toggle: () => isOpen.value = !isOpen.value,
    }
}
