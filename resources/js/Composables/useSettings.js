import { useForm } from '@inertiajs/vue3'

const form = useForm({
    hardware: true,
    disk: true,
    geekbench: true,
    geekbench_version: 6,
    iperf: false,
    network: true,
    network_test_type: 'ipv4',
    php_database: true,
    database: {
        create: 1000,
        read: 1000,
        update: 500,
        delete: 500,
    },
})

export const useSettings = () => {

    const runBenchmark = () => {
        form.post('/settings/benchmark');
    }

    return {
        form,
        runBenchmark,
    }
}