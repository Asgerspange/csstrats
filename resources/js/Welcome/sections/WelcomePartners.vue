<script setup lang="ts">
import { computed } from 'vue'

// Quiet inline row, not a band: partnership is stated, not shouted.
// includeHltv is opt-in: the landing omits HLTV, the /hltv offer page keeps it.
const props = withDefaults(
    defineProps<{ center?: boolean; includeHltv?: boolean }>(),
    { center: false, includeHltv: false },
)

const allPartners = [
    {
        name: 'HLTV',
        short: 'HLTV',
        href: 'https://www.hltv.org',
        logo: 'https://csstrats.b-cdn.net/partners/hltv.png',
        logoClass: 'h-5 w-5 rounded-[3px]',
        width: 20,
        height: 20,
        hltv: true,
    },
    {
        name: 'Esportligaen',
        short: 'Esportligaen',
        href: 'https://esportligaen.dk',
        logo: 'https://csstrats.b-cdn.net/partners/esportligaen.webp',
        logoClass: 'h-5 w-auto',
        width: 24,
        height: 20,
    },
    {
        name: 'Danske Gymnastik og Idrætsforeninger',
        short: 'DGI',
        href: 'https://www.dgi.dk',
        logo: 'https://csstrats.b-cdn.net/partners/dgi_logo.jpg',
        logoClass: 'h-5 w-auto',
        width: 88,
        height: 20,
    },
]

const partners = computed(() =>
    props.includeHltv ? allPartners : allPartners.filter((p) => !p.hltv),
)
</script>

<template>
    <div
        aria-label="Partners"
        class="flex flex-wrap items-center gap-x-8 gap-y-3"
        :class="center ? 'justify-center' : ''"
    >
        <p class="font-mono text-[11px] font-medium uppercase tracking-[0.1em] text-bone">
            Partnered with
        </p>
        <a
            v-for="partner in partners"
            :key="partner.name"
            :href="partner.href"
            target="_blank"
            rel="noopener"
            class="group flex items-center gap-2.5"
        >
            <img
                :src="partner.logo"
                :alt="`${partner.name} logo`"
                :class="partner.logoClass"
                :width="partner.width"
                :height="partner.height"
                loading="lazy"
                decoding="async"
            />
            <span class="font-display text-base font-semibold tracking-[-0.01em] text-bone transition-colors duration-[180ms] group-hover:text-chalk">
                <span class="sm:hidden">{{ partner.short }}</span>
                <span class="hidden sm:inline">{{ partner.name }}</span>
            </span>
        </a>
    </div>
</template>
