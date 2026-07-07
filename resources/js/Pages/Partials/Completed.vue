<template>
    <div class="w-full flex flex-col items-center justify-center py-16">
        <div class="w-full flex flex-col items-center justify-center">
            <h1 class="text-[#F7F7F7] uppercase font-bold font-mono text-6xl mb-3">Your Results</h1>
            
            <div>
                <div v-show="!resultsImage" id="results-image" style="display: flex; flex-direction: column; justify-content: center; align-items: center; width: 750px;">
                    <div style="background-color: #771A0D; height: 88px; display: flex; align-items: center; justify-content: center; border-top-left-radius: 12px; border-top-right-radius: 12px; width: 100%;">
                        <img src="/images/results/title.png" style="max-width: 475px; margin: auto; display: block;"/>
                    </div>

                    <div style="background-color: #0C0E12; height: 256px; width: 100%; padding-left: 24px; padding-right: 24px; padding-top: 4px; padding-bottom: 4px; display: flex; align-items: start; justify-content: space-between;">
                        <div style="display: flex; flex-direction: column; padding-top: 12px; padding-bottom: 12px;">
                            <label style="font-size: 14px; color: #61656C; font-family: var(--font-mono); font-weight: 400;">LARAVEL DATABASE PERFORMANCE</label>
                            <div style="display: flex; align-items: center; justify-content: space-between">
                                <div style="display: flex; flex-direction: column;">
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; align-items: center">
                                            <img src="/images/results/create.png" style="width: 20px; margin-right: 4px;"/>
                                            <span style="color: #FFF; font-size: 20px; font-family: var(--font-mono);">CREATE</span>
                                            <span style="font-size: 12px; color: #61656C; font-family: var(--font-mono); font-weight: 400;">({{ summary.php.create.records ?? 100 }} RECORDS)</span>
                                        </div>
                                        <span style="color: white; font-size: 48px; font-family: var(--font-mono); font-weight: 500;">{{ formatMs(summary.php.create.ms) }}</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; margin-top: 12px;">
                                        <div style="display: flex; align-items: center">
                                            <img src="/images/results/update.png" style="width: 20px; margin-right: 4px;"/>
                                            <span style="color: #FFF; font-size: 20px; font-family: var(--font-mono);">UPDATE</span>
                                            <span style="font-size: 12px; color: #61656C; font-family: var(--font-mono); font-weight: 400;">({{ summary.php.update.records ?? 100 }} RECORDS)</span>
                                        </div>
                                        <span style="color: white; font-size: 48px; font-family: var(--font-mono); font-weight: 500;">{{ formatMs(summary.php.update.ms) }}</span>
                                    </div>
                                </div>
                                <div style="display: flex; flex-direction: column; margin-left: 24px;">
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; align-items: center">
                                            <img src="/images/results/read.png" style="width: 20px; margin-right: 4px;"/>
                                            <span style="color: #FFF; font-size: 20px; font-family: var(--font-mono);">READ</span>
                                            <span style="font-size: 12px; color: #61656C; font-family: var(--font-mono); font-weight: 400;">({{ summary.php.read.records ?? 100 }} RECORDS)</span>
                                        </div>
                                        <span style="color: white; font-size: 48px; font-family: var(--font-mono); font-weight: 500;">{{ formatMs(summary.php.read.ms) }}</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; margin-top: 12px;">
                                        <div style="display: flex; align-items: center">
                                            <img src="/images/results/delete.png" style="width: 20px; margin-right: 4px;"/>
                                            <span style="color: #FFF; font-size: 20px; font-family: var(--font-mono);">DELETE</span>
                                            <span style="font-size: 12px; color: #61656C; font-family: var(--font-mono); font-weight: 400;">({{ summary.php.delete.records ?? 100 }} RECORDS)</span>
                                        </div>
                                        <span style="color: white; font-size: 48px; font-family: var(--font-mono); font-weight: 500;">{{ formatMs(summary.php.delete.ms) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; flex-direction: column; padding-top: 12px; padding-bottom: 12px;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; flex-direction: column;">
                                    <label style="font-size: 14px; color: #61656C; font-family: var(--font-mono); font-weight: 400;">SERVER</label>
                                    <span style="font-size: 16px; color: #CECFD2; font-family: var(--font-mono); font-weight: 500;">{{ php.php_server_api }}</span>
                                </div>
                                <div style="display: flex; flex-direction: column; margin-left: 16px; margin-right: 16px;">
                                    <label style="font-size: 14px; color: #61656C; font-family: var(--font-mono); font-weight: 400;">PHP</label>
                                    <span style="font-size: 16px; color: #CECFD2; font-family: var(--font-mono); font-weight: 500;">{{ laravel.environment.php_version }}</span>
                                </div>
                                <div style="display: flex; flex-direction: column;">
                                    <label style="font-size: 14px; color: #61656C; font-family: var(--font-mono); font-weight: 400;">DATABASE</label>
                                    <span style="font-size: 16px; color: #CECFD2; font-family: var(--font-mono); font-weight: 500;">{{ laravel.drivers.database }}</span>
                                </div>
                            </div>
                            <div style="display: flex; flex-direction: column; margin-top: 24px; margin-bottom: 24px;">
                                <label style="font-size: 14px; color: #61656C; font-family: var(--font-mono); font-weight: 400; text-align: right;">NETWORK SPEED TEST</label>
                                <div style="display: flex; flex-direction: column;" v-if="results['cfspeedtest'].status === 'completed'">
                                    <div style="display: flex; align-items: center; justify-content: space-between;">
                                        <span style="display: flex; align-items: center;">
                                            <img src="/images/results/download-cloud.png" style="width: 14px; margin-right: 4px;"/>
                                            <span style="font-size: 16px; color: #CECFD2; font-family: var(--font-mono); font-weight: 500;">{{ parseFloat(summary.network.download).toFixed(0) }} mbps</span>
                                        </span>
                                        <span style="display: flex; align-items: center; margin-left: 12px; margin-right: 12px;">
                                            <img src="/images/results/upload-cloud.png" style="width: 14px; margin-right: 4px;"/>
                                            <span style="font-size: 16px; color: #CECFD2; font-family: var(--font-mono); font-weight: 500;">{{ parseFloat(summary.network.upload).toFixed(0) }} mbps</span>
                                        </span>
                                        <span style="display: flex; align-items: center;">
                                            <img src="/images/results/latency-switch.png" style="width: 14px; margin-right: 4px;"/>
                                            <span style="font-size: 16px; color: #CECFD2; font-family: var(--font-mono); font-weight: 500;">{{ parseFloat(summary.network.latency).toFixed(0) }} ms</span>
                                        </span>
                                    </div>
                                    <div style="display: flex; align-items: center; justify-content: end;">
                                        <span style="font-size: 12px; color: #61656C; font-family: var(--font-mono); font-weight: 400;">{{ summary.network.source }}</span>
                                        <img src="/images/results/right-arrow.png" style="width: 16px; height: 16px; margin-right: 8px; margin-left: 8px;"/>
                                        <span style="font-size: 12px; color: #61656C; font-family: var(--font-mono); font-weight: 400;">Cloudflare ({{ summary.network.colo }})</span>
                                    </div>
                                </div>
                                <span v-else style="font-size: 16px; color: #CECFD2; font-family: var(--font-mono); font-weight: 500; text-align: right;">N/A</span>
                            </div>
                            <div style="display: flex; flex-direction: column;">
                                <label style="font-size: 14px; color: #61656C; font-family: var(--font-mono); font-weight: 400; text-align: right;">GEEKBENCH RESULTS</label>
                                <div style="display: flex; align-items: center; justify-content: end;" v-if="results['yabs'].status === 'completed' && summary.yabs.score_single && summary.yabs.score_multi">
                                    <div style="display: flex; flex-direction: column; margin-right: 16px;">
                                        <div style="display: flex; align-items: center; justify-content: end;">
                                            <img src="/images/results/single-core.png" style="width: 16px; margin-right: 4px;"/>
                                            <span style="font-size: 24px; color: #CECFD2; font-family: var(--font-mono); font-weight: 500;">{{ summary.yabs.score_single }}</span>
                                        </div>
                                        <span style="font-size: 14px; color: #61656C; font-family: var(--font-mono); font-weight: 400;">single-core</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; align-items: center; justify-content: end;">
                                            <img src="/images/results/multi-core.png" style="width: 16px; margin-right: 4px;"/>
                                            <span style="font-size: 24px; color: #CECFD2; font-family: var(--font-mono); font-weight: 500;">{{ summary.yabs.score_multi }}</span>
                                        </div>
                                        <span style="font-size: 14px; color: #61656C; font-family: var(--font-mono); font-weight: 400;">multi-core</span>
                                    </div>
                                </div>
                                <span v-else style="font-size: 16px; color: #CECFD2; font-family: var(--font-mono); font-weight: 500; text-align: right;">N/A</span>
                            </div>
                        </div>
                    </div>

                    <div style="background-color: #771A0D; height: 56px; display: flex; align-items: center; justify-content: space-between; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; width: 100%; padding-left: 24px; padding-right: 24px;">
                        <img src="/images/results/benchkit-by-server-side-up.png" style="height: 32px;"/>
                        <span style="font-size: 18px; color: #CECFD2; font-family: var(--font-mono); font-weight: 500;">{{ timestamp }}</span>
                    </div>
                </div>
            </div>

            <div v-show="resultsImage" id="results-image-container">
                <img :src="resultsImage" alt="Results Image" class="w-[750px] mx-auto" />
            </div>

            <div class="w-full flex items-center justify-center mt-7">
                <a href="https://x.com/intent/tweet?text=Check%20out%20my%20%23BenchKit%20by%20%40serversideup%20results!%20How%20fast%20is%20your%20host%20with%20%23Laravel?" target="_blank" class="w-80 rounded-lg py-3 flex items-center justify-center font-mono shadow-sm text-white bg-[#E62E05] border border-[#E62E05]">
                    Share on 
                    <svg class="ml-2" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                        <path d="M10.1909 7.41006L16.5656 0H15.055L9.51988 6.43405L5.09898 0H0L6.68527 9.72942L0 17.5H1.51068L7.35593 10.7054L12.0247 17.5H17.1237L10.1906 7.41006H10.1909ZM8.12184 9.81514L7.44449 8.84631L2.055 1.13722H4.37532L8.7247 7.3587L9.40206 8.32753L15.0557 16.4145H12.7354L8.12184 9.81551V9.81514Z" fill="white"/>
                    </svg>
                </a>
            </div>

            <div class="w-full flex items-center justify-center mt-4" v-show="resultsImage">
                <button @click="downloadImage()" type="button" class="w-80 rounded-lg py-3 flex items-center justify-center font-mono shadow-sm text-[#CECFD2] bg-[#0C0E12] border border-[#373A41] cursor-pointer">
                    <svg class="mr-2" xmlns="http://www.w3.org/2000/svg" width="19" height="17" viewBox="0 0 19 17" fill="none">
                        <path d="M2.50016 11.8685C1.49517 11.1958 0.833496 10.0502 0.833496 8.75C0.833496 6.79702 2.32642 5.19274 4.23328 5.01614C4.62334 2.64344 6.6837 0.833332 9.16683 0.833332C11.65 0.833332 13.7103 2.64344 14.1004 5.01614C16.0072 5.19274 17.5002 6.79702 17.5002 8.75C17.5002 10.0502 16.8385 11.1958 15.8335 11.8685M5.8335 12.5L9.16683 15.8333M9.16683 15.8333L12.5002 12.5M9.16683 15.8333V8.33333" stroke="#61656C" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Download Image
                </button>
            </div>

            <div class="w-full flex items-center justify-center mt-4">
                <button @click="runAgain()" type="button" class="w-80 rounded-lg py-3 flex items-center justify-center font-mono shadow-sm text-[#CECFD2] bg-[#0C0E12] border border-[#373A41] cursor-pointer">
                    <svg class="mr-2" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M1.6665 8.33333C1.6665 8.33333 3.33732 6.05685 4.6947 4.69854C6.05208 3.34022 7.92783 2.5 9.99984 2.5C14.142 2.5 17.4998 5.85786 17.4998 10C17.4998 14.1421 14.142 17.5 9.99984 17.5C6.58059 17.5 3.69576 15.2119 2.79298 12.0833M1.6665 8.33333V3.33333M1.6665 8.33333H6.6665" stroke="#61656C" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Run Again
                </button>
            </div>
        </div>
        <div class="w-full flex items-center justify-between mx-auto max-w-screen-lg mt-16">
            <h2 class="text-4xl font-medium text-[#F7F7F7] uppercase font-mono">Detailed Results</h2>

            <div class="flex items-center">
                <a :href="geekBenchUrl" target="_blank" v-show="results['yabs'].status === 'completed' && geekBenchUrl" type="button" class="px-[14px] py-2.5 rounded-lg border border-[#373A41] bg-[#0C0E12] text-sm text-[#CECFD2] font-mono cursor-pointer flex items-center">
                    <svg class="mr-1" xmlns="http://www.w3.org/2000/svg" width="16" height="19" viewBox="0 0 16 19" fill="none">
                        <path d="M8.69389 0.833382L1.27178 9.73993C0.981101 10.0887 0.835765 10.2631 0.833544 10.4104C0.831613 10.5385 0.888671 10.6603 0.988276 10.7408C1.10285 10.8334 1.32988 10.8334 1.78392 10.8334H7.86056L7.02723 17.5L14.4493 8.5935C14.74 8.2447 14.8854 8.07029 14.8876 7.923C14.8895 7.79495 14.8325 7.67313 14.7328 7.59264C14.6183 7.50005 14.3912 7.50005 13.9372 7.50005H7.86056L8.69389 0.833382Z" stroke="#61656C" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    View Geekbench Results
                </a>
                <button @click="downloadResults()" type="button" class="ml-2.5 px-[14px] py-2.5 rounded-lg border border-[#373A41] bg-[#0C0E12] text-sm text-[#CECFD2] font-mono cursor-pointer flex items-center">
                    <svg class="mr-1" xmlns="http://www.w3.org/2000/svg" width="19" height="17" viewBox="0 0 19 17" fill="none">
                        <path d="M2.50016 11.8685C1.49517 11.1958 0.833496 10.0502 0.833496 8.75C0.833496 6.79702 2.32642 5.19274 4.23328 5.01614C4.62334 2.64344 6.6837 0.833332 9.16683 0.833332C11.65 0.833332 13.7103 2.64344 14.1004 5.01614C16.0072 5.19274 17.5002 6.79702 17.5002 8.75C17.5002 10.0502 16.8385 11.1958 15.8335 11.8685M5.8335 12.5L9.16683 15.8333M9.16683 15.8333L12.5002 12.5M9.16683 15.8333V8.33333" stroke="#61656C" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Download Results
                </button>
            </div>
        </div>

        <BenchmarkResults />
    </div>
</template>

<script setup>
import * as htmlToImage from 'html-to-image';
import BenchmarkResults from '@/Pages/Partials/BenchmarkResults.vue';
import { onMounted, ref, reactive, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';

const {
    startQueue,
    results
} = useBenchmarkQueue();

const php = computed(() => usePage().props.php);
const laravel = computed(() => JSON.parse(usePage().props.laravel));

const formatUTCTimestamp = (date) => {
    const pad = n => n < 10 ? '0' + n : n;
    const day = pad(date.getUTCDate());
    const month = pad(date.getUTCMonth() + 1);
    const year = date.getUTCFullYear();
    const hour = date.getUTCHours();
    const minute = pad(date.getUTCMinutes());
    return `${day}/${month}/${year} ${hour}:${minute} UTC`;
};

const timestamp = ref(formatUTCTimestamp(new Date()));
const geekBenchUrl = ref(null);

const summary = reactive({
    yabs: {
        score_single: null,
        score_multi: null
    },
    network: {
        asn: null,
        colo: null,
        upload: null,
        download: null,
        latency: null,
        source: null,
        destination: null
    },
    php: {
        create: { ms: null, records: null },
        read: { ms: null, records: null },
        update: { ms: null, records: null },
        delete: { ms: null, records: null }
    }
});

const formatMs = (ms) => {
    return ms === null || ms === undefined ? 'N/A' : `${ms}ms`;
}


onMounted(() => {
    buildSummary();
});

const summaryBuilt = reactive({
    yabs: false,
    cfspeedtest: false,
    php: false
});

const readyToGenerateImage = computed(() => {
    return summaryBuilt.yabs && summaryBuilt.cfspeedtest && summaryBuilt.php;
});

watch(readyToGenerateImage, () => {
    if( readyToGenerateImage.value ) {
        generateImage();
    }
});

const buildSummary = () => {
    if( results['yabs'].status === 'completed' ) {
        buildYabsSummary();
    }else{
        summaryBuilt.yabs = true;
    }

    if( results['cfspeedtest'].status === 'completed' ) {
        buildCfspeedtestSummary();
    }else{
        summaryBuilt.cfspeedtest = true;
    }

    if( results['php'].status === 'completed' ) {
        buildPhpSummary();
    }else{
        summaryBuilt.php = true;
    }
}

const buildCfspeedtestSummary = async () => {
    try {
        const response = await fetch('/cfspeedtest/results');

        if( response.ok ) {
            const data = await response.json();
            const network = data.cfspeedtest_results;

            summary.network.asn = network.asn;
            summary.network.colo = network.colo;
            summary.network.latency = network.latency_ms;
            summary.network.download = network.download_mbps;
            summary.network.upload = network.upload_mbps;

            if( network.asn ) {
                const asData = await fetch(`https://stat.ripe.net/data/as-overview/data.json?resource=AS${network.asn}`).then(response => response.json());
                summary.network.source = asData.data.holder;
            }
        }
    } catch (error) {
        console.error(error);
    } finally {
        summaryBuilt.cfspeedtest = true;
    }
}

const buildYabsSummary = async () => {
    try {
        const response = await fetch('/yabs/results');

        if( response.ok ) {
            const data = await response.json();

            if( data.geekbench && data.geekbench[0] && data.geekbench[0].single && data.geekbench[0].multi ) {
                summary.yabs.score_single = data.geekbench[0].single;
                summary.yabs.score_multi = data.geekbench[0].multi;
                geekBenchUrl.value = data.geekbench[0].url;
            }
        }
    } catch (error) {
        console.error(error);
    } finally {
        summaryBuilt.yabs = true;
    }
}

const buildPhpSummary = async () => {
    try {
        const response = await fetch('/php/results');

        if( response.ok ) {
            const data = await response.json();

            ['create', 'read', 'update', 'delete'].forEach((key) => {
                summary.php[key].ms = data.phpbench_results[key].milliseconds;
                summary.php[key].records = data.phpbench_results[key].records;
            });
        }
    } catch (error) {
        console.error(error);
    } finally {
        summaryBuilt.php = true;
    }
}

const resultsImage = ref(null);
const generateImage = () => {
    htmlToImage.toPng(document.getElementById('results-image'), {
        quality: 1,
        skipFonts: true,
        style: {
            fontFamily: 'JetBrains Mono, monospace',
        },
    }).then(function(dataUrl) {
        resultsImage.value = dataUrl;
    });
}

const runAgain = () => {
    resetSummary();
    startQueue();
}

const resetSummary = () => {
    summary.yabs.score_single = null;
    summary.yabs.score_multi = null;
    summary.network.asn = null;
    summary.network.colo = null;
    summary.network.upload = null;
    summary.network.download = null;
    summary.network.latency = null;
    summary.network.source = null;
    summary.network.destination = null;

    ['create', 'read', 'update', 'delete'].forEach((key) => {
        summary.php[key].ms = null;
        summary.php[key].records = null;
    });

    summaryBuilt.yabs = false;
    summaryBuilt.cfspeedtest = false;
    summaryBuilt.php = false;
    geekBenchUrl.value = null;
    resultsImage.value = null;
}

const fileTimestamp = () => {
    const date = new Date();
    const year = date.getFullYear();
    const month = date.getMonth() + 1;
    const day = date.getDate();
    const hour = date.getHours();
    const minute = date.getMinutes();
    const second = date.getSeconds();

    return `${year}-${month}-${day}-${hour}${minute}${second}`;
}

const downloadBlob = (blob, filename) => {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

const downloadImage = () => {
    const a = document.createElement('a');
    a.href = resultsImage.value;
    a.download = `benchkit-results-${fileTimestamp()}.png`;
    a.click();
}

const downloadResults = async () => {
    let text = '';

    if( results['yabs'].status === 'completed' ) {
        text += '################################################################################\n';
        text += '# HARDWARE TESTS\n';
        text += '################################################################################\n';
        text += results['yabs'].output.join('\n');
        text += '\n';
    }

    if( results['cfspeedtest'].status === 'completed' ) {
        text += '################################################################################\n';
        text += '# NETWORK TESTS\n';
        text += '################################################################################\n';
        text += results['cfspeedtest'].output.join('\n');
        text += '\n';
    }

    if( results['php'].status === 'completed' ) {
        text += '################################################################################\n';
        text += '# PHP TESTS\n';
        text += '################################################################################\n';
        text += results['php'].output.join('\n');
        text += '\n';
    }

    const formattedDate = fileTimestamp();

    downloadBlob(new Blob([text], { type: 'text/plain' }), `benchkit-results-${formattedDate}.txt`);

    // Also download the unified machine-readable results document
    try {
        const response = await fetch('/results');

        if( response.ok ) {
            const json = await response.json();
            downloadBlob(
                new Blob([JSON.stringify(json, null, 2)], { type: 'application/json' }),
                `benchkit-results-${formattedDate}.json`
            );
        }
    } catch (error) {
        console.error(error);
    }
}
</script>