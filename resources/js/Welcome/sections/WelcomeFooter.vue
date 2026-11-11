<script setup lang="ts">
import Container from '../primitives/Container.vue'

const groups = [
    {
        title: 'Company',
        links: [
            { name: 'IS Digital ApS', href: '', external: true },
            { name: 'Discord', href: 'https://discord.gg/Rmp5p4r7CT', external: false },
            { name: 'Twitter (X)', href: 'https://x.com/CsStrats2', external: true },
        ],
    },
    {
        title: 'Legal',
        links: [
            { name: 'Terms', href: '/tos' },
            { name: 'Privacy', href: '/policy' },
            { name: 'Cookies', href: '/cookie-policy' },
        ],
    },
]

const year = new Date().getFullYear()

const openConsentManager = (e: MouseEvent) => {
    e.preventDefault()
    const consent = (window as Window & { csConsent?: { open?: () => void } }).csConsent
    consent?.open?.()
}
</script>

<template>
    <footer class="relative border-t border-ash pt-16 pb-12">
        <Container>
            <div class="grid gap-16 lg:grid-cols-[2fr_3fr]">
                <div class="flex flex-col gap-6">
                    <a href="/" class="font-display text-2xl font-medium tracking-[-0.02em] text-chalk">
                        cs<span class="text-saffron">strats</span>
                    </a>
                    <p class="max-w-[40ch] text-sm text-bone">
                        CsStrats is closing. Built in Odense by IS Digital ApS.
                    </p>
                </div>

                <nav class="grid grid-cols-2 gap-12" aria-label="Footer">
                    <div v-for="group in groups" :key="group.title" class="flex flex-col gap-4">
                        <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                            {{ group.title }}
                        </p>
                        <ul class="flex flex-col gap-3">
                            <li v-for="link in group.links" :key="link.name">
                                <a
                                    :href="link.href"
                                    :target="link.external ? '_blank' : '_self'"
                                    :rel="link.external ? 'noopener' : undefined"
                                    class="text-sm text-chalk transition-colors duration-[180ms] hover:text-saffron"
                                >
                                    {{ link.name }}
                                </a>
                            </li>
                            <li v-if="group.title === 'Legal'">
                                <button
                                    type="button"
                                    class="text-sm text-chalk transition-colors duration-[180ms] hover:text-saffron"
                                    @click="openConsentManager"
                                >
                                    Manage consents
                                </button>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>

            <div class="mt-16 flex flex-col gap-3 border-t border-ash pt-8 sm:flex-row sm:items-center sm:justify-between">
                <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                    &copy; {{ year }} IS Digital ApS &middot; Odense, DK
                </p>
                <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                    Not affiliated with Valve. CS2 is a trademark of Valve Corporation.
                </p>
            </div>
        </Container>
    </footer>
</template>
