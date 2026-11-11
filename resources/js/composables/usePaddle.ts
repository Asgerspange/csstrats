import { ref, readonly } from 'vue';
import { initializePaddle, type Paddle } from '@paddle/paddle-js';
import api from '@/lib/axios';

/**
 * Paddle for the authenticated app bundle. Initialised once on demand (the
 * first caller wins; later callers reuse the same instance), then exposes a
 * `checkout()` helper that opens the Paddle overlay for a given priceId.
 *
 * Mirrors the checkout flow in the marketing WelcomePricing.vue, but lives in
 * the app bundle so the in-app upgrade modal can open checkout without a
 * page navigation. `window.PADDLE_TOKEN` / `window.PADDLE_SANDBOX` are
 * injected by app.blade.php.
 */

const paddle = ref<Paddle | null>(null);
const initialising = ref(false);
const initialised = ref(false);

let initPromise: Promise<Paddle | null> | null = null;

async function ensurePaddle(): Promise<Paddle | null> {
    if (initialised.value && paddle.value) return paddle.value;
    if (initPromise) return initPromise;

    initialising.value = true;
    initPromise = (async () => {
        try {
            const instance = await initializePaddle({
                environment: window.PADDLE_SANDBOX ? 'sandbox' : 'production',
                token: window.PADDLE_TOKEN,
            });
            paddle.value = instance;
            initialised.value = !!instance;
            return instance;
        } catch {
            return null;
        } finally {
            initialising.value = false;
        }
    })();
    return initPromise;
}

export interface CheckoutResult {
    status: 'opened' | 'subscribed' | 'error';
    message?: string;
}

/**
 * Open Paddle checkout for a single priceId. Resolves the Paddle customer id
 * via /billing/customer (creating one if needed). If the user already has an
 * active Paddle subscription, returns 'subscribed' so the caller can show the
 * "manage your subscription" path instead of opening checkout.
 *
 * @param priceId The Paddle price id to check out.
 * @param options.discountCode Optional promo / discount code.
 */
async function checkout(
    priceId: string,
    options: { discountCode?: string } = {},
): Promise<CheckoutResult> {
    const instance = await ensurePaddle();
    if (!instance) {
        return { status: 'error', message: 'Checkout could not start. Try again.' };
    }

    try {
        // Already-subscribed check: don't open a second checkout over an
        // existing Paddle sub.
        const subRes = await fetch('/billing/subscription', { credentials: 'include' });
        if (subRes.ok) {
            const sub = await subRes.json();
            if (sub?.subscription_id) {
                return { status: 'subscribed' };
            }
        }

        // Resolve / create the Paddle customer.
        const custRes = await api.post<{ customer_id: string }>('/billing/customer', {});
        const customer_id = custRes.data.customer_id;

        const config: Parameters<NonNullable<Paddle>['Checkout']>['open'][0] = {
            items: [{ priceId, quantity: 1 }],
            customer: { id: customer_id },
            settings: {
                successUrl: window.location.origin,
                theme: 'dark',
                displayMode: 'overlay',
            },
        };
        if (options.discountCode) {
            config.discountCode = options.discountCode;
        }

        instance.Checkout.open(config);
        return { status: 'opened' };
    } catch {
        return { status: 'error', message: 'Checkout could not start. Try again.' };
    }
}

export function usePaddle() {
    return {
        paddle: readonly(paddle),
        initialising: readonly(initialising),
        ensurePaddle,
        checkout,
    };
}
