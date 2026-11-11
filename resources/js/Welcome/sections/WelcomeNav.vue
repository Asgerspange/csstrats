<script setup lang="ts">
import Button from '../primitives/Button.vue'
import { useLandingData } from '../useLandingData'

const { user } = useLandingData()
</script>

<template>
    <header class="absolute top-0 left-0 right-0 z-50">
        <div class="mx-auto flex w-full max-w-[80rem] items-center justify-between px-6 py-5 sm:px-10 lg:px-16">
            <a
                href="/"
                class="flex items-center gap-2"
                aria-label="CsStrats home"
            >
                <img
                    src="https://csstrats.b-cdn.net/icons/isdigital.svg"
                    alt=""
                    width="26"
                    height="26"
                    class="h-[26px] w-[26px] shrink-0"
                />
                <span class="font-display text-2xl font-medium leading-none tracking-[-0.02em] text-chalk">
                    cs<span class="text-saffron">strats</span>
                </span>
            </a>

            <div class="flex items-center gap-3">
                <template v-if="user">
                    <Button as="a" href="/profile" variant="ghost" size="sm">Profile</Button>
                    <Button as="a" href="/logout" variant="primary" size="sm">Log out</Button>
                </template>
                <template v-else>
                    <Button as="a" href="/login" variant="primary" size="sm">Log in</Button>
                </template>
            </div>
        </div>
    </header>
</template>
