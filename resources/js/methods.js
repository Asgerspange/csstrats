import {
    SERVER_TICK_RATE,
    TOTAL_ROUND_DURATION,
    GRENADE_ACTIVATION_TICKS,
    FLASH_ACTIVATION_TICKS,
    SMOKE_RADIUS,
    INFERNO_RADIUS,
    GRENADE_EXPLOSION_RADIUS,
    FLASH_RADIUS,
    FEED_LINGER_SECONDS,
    MAX_ZOOM,
    MIN_ZOOM,
    BULLET_FADE_TICKS,
    GRENADE_TRAJECTORY_FADE_TICKS,
    FLASH_FADE_TICKS,
    NADE_FADE_TICKS,
    C4_DELAY_TICKS,
} from '@/PagesNew/Demo/2D/options';

export function handleDemoScroll(event, mapX, mapY, scale) {
    const delta = (event.wheelDelta || -event.detail) > 0 ? 0.1 : -0.1;
    const oldScale = scale;
    let newScale = oldScale + delta;
    
    if (newScale < MIN_ZOOM) newScale = MIN_ZOOM;
    if (newScale > MAX_ZOOM) newScale = MAX_ZOOM;
    
    const x = event.offsetX;
    const y = event.offsetY;
    
    const zoomBy = newScale / oldScale;

    return {
        x: x - (x - mapX) * zoomBy,
        y: y - (y - mapY) * zoomBy,
        scale: newScale
    };
}

export const tickToSecondsRemaining = (t, currentTick, round) => {
    if (!currentTick.value || !round.value.meta) {
        return null;
    }
    
    const start = Math.max(0, round.value.meta.start ?? 0, round.value.meta.freeze_end ?? 0);
    return Math.max(0, TOTAL_ROUND_DURATION - Math.max(0, (t - start) / SERVER_TICK_RATE));
};