<script setup lang="ts">
    import { ref, computed } from 'vue'
    import Container from '../primitives/Container.vue'

    const features = [
        {
            title: 'Tactics',
            description: 'Find, save, and edit professional tactics.',
            bg: 'https://csstrats.b-cdn.net/features/tactics/billede_2026-08-02_034735353.png',
            gallery: [
                'https://csstrats.b-cdn.net/features/tactics/billede_2026-08-02_040245753.png',
                'https://csstrats.b-cdn.net/features/tactics/billede_2026-08-02_040551877.png',
                'https://csstrats.b-cdn.net/features/tactics/billede_2026-08-02_034735353.png'
            ],
            infoHeading: "Explore our big collection of tactics",
            infoDescription: "We provide over a thousand tactics from professional players, all available for you to explore and learn from. Our tactics are organized by map and category, making it easy to find the perfect strategy for your next game.",
            color: '#b0f3ff',
            backgroundColor: '#e3fbff'
        },
        {
            title: 'Demo Viewer',
            description: 'Watch demos with our built-in demo viewer, complete with advanced features.',
            
            bg: 'https://csstrats.b-cdn.net/features/demo/billede_2026-08-02_041924213.png',
            gallery: [
                'https://csstrats.b-cdn.net/features/demo/billede_2026-08-02_042113425.png',
                'https://csstrats.b-cdn.net/features/demo/billede_2026-08-02_042158610.png',
                'https://csstrats.b-cdn.net/features/demo/billede_2026-08-02_041924213.png'
            ],
            infoHeading: "Powerful demo viewer with advanced features",
            infoDescription: "Our demo viewer allows you to watch demos with advanced features like 3D, 2D, slow motion, free camera, Multi-Demo comparison and more. Analyze professional players' demos and learn from their strategies.",
            
            color: '#f4ff5e',
            backgroundColor: '#fcffd5'
        },
        {
            title: 'Strat Builder',
            description: 'Build and visualize strategies with our intuitive strat builder.',
            bg: 'https://csstrats.b-cdn.net/features/stratbuilder/billede_2026-08-02_214143732.png',
            gallery: [
                'https://csstrats.b-cdn.net/features/stratbuilder/billede_2026-08-02_213010708.png',
                'https://csstrats.b-cdn.net/features/stratbuilder/billede_2026-08-02_214339295.png',
                'https://csstrats.b-cdn.net/features/stratbuilder/billede_2026-08-02_214143732.png'
            ],
            infoHeading: "Use our Strat Builder to gain an competitive edge",
            infoDescription: "Our Strat Builder allows you to create and visualize strategies with ease. Plan your tactics, analyze your opponents, and gain a competitive edge in your matches.",
            
            color: '#ffb0f3',
            backgroundColor: '#ffe3fb'
        },
        {
            title: 'Stratbooks',
            description: 'Organize and share your strategies with our stratbook feature.',
            bg: 'https://csstrats.b-cdn.net/features/tactics/billede_2026-08-02_034735353.png',
            gallery: [
                'https://csstrats.b-cdn.net/features/tactics/billede_2026-08-02_040245753.png',
                'https://csstrats.b-cdn.net/features/tactics/billede_2026-08-02_040551877.png',
                'https://csstrats.b-cdn.net/features/tactics/billede_2026-08-02_034735353.png'
            ],
            infoHeading: "Save and create your own stratbooks to share with your team",
            infoDescription: "Our Stratbook feature allows you to organize and share your strategies with your team. Create your own stratbooks, add tactics, and collaborate with your teammates to improve your gameplay.",
            
            backgroundColor: '#c6bbfb',
            color: '#8e77f7'
        },
        // { title: 'AI Antistrat',  description: 'Get AI-generated counter-strategies to improve your gameplay.', 
        //    bg: 'https://csstrats.b-cdn.net/features/tactics/billede_2026-08-02_034735353.png',
        //     gallery: [
        //         'https://csstrats.b-cdn.net/features/tactics/billede_2026-08-02_040245753.png',
        //         'https://csstrats.b-cdn.net/features/tactics/billede_2026-08-02_040551877.png',
        //         'https://csstrats.b-cdn.net/features/tactics/billede_2026-08-02_034735353.png'
        //     ],
        //     infoHeading: "Explore our big collection of tactics",
        //     infoDescription: "We provide over a thousand tactics from professional players, all available for you to explore and learn from. Our tactics are organized by map and category, making it easy to find the perfect strategy for your next game.",
        //      color: '#ff4f00', backgroundColor: '#ff4f00' }
    ]

    const selectedIndex = ref(0)
    const selectedFeature = computed(() => features[selectedIndex.value])
    const panelColor = computed(() => selectedFeature.value.backgroundColor || selectedFeature.value.color)
    const panelStyle = computed(() => ({
        backgroundColor: panelColor.value,
        borderTopLeftRadius: selectedIndex.value === 0 ? '0px' : '16px',
        borderTopRightRadius: selectedIndex.value === features.length - 1 ? '0px' : '16px',
    }))
