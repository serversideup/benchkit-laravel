import { computed, ref } from 'vue';
import { useSettings } from '@/Composables/useSettings';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';
import { startGeneratorSession } from '@/Composables/useRunSession';

const { form } = useSettings();
const { generator, boostPolling } = useBenchmarkQueue();

const minting = ref(null);

/**
 * Make sure a pairing exists to show a command for. Idempotent from the
 * caller's side: the server keeps a session bound to a live run and rotates
 * an idle one, and the poll keeps `generator` current afterwards either way.
 */
const ensureSession = async () => {
    if( minting.value ) {
        return minting.value;
    }

    minting.value = startGeneratorSession()
        .then((data) => {
            generator.value = data.generator;

            return data.generator;
        })
        .finally(() => {
            minting.value = null;
        });

    return minting.value;
};

/**
 * -k unconditionally: this instance is as likely to be on a self-signed
 * loopback certificate as on a real one, and from here there is no telling
 * which. A TLS failure on the very first fetch is the one error that leaves
 * the person nothing to act on, and the token — not the transport — is what
 * authenticates the pairing. On a plain http instance the flag does nothing.
 */
const command = computed(() => generator.value
    ? `curl -kfsSL ${window.location.origin}/bench/generator/${generator.value.token}/script | sh`
    : null);

const connected = computed(() => Boolean(generator.value?.handshake));

/**
 * What a closed-loop generator can measure from where it is: the concurrency
 * it will reach divided by the round trip it reported at handshake. Shown,
 * never enforced — the person with the number decides whether it is enough.
 *
 * The load no longer holds a fixed connection count, so this quotes the
 * highest level the sweep will reach. That is the level where the ceiling
 * binds least, which makes this the most generous honest figure rather than
 * one that would understate the machine.
 */
const CEILING_CONCURRENCY = 512;

const ceiling = computed(() => {
    const rtt = generator.value?.handshake?.rtt_ms;

    if (!rtt || rtt <= 0) {
        return null;
    }

    return Math.round(CEILING_CONCURRENCY / (rtt / 1000));
});

export const useGeneratorPairing = () => {
    return {
        generator,
        ensureSession,
        command,
        connected,
        ceiling,
        boostPolling,
    };
};
