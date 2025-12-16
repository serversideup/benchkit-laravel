import { useForm } from '@inertiajs/vue3'

const form = useForm({
    hardware: true,
    disk: true,
    geekbench: true,
    geekbench_version: 6,
    iperf: false,
    network: true,
    network_test_type: 'ipv4',
    php_database: true
})

export const useSettings = () => {
    return {
        form
    }
}