</script>

<template>
    <section class="relative py-16 sm:py-20 lg:py-24">
        <Container>
            <div class="flex gap-4 relative z-10">
                <div
                    v-for="(feature, i) in features"
                    :key="feature.title"
                    class="feature-card flex-1 basis-0 cursor-pointer p-4 text-left focus:outline-none focus:ring-2 focus:ring-black/20"
                    :class="selectedIndex === i
                        ? 'is-active rounded-t-2xl'
                        : 'bg-white shadow-lg rounded-2xl transition-transform duration-200 hover:-translate-y-0.5'"
                    :style="selectedIndex === i
                        ? `--panel: ${panelColor}; --fillet-left: ${i > 0 ? '16px' : '0px'}; --fillet-right: ${i < features.length - 1 ? '16px' : '0px'}; background-color: ${panelColor};`
                        : ''"
                    @click="selectedIndex = i"
                >
                    <div :style="`background-color: ${feature.color}`" class="mb-2 flex h-24 w-full items-center justify-center rounded-2xl pt-4 px-4 bg-white/60">

                        <img v-if="feature.bg" :src="feature.bg" class="h-full w-full object-cover rounded-t-2xl" />
                        <span v-else class="text-2xl font-bold text-black">{{ feature.icon }}</span>
                    </div>
                    <h3 class="text-lg font-bold px-2">{{ feature.title }}</h3>
                    <p class="text-sm text-black/70 px-2">{{ feature.description }}</p>
                </div>
            </div>

            <div 
                class="mt-4 rounded-b-2xl p-16 min-h-[400px]" 
                :style="panelStyle"
            >
                <div class="flex flex-col lg:flex-row items-stretch h-full min-h-[300px]">

                    <!-- Text -->
                    <div class="w-full lg:basis-1/3 flex-none">
                        <h3 class="text-2xl max-w-xs font-bold">
                            {{ selectedFeature.infoHeading }}
                        </h3>
                        <p class="mt-2 max-w-2xs text-md text-black">
                            {{ selectedFeature.infoDescription }}
                        </p>
                    </div>

                    <!-- Gallery -->
                    <div
                        v-if="selectedFeature.gallery"
                        class="w-full lg:basis-2/3 flex-none h-[420px] grid grid-cols-[1fr_2fr] gap-3"
                    >
                        <!-- Left column (2 stacked images) -->
                        <div class="grid grid-rows-2 gap-3">
                            <img
                                :src="selectedFeature.gallery[0]"
                                class="w-full h-full rounded-lg object-cover shadow-lg"
                            />
                            <img
                                :src="selectedFeature.gallery[1]"
                                class="w-full h-full rounded-lg object-cover shadow-lg"
                            />
                        </div>

                        <!-- Right large image -->
                        <img
                            :src="selectedFeature.gallery[2]"
                            class="w-full h-full rounded-lg object-cover shadow-lg"
                        />
                    </div>

                </div>
            </div>
        </Container>
    </section>
</template>

<style scoped>
    .feature-card.is-active {
        position: relative;
    }

    .feature-card.is-active::after {
        content: '';
        position: absolute;
        left: calc(-1 * var(--fillet-left, 16px));
        right: calc(-1 * var(--fillet-right, 16px));
        bottom: -16px;
        height: 16px;
        background:
            radial-gradient(circle at 0% 0%, transparent 0 var(--fillet-left, 16px), var(--panel) var(--fillet-left, 16px)) bottom left / var(--fillet-left, 16px) 16px no-repeat,
            radial-gradient(circle at 100% 0%, transparent 0 var(--fillet-right, 16px), var(--panel) var(--fillet-right, 16px)) bottom right / var(--fillet-right, 16px) 16px no-repeat,
            linear-gradient(var(--panel), var(--panel)) var(--fillet-left, 16px) 0 / calc(100% - var(--fillet-left, 16px) - var(--fillet-right, 16px)) 100% no-repeat;
    }
</style>
