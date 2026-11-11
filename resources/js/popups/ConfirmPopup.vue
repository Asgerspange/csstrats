<template>
    <div style="position: relative;" class="vert-align">
        <i @click="openPopup" class="text-secondary material-symbols action-icon delete-icon">delete</i>
        <div
            v-if="isOpen"
            class="popup-container"
            :style="popupStyle"
            @click.self="closePopup"
        >
            <slot name="header">
                <h2>Default Header</h2>
            </slot>
            <slot name="content">
                <p>Default content goes here...</p>
            </slot>
            <slot name="actions">
                <Button severity="secondary" @click="closePopup">Close</Button>
            </slot>
        </div>
    </div>
</template>

<script setup>
import Button from '@/components/volt/Button.vue';
import bus from '../eventbus';
import { ref, defineProps, computed } from 'vue';

defineProps(['icon']);

const isMobile = computed(() => window.innerWidth < 768);

const isOpen = ref(false);
const popupStyle = ref({});

function openPopup(event) {
    isOpen.value = !isOpen.value;

    const rect = event.currentTarget.getBoundingClientRect();

    const clickX = event.clientX - rect.left;
    const clickY = event.clientY - rect.top;


    popupStyle.value = {
        position: 'absolute',
        top: `${clickY + 15}px`,
        left: isMobile.value ? `${clickX - 200}px` : `${clickX - 300}px`,
    };
}

function closePopup() {
    isOpen.value = false;
}

bus.on('close-popup', () => {
    isOpen.value = false;
});

</script>

<style lang="scss" scoped>
.popup-container {
    background: var(--card-primary-bg);
    border-radius: 10px;
    padding: 20px;
    width: 300px;
    box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
    text-align: center;
    z-index: 1000;
    color: white;
}

.action-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background-color: var(--card-btn-bg);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    
    &:hover {
        background-color: #3a3a50;
        transform: translateY(-2px);
    }
    
    &.share-icon:hover {
        color: #58bc82;
    }
    
    &.delete-icon:hover {
        color: #ff5252;
    }
}

@media (max-width: 768px) {
    .popup-container {
        width: 200px;
    }
}
</style>
