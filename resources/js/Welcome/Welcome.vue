<script setup lang="ts">
import WelcomeNav from './sections/WelcomeNav.vue'
import WelcomeFooter from './sections/WelcomeFooter.vue'
import Container from './primitives/Container.vue'
import Button from './primitives/Button.vue'
import { useLandingData } from './useLandingData'

const { user } = useLandingData()
</script>

<template>
    <div class="relative flex min-h-screen flex-col bg-concrete text-chalk antialiased">
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-[var(--radius-sm)] focus:bg-saffron focus:px-4 focus:py-2 focus:font-mono focus:text-xs focus:font-medium focus:uppercase focus:tracking-[0.08em] focus:text-chalk"
        >
            Skip to content
        </a>

        <WelcomeNav />

        <main id="main" class="flex flex-1 flex-col justify-center py-28 sm:py-36">
            <Container>
                <div class="flex max-w-[40rem] flex-col gap-8">
                    <h1 class="max-w-[16ch] text-balance font-display text-[clamp(2.75rem,7.5vw,5.75rem)] font-medium leading-[1.0] tracking-[-0.03em] text-chalk">
                        CsStrats is closing.
                    </h1>

                    <div class="flex max-w-[62ch] flex-col gap-4 text-pretty text-lg text-bone sm:text-xl">
                        <p>
                            We are shutting the product down. Existing accounts can still log in. The tactics library, the board, and the demoviewer stay available for those accounts.
                        </p>
                        <p>
                            Billing and data questions go to
                            <a href="mailto:info@csstrats.dk" class="text-chalk underline decoration-ash underline-offset-4 transition-colors hover:text-saffron">info@csstrats.dk</a>.
                        </p>
                    </div>

                    <div class="flex flex-col items-stretch gap-4 pt-2 sm:flex-row sm:items-center">
                        <template v-if="user">
                            <Button as="a" href="/profile" variant="primary" size="lg">Open the app</Button>
                            <Button as="a" href="/logout" variant="ghost" size="lg">Log out</Button>
                        </template>
                        <template v-else>
                            <Button as="a" href="/login" variant="primary" size="lg">Log in</Button>
                        </template>
                    </div>
                </div>
            </Container>
        </main>

        <WelcomeFooter />
    </div>
</template>
