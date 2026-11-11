<template>
    <div class="round-viewer" :class="{ 'fullscreen': isFullscreen }">
        <div id="canvas-container" ref="canvasContainer">
            <canvas id="mapCanvas" ref="mapCanvas"></canvas>
            <canvas id="gameCanvas" ref="gameCanvas"></canvas>
        </div>
        
        <div id="overlay">
            <div id="viewer-controls">
                <button 
                class="fullscreen-button" 
                @click="toggleFullscreen"
                title="Toggle fullscreen"
                >
                <i class="material-symbols">{{ isFullscreen ? 'fullscreen_exit' : 'fullscreen' }}</i>
            </button>
        </div>
        
        <!-- Only visible in fullscreen mode -->
        <div id="scoreboard-container" v-if="isFullscreen">
            <TeamScoreboard 
            v-for="team in [['ct', 'CT', 'CT', 'Counter Terrorists'], ['t', 'T', 'TERRORIST', 'Terrorists']]"
            :key="team[0]" 
            :name="team[1]"
            :score="round.meta[team[1] + '_score'] ?? 0"
            :players="currentTick?.players.filter((player) => player.team_name === team[0])"
            :getWeapon="getWeapon" 
            :getStashedWeapon="getStashedWeapon"
            :getUtilityItems="getUtilityItems" 
            />
        </div>
        
        <div id="round-info">
            <!-- Round clock always visible -->
            <div id="round-clock" :class="{ 'compact': !isFullscreen }">
                <div class="round-number">ROUND {{ round.meta?.round_num ?? 1 }}</div>
                <div class="time-remaining">
                    <template v-if="secondsRemaining !== null">
                        {{ secondsToClock(secondsRemaining) }}
                    </template>
                    <template v-else>
                        1:55
                    </template>
                </div>
            </div>
            
            <!-- Round feed only visible in fullscreen -->
            <div id="round-feed" v-if="isFullscreen">
                <div 
                class="feed-item" 
                v-for="event in currentEvents" 
                :key="event.victim_steamid"
                >
                <span class="event-time">{{ secondsToClock(tickToSecondsRemaining(event.tick, currentTick, round)) }}</span>
                
                <img 
                :src="demoIcons.blind_kill?.base64" 
                alt="blind" 
                class="event-icon"
                v-if="event.attackerblind" 
                />
                
                <span 
                class="player-name attacker"
                :style="{ color: getTeamColor(event.attacker_side) }"
                >
                {{ event.attacker_name }}
            </span>
            
            <template v-if="(event.assister_steamid && event.assister_steamid != 'None')">
                <span class="assist-plus">+</span>
                
                <img 
                :src="demoIcons.flashbang_assist?.base64" 
                alt="flashbang"
                class="event-icon"
                v-if="event.assistedflash" 
                />
                
                <span 
                class="player-name assister"
                :style="{ color: getTeamColor(event.assister_side) }"
                >
                {{ event.assister_name }}
            </span>
        </template>
        
        <img 
        :src="getWeapon(event.weapon).base64" 
        alt="weapon"
        class="weapon-icon"
        />
        
        <img 
        :src="demoIcons.noscope?.base64" 
        alt="noscope"
        class="event-icon"
        v-if="event.noscope" 
        />
        
        <img 
        :src="demoIcons.penetrate?.base64" 
        alt="wallbang"
        class="event-icon"
        v-if="event.penetrate" 
        />
        
        <img 
        :src="demoIcons.smoke_kill?.base64" 
        alt="thrusmoke"
        class="event-icon"
        v-if="event.thrusmoke" 
        />
        
        <img 
        :src="demoIcons.icon_headshot?.base64" 
        alt="headshot"
        class="event-icon"
        v-if="event.headshot" 
        />
        
        <span 
        class="player-name victim"
        :style="{ color: getTeamColor(event.victim_side) }"
        >
        {{ event.victim_name }}
    </span>
</div>
</div>
</div>

<div id="playback-controls">
    <div class="control-buttons">
        <button 
        v-if="allowPlay"
        class="playback-button"
        @click="togglePlayback()"
        >
        <i class="material-symbols">{{ playing ? 'pause' : 'play_arrow' }}</i>
    </button>
    
    <button 
    v-else
    class="playback-button loading"
    disabled
    >
    <i class="material-symbols loading-icon">autorenew</i>
</button>

<button 
class="speed-button"
@click="togglePlaybackSpeed"
>
x{{ playbackSpeed }}
</button>
</div>

<div class="timeline-slider">
    <Slider 
    v-model="currentTickIndex" 
    :min="0" 
    :max="round?.tickNumbers.length - 1" 
    :step="1"
    class="dark-slider"
    />
</div>
</div>
</div>
</div>
</template>

<script setup>
import { ref, onUnmounted, onMounted, watch, defineProps, computed, nextTick, version } from 'vue';
import getScale from '@/scales.js';
import TeamScoreboard from '@/PagesNew/Demo/2D/TeamScoreboard.vue';
import { convertFloatToHexOpacity, secondsToClock } from '@/helpers';
import {
    SERVER_TICK_RATE,
    GRENADE_ACTIVATION_TICKS,
    FLASH_ACTIVATION_TICKS,
    SMOKE_RADIUS,
    INFERNO_RADIUS,
    GRENADE_EXPLOSION_RADIUS,
    FLASH_RADIUS,
    FEED_LINGER_SECONDS,
    BULLET_FADE_TICKS,
    GRENADE_TRAJECTORY_FADE_TICKS,
    FLASH_FADE_TICKS,
    NADE_FADE_TICKS,
    C4_DELAY_TICKS,
    UTILITIES,
    PISTOLS
} from '@/PagesNew/Demo/2D/options';
import { handleDemoScroll, tickToSecondsRemaining } from '@/methods';
import useImages from '@/composables/useImages';
import Bus from '@/eventbus';
import Slider from '@/components/volt/Slider.vue';

const props = defineProps({
    demoId: String,
    roundIndex: Number,
    map: String,
    isCustom: {
        type: Boolean,
        default: false
    },
    version: {
        type: Number,
        default: 1
    }
});

const round = ref({
    ticks: {},
    tickNumbers: [],
    meta: {},
    smokes: [],
    infernos: [],
    kills: {}
});

const { Images } = useImages();
const { data: weaponImages } = Images.get('weapon');
const { data: icons } = Images.get('demo_icon');

const isFullscreen = ref(false);
const allowPlay = ref(false);
const playing = ref(false);
const playbackSpeed = ref(1);
const tickRate = computed(() => SERVER_TICK_RATE / (1 / playbackSpeed.value));

const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        // Enter fullscreen using the browser's native Fullscreen API
        // Important: Use the entire round-viewer as the fullscreen element, not just the canvas container
        const roundViewer = canvasContainer.value.closest('.round-viewer');
        roundViewer.requestFullscreen().then(() => {
            isFullscreen.value = true;
            // Allow time for the transition to complete before resizing canvases
            setTimeout(() => {
                handleResize();
                // Redraw map and recenter it when toggling fullscreen
                drawMap();
                render();
            }, 300);
        }).catch(err => {
            console.error(`Error attempting to enable fullscreen: ${err.message}`);
        });
    } else {
        // Exit fullscreen
        document.exitFullscreen().then(() => {
            isFullscreen.value = false;
            // Allow time for the transition to complete before resizing canvases
            setTimeout(() => {
                handleResize();
                // Redraw map and recenter it when toggling fullscreen
                drawMap();
                render();
            }, 300);
        }).catch(err => {
            console.error(`Error attempting to exit fullscreen: ${err.message}`);
        });
    }
};


const secondsRemaining = computed(() => {
    return tickToSecondsRemaining(round.value?.tickNumbers[currentTickIndex.value], currentTick, round);
});

const currentTickIndex = ref(0);
const currentTick = computed(() => {
    if (currentTickIndex.value >= 0 && currentTickIndex.value < round.value?.tickNumbers.length) {
        return round.value.ticks[round.value?.tickNumbers[currentTickIndex.value]];
    }
    return null;
});

const currentEvents = computed(() => {
    if (!currentTickIndex.value) return [];
    
    const startIndex = Math.max(0, currentTickIndex.value - FEED_LINGER_SECONDS * SERVER_TICK_RATE);
    const endIndex = Math.min(currentTickIndex.value, round.value?.tickNumbers.length);
    
    const events = [];
    const displayedSteamids = new Set();
    
    for (let i = startIndex; i < endIndex; i++) {
        const tickNumber = round.value?.tickNumbers[i];
        if (!(tickNumber in round.value.kills)) {
            continue;
        }
        
        events.push(...round.value.kills[tickNumber].filter((kill) => !displayedSteamids.has(kill.victim_steamid)));
        round.value.kills[tickNumber].forEach((kill) => displayedSteamids.add(kill.victim_steamid));
    }
    
    return events;
});

const cachedImage = ref(null);
const mapPosition = ref({ x: 0, y: 0, scale: 1 });

const demoIcons = computed(() => Object.fromEntries((icons.value || []).map((icon) => [icon.name, icon])));
const weapons = computed(() => Object.fromEntries((weaponImages.value || []).map((weapon) => [weapon.name.toLowerCase(), weapon])));

const getTeamColor = (team) => {   
    return team === 'ct' ? '#7a9bcb' : '#d1c185';
};

const steamIdTeamLookup = computed(() => {
    const firstTick = round.value.ticks[round.value?.tickNumbers[0]];
    if (!firstTick) return {};
    
    return Object.fromEntries(firstTick.players.map((player) => [player.steamid, player.team_name]));
});

const getWeapon = (name) => {
    if (!name) return { base64: "", svg_path: "" };
    
    name = name.toLowerCase();
    
    const weaponAliases = {
       "high explosive grenade": "he-grenade",
        "he_grenade": "he-grenade",
        "smoke": "smoke-grenade",
        "smoke grenade": "smoke-grenade",
        "incendiary grenade": "incendiary-grenade",
        "dual berettas": "dual-berettas",
        "desert eagle": "desert-eagle",
        "galil ar": "galil-ar",
        "decoy": "decoy-grenade",
        "incendiary_grenade": "incendiary-grenade",
        "usp_silencer": "usp-s",
        'glock': 'glock-18',
        'hkp2000': 'p2000',
        'elite': 'dual-berettas',
        'ssg 08': 'ssg-08',
        'ssg08': 'ssg-08',
        'scar 20': 'scar-20',
        'fiveseven': 'five-seven',
        'ak47': 'ak-47',
        'galilar': 'galil-ar',
        'mac10': 'mac-10',
        'm4a1_silencer': 'm4a1-s',
        'deagle': 'desert-eagle',
        'tec9': 'tec-9',
        'hegrenade': 'he-grenade',
        'm4a1': 'm4a1-s',
        'decoy grenade': 'decoy-grenade',
        'ump45': 'ump-45',
        'karambit': 'knife_karambit',
        'butterfly knife': 'knife_butterfly',
        'kukri knife': 'knife_kukri',
        'ursus knife': 'knife_ursus',
        'm9 bayonet': 'knife_m9_bayonet',
        'navaja knife': 'knife_gypsy_jackknife',
        'stiletto knife': 'knife_stiletto',
        'flip knife': 'knife_flip',
        'gut knife': 'knife_gut',
        'shadow daggers': 'knife_push',
        'survival knife': 'knife_canis',
        'skeleton knife': 'knife_skeleton',
        'paracord knife': 'knife_cord',
        'talon knife': 'knife_widowmaker',
        'nomad knife': 'knife_outdoor',
        'zeus x27': 'zeus',
        'c4 explosive': 'c4',
        'cflashbang': 'flashbang',
        'chegrenade': 'he-grenade',
        'csmokegrenade': 'smoke-grenade',
        'cincendiarygrenade': 'incendiary-grenade',
        'cmolotovgrenade': 'molotov',
        'csmokegrenadeprojectile': 'smoke-grenade',
        'chegrenadeprojectile': 'he-grenade',
        'cmolotovprojectile': 'molotov',
        'cflashbangprojectile': 'flashbang',
        "bowie knife": "knife_survival_bowie",
        "planted_c4": "c4",
        "huntsman knife": "knife_tactical",
        'classic knife': 'knife_css',
        "r8 revolver": "r8-revolver"
    };
    
    const mappedName = weaponAliases[name] || name;
    
    if (!weapons.value[mappedName.toLowerCase()]) {
        console.error('Unknown weapon:', name);
        return { base64: "", svg_path: "" };
    }
    
    return weapons.value[mappedName];
};

const getStashedWeapon = (player) => {
    let stashed = player.inventory.filter(
    (item) => !UTILITIES.includes(item)
    && item !== player.active_weapon_name
    && item.toLowerCase().indexOf("knife") < 0
    && item.toLowerCase() !== "bayonet"
    && item.toLowerCase() !== "c4"
    );
    
    if (stashed.length > 1) {
        stashed = stashed.filter((item) => !PISTOLS.includes(item));
    }
    
    return stashed[0];
};

const getUtilityItems = (player) => {
    return [...(player.has_defuser ? ['Defuser'] : []), ...player.inventory.filter((item) => UTILITIES.includes(item)).sort()];
};

const canvasContainer = ref(null);
const mapCanvas = ref(null);
const gameCanvas = ref(null);

const drawMap = () => {
    // Center map on canvas
    let image = null;
    
    function _draw() {
        if (!canvasContainer.value || !mapCanvas.value || !gameCanvas.value) return;
        
        // Draw the background map when the component is mounted
        mapCanvas.value.width = canvasContainer.value.clientWidth;
        mapCanvas.value.height = canvasContainer.value.clientHeight;
        gameCanvas.value.width = canvasContainer.value.clientWidth;
        gameCanvas.value.height = canvasContainer.value.clientHeight;
        
        const ctx = mapCanvas.value.getContext('2d');
        
        if (!cachedImage.value) return;
        
        // Adjust the scale based on the container size to ensure the map is visible
        // Use a scaling approach that considers both width and height
        const containerWidth = canvasContainer.value.offsetWidth;
        const containerHeight = canvasContainer.value.offsetHeight;
        const imageWidth = cachedImage.value.width;
        const imageHeight = cachedImage.value.height;
        
        // Calculate the scale that will fit the map in the container
        const scaleX = containerWidth / imageWidth;
        const scaleY = containerHeight / imageHeight;
        const fitScale = Math.min(scaleX, scaleY) * 0.9; // 90% of the fit scale for a margin
        
        // Set the scale if we're in fullscreen mode or first draw
        if (isFullscreen.value || mapPosition.value.scale === 1) {
            mapPosition.value.scale = fitScale;
        }
        
        // Center the map
        const x = Math.floor((containerWidth - imageWidth * mapPosition.value.scale) / 2);
        const y = Math.floor((containerHeight - imageHeight * mapPosition.value.scale) / 2);
        
        mapPosition.value = {
            x,
            y,
            scale: mapPosition.value.scale
        };
        
        ctx.setTransform(1, 0, 0, 1, x, y);
        ctx.drawImage(cachedImage.value, 0, 0);
    }
    
    if (cachedImage.value) {
        _draw();
    } else {
        const image = new Image();
        image.src = `/images/demo/${props.map}.svg`;
        image.onload = () => {
            cachedImage.value = image; // Cache the loaded image
            _draw();
        };
    }
};

const toWorldCoords = (x, y) => {
    x = (x - mapPosition.value.x) / mapPosition.value.scale;
    y = (y - mapPosition.value.y) / mapPosition.value.scale;
    return { x, y };
};

const toGameCoords = (x, y) => {
    const scale = getScale(props.map);
    
    x = (x - scale.pos_x) / scale.scale;
    y = (scale.pos_y - y) / scale.scale;
    
    return { x, y };
};

const render = () => {
    requestAnimationFrame(() => {
        if (!mapCanvas.value || !gameCanvas.value) return;
        
        const currentTickNumber = round.value?.tickNumbers[currentTickIndex.value];
        const mapCtx = mapCanvas.value.getContext('2d');
        
        const scale = getScale(props.map);
        
        const infernoRadius = INFERNO_RADIUS / scale.scale;
        const smokeRadius = SMOKE_RADIUS / scale.scale;
        const explosionRadius = GRENADE_EXPLOSION_RADIUS / scale.scale;
        const flashRadius = FLASH_RADIUS / scale.scale;
        
        // Clear the map canvas
        let topLeft = toWorldCoords(-mapCanvas.value.width * 2, -mapCanvas.value.height * 2);
        let bottomRight = toWorldCoords(mapCanvas.value.width * 2, mapCanvas.value.height * 2);
        mapCtx.clearRect(topLeft.x, topLeft.y, bottomRight.x - topLeft.x, bottomRight.y - topLeft.y);
        
        // Draw the map image
        mapCtx.setTransform(mapPosition.value.scale, 0, 0, mapPosition.value.scale, mapPosition.value.x, mapPosition.value.y);
        if (cachedImage.value) {
            mapCtx.drawImage(cachedImage.value, 0, 0);
        }
        
        const ctx = gameCanvas.value.getContext('2d');
        ctx.setTransform(mapPosition.value.scale, 0, 0, mapPosition.value.scale, mapPosition.value.x, mapPosition.value.y);
        ctx.clearRect(topLeft.x, topLeft.y, bottomRight.x - topLeft.x, bottomRight.y - topLeft.y);
        
        if (!currentTick.value) return;
        
        // Draw players
        currentTick.value.players.forEach((player) => {
            if (!player || player.health <= 0) return; // Skip dead players
            const { x, y } = toGameCoords(player.X, player.Y);
            ctx.save(); // Save the current drawing state
            ctx.translate(x, y); // Move the origin to the player's position
            const teamColor = getTeamColor(player.team_name);
            
            // Draw player weapon
            const weapon = getWeapon(player.active_weapon_name);
            if (weapon.svg_path) {
                ctx.save();
                // Draw the weapon SVG path scaled down
                const path = new Path2D(weapon.svg_path);
                ctx.fillStyle = teamColor;
                ctx.scale(0.3, 0.3);
                ctx.translate(50, -35);
                ctx.fill(path);
                ctx.restore();
            }
            
            // Draw box behind player name
            ctx.fillStyle = 'rgba(0, 0, 0, 0.4)';
            ctx.font = 'bold 14px "Bai Jamjuree", sans-serif';
            const nameWidth = ctx.measureText(player.name).width;
            ctx.fillRect(-nameWidth / 2 - 5, -35, nameWidth + 10, 23);
            
            // Draw player name
            ctx.fillStyle = teamColor;
            ctx.textAlign = 'center';
            ctx.fillText(player.name, 0, -20);
            
            // Rotate and draw player dot
            ctx.rotate(((-player.yaw) * Math.PI) / 180); // Rotate based on yaw, flipped
            
            // Define the path for the player dot (assuming SVG origin is (0,0) now)
            const path = new Path2D(`M 6 -7.44 a 9.6 9.6 180 0 0 -6 -2.16 a 9.6 9.6 180 0 0 0 19.2 a 9.6 9.6 180 0 0 6 -2.16 l 9.84 -7.44 z`);
            ctx.fillStyle = teamColor;
            ctx.fill(path);
            
            // Undo the rotation
            ctx.rotate((player.yaw * Math.PI) / 180);
            
            // Draw c4
            if (player.inventory.includes('C4')) {
                ctx.save();
                const c4 = getWeapon('c4');
                const path = new Path2D(c4.svg_path);
                ctx.fillStyle = "#cd6133";
                ctx.scale(0.25, 0.25);
                ctx.translate(-20, -20);
                ctx.fill(path);
                ctx.restore();
            }
            
            ctx.restore(); // Restore the drawing state
        });
        
        // Draw bullets
        for (let i = 0; i < BULLET_FADE_TICKS && currentTickIndex.value - i >= 0; i++) {
            if (round.value.ticks[currentTickNumber]?.weapon_fires) {
                round.value.ticks[currentTickNumber].weapon_fires.forEach((weapon_fire) => {
                    const { x, y } = toGameCoords(weapon_fire.player_X, weapon_fire.player_Y);
                    const teamColor = getTeamColor(weapon_fire.player_team_name);
                    ctx.save();
                    
                    ctx.translate(x, y);
                    ctx.strokeStyle = teamColor + convertFloatToHexOpacity(1 - i / BULLET_FADE_TICKS);
                    ctx.lineWidth = 1;
                    
                    // Draw a line from the coords at angle calculated from yaw
                    ctx.beginPath();
                    ctx.moveTo(0, 0);
                    ctx.lineTo(1000 * Math.cos((-weapon_fire.player_yaw * Math.PI) / 180), 1000 * Math.sin((-weapon_fire.player_yaw * Math.PI) / 180));
                    ctx.stroke();
                    
                    ctx.restore();
                });
            }
        }
        
        // Draw c4
        if (currentTick.value.bomb) {
            const bomb = currentTick.value.bomb;
            const { x, y } = toGameCoords(bomb.X, bomb.Y);
            ctx.save();
            ctx.translate(x, y);
            ctx.scale(0.25, 0.25);
            ctx.translate(-20, -20);
            const path = new Path2D(getWeapon('c4').svg_path);
            ctx.fillStyle = "#cd6133";
            ctx.fill(path);
            ctx.restore();
            
            // Draw the bomb timer
            if (bomb.planted_ticks !== null) {
                const remainingTicks = C4_DELAY_TICKS - bomb.planted_ticks;
                const remainingSeconds = remainingTicks / SERVER_TICK_RATE;
                const remainingPercentage = remainingSeconds / 40;
                const path = new Path2D(`M 0 0 m -10 0 a 10 10 90 1 0 20 0 a 10 10 90 1 0 -20 0`);
                ctx.save();
                ctx.translate(x, y);
                ctx.fillStyle = 'rgba(0, 0, 0, 0.2)';
                ctx.fill(path);
                
                const startAngle = -Math.PI / 2; // Start at the top (12 o'clock)
                const endAngle = startAngle + remainingPercentage * 2 * Math.PI; // Partial arc based on remaining time
                
                // Draw partial stroke
                ctx.beginPath();
                ctx.arc(0, 0, 10, startAngle, endAngle, false);
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.4)';
                ctx.lineWidth = 1.5;
                ctx.stroke();
                
                // Write the remaining time on the bomb
                ctx.fillStyle = 'rgba(255, 255, 255, 1)';
                ctx.font = 'bold 6px Bai Jamjuree';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(remainingSeconds.toFixed(1), 0, 0);
                ctx.restore();
            }
        }
        
        // Draw smokes
        if (currentTick.value.smokes) {
            currentTick.value.smokes.forEach((smoke) => {
                const { x, y } = toGameCoords(smoke.X, smoke.Y);
                ctx.save();
                ctx.translate(x, y);
                const remainingPercentage = 1 - (currentTickNumber - smoke.start_tick) / (smoke.end_tick - smoke.start_tick);
                
                const path = new Path2D(`M 0 0 m -${smokeRadius} 0 a ${smokeRadius} ${smokeRadius} 0 1 0 ${smokeRadius * 2} 0 a ${smokeRadius} ${smokeRadius} 0 1 0 -${smokeRadius * 2} 0`);
                ctx.fillStyle = 'rgba(83, 92, 104, 0.9)';
                ctx.fill(path);
                
                // Calculate angles for partial stroke
                const startAngle = -Math.PI / 2; // Start at the top (12 o'clock)
                const endAngle = startAngle + remainingPercentage * 2 * Math.PI; // Partial arc based on remaining time
                
                // Draw partial stroke
                ctx.beginPath();
                ctx.arc(0, 0, smokeRadius, startAngle, endAngle, false);
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 1.5;
                ctx.stroke();
                
                ctx.restore();
            });
        }
        
        // Draw infernos
        if (currentTick.value.infernos) {
            currentTick.value.infernos.forEach((inferno) => {
                const { x, y } = toGameCoords(inferno.X, inferno.Y);
                ctx.save();
                ctx.translate(x, y);
                const remainingPercentage = 1 - (currentTickNumber - inferno.start_tick) / (inferno.end_tick - inferno.start_tick);
                
                const path = new Path2D(`M 0 0 m -${infernoRadius} 0 a ${infernoRadius} ${infernoRadius} 0 1 0 ${infernoRadius * 2} 0 a ${infernoRadius} ${infernoRadius} 0 1 0 -${infernoRadius * 2} 0`);
                ctx.fillStyle = 'rgba(255, 0, 0, 0.5)';
                ctx.fill(path);
                
                // Calculate angles for partial stroke
                const startAngle = -Math.PI / 2; // Start at the top (12 o'clock)
                const endAngle = startAngle + remainingPercentage * 2 * Math.PI; // Partial arc based on remaining time
                
                // Draw partial stroke
                ctx.beginPath();
                ctx.arc(0, 0, infernoRadius, startAngle, endAngle, false);
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 1.5;
                ctx.stroke();
                ctx.restore();
            });
        }
        
        // Draw grenades
        if (currentTick.value.grenades) {
            currentTick.value.grenades.forEach((grenade) => {
                if (grenade.trajectory && grenade.trajectory.length > 0) {
                    let initCoords = toGameCoords(grenade.trajectory[0].X, grenade.trajectory[0].Y);
                    ctx.save();
                    
                    ctx.beginPath();
                    ctx.moveTo(initCoords.x, initCoords.y);
                    
                    grenade.trajectory.forEach((point) => {
                        ctx.lineWidth = 1;
                        if (point.tick < currentTickNumber - GRENADE_TRAJECTORY_FADE_TICKS) return;
                        ctx.strokeStyle = getTeamColor(steamIdTeamLookup.value[grenade.thrower_steamid]) + convertFloatToHexOpacity(1 - (currentTickNumber - point.tick) / GRENADE_TRAJECTORY_FADE_TICKS);
                        const { x, y } = toGameCoords(point.X, point.Y);
                        ctx.lineTo(x, y);
                        ctx.stroke();
                        ctx.beginPath();
                        ctx.moveTo(x, y);
                    });
                    
                    if (grenade.grenade_type === 'smoke') {
                        const entity = round.value.smokes.find((smoke) => smoke.entity_id === grenade.entity_id);
                        if (entity && entity.start_tick <= currentTickNumber) {
                            ctx.restore();
                            return;
                        }
                    }
                    
                    const { x, y } = toGameCoords(grenade.X, grenade.Y);
                    ctx.translate(x, y);
                    
                    if (grenade.grenade_type === 'he_grenade') {
                        const firstTick = grenade.trajectory[0].tick;
                        const grenadeTick = grenade.tick - firstTick - 1;
                        
                        if (grenadeTick >= GRENADE_ACTIVATION_TICKS) {
                            const fadeOpacity = (1 - (grenadeTick - GRENADE_ACTIVATION_TICKS) / NADE_FADE_TICKS) * 0.4;
                            const path = new Path2D(`M 0 0 m -${explosionRadius} 0 a ${explosionRadius} ${explosionRadius} 0 1 0 ${explosionRadius * 2} 0 a ${explosionRadius} ${explosionRadius} 0 1 0 -${explosionRadius * 2} 0`);
                            ctx.fillStyle = `rgba(194, 54, 22, ${fadeOpacity})`;
                            ctx.fill(path);
                            
                            ctx.restore();
                            return;
                        }
                    }
                    
                    if (grenade.grenade_type === "flashbang") {
                        const firstTick = grenade.trajectory[0].tick;
                        const grenadeTick = grenade.tick - firstTick + 1;
                        
                        if (grenadeTick >= FLASH_ACTIVATION_TICKS) {
                            const fadeOpacity = (1 - (grenadeTick - FLASH_ACTIVATION_TICKS) / FLASH_FADE_TICKS) * 0.8;
                            
                            const path = new Path2D(`M 0 0 m -${flashRadius} 0 a ${flashRadius} ${flashRadius} 0 1 0 ${flashRadius * 2} 0 a ${flashRadius} ${flashRadius} 0 1 0 -${flashRadius * 2} 0`);
                            ctx.fillStyle = `rgba(255, 255, 255, ${fadeOpacity})`;
                            ctx.fill(path);
                            
                            ctx.restore();
                            return;
                        }
                    }
                    
                    ctx.translate(-5, -8);
                    ctx.scale(0.4, 0.4);
                    
                    const path = new Path2D(getWeapon(grenade.grenade_type).svg_path);
                    ctx.fillStyle = getTeamColor(steamIdTeamLookup.value[grenade.thrower_steamid]);
                    ctx.fill(path);
                    ctx.restore();
                }
            });
        }
    });
};

const interval = ref(null);

const togglePlaybackSpeed = () => {
    switch (playbackSpeed.value) {
        case 1:
        playbackSpeed.value = 2;
        break;
        case 2:
        playbackSpeed.value = 4;
        break;
        case 4:
        playbackSpeed.value = 0.25;
        break;
        case 0.25:
        playbackSpeed.value = 0.5;
        break;
        case 0.5:
        playbackSpeed.value = 1;
        break;
        default:
        playbackSpeed.value = 1;
        break;
    }
    if (playing.value) {
        playDemo();
    }
};

const togglePlayback = () => {
    if (playing.value) {
        pauseDemo();
    } else {
        playDemo();
    }
};

const playDemo = () => {
    playing.value = true;
    clearInterval(interval.value);
    
    interval.value = setInterval(() => {
        if (currentTickIndex.value < round.value?.tickNumbers.length - 1) {
            while (round.value?.tickNumbers[currentTickIndex.value] < round.value.meta.freeze_end) {
                currentTickIndex.value += 1;
            }
            
            currentTickIndex.value += 1;
        } else {
            // Loop back to beginning when done
            currentTickIndex.value = 0;
            pauseDemo();
        }
    }, 1000 / tickRate.value);
};

const pauseDemo = () => {
    clearInterval(interval.value);
    playing.value = false;
};

const resetDemo = () => {
    clearInterval(interval.value);
    playing.value = false;
    currentTickIndex.value = 0;
    render();
};

// Initialize round data when the component loads
const initializeRound = async () => {
    if (props.roundData) {
        // If round data is directly provided as a prop
        round.value = props.roundData;
        allowPlay.value = true;
    } else {
        // Otherwise load via API
        await loadRound();
    }
};

// Load round data from API
const loadRound = async () => {
    try {
        allowPlay.value = false;

        const roundData = await _loadRoundData(props.demoId, props.roundIndex, props.version);
        if (roundData) {
            round.value = roundData;
            allowPlay.value = true;
        }
    } catch (error) {
        console.error("Failed to load round data:", error);
    }
};

// Function to load round data
const _loadRoundData = async (demoId, roundIndex = 1, version = 1) => {

    try {
        // Unified endpoint: serves both curated admin demos and personal demos
        // (access-scoped server-side).
        const response = await fetch(
            `/api/demo-chunk?demo_id=${demoId}&round_index=${roundIndex}`
        );

        const data = await response.json();
        const sortedTicks = Object.keys(data.ticks)
        .map(Number)
        .sort((a, b) => a - b);
        
        if (response.ok && sortedTicks.length > 0) {
            if (version < 2 || version > 3) {
                sortedTicks.forEach((tick) => {
                    data.ticks[tick].smokes = [];
                    data.ticks[tick].infernos = [];
                });
                
                data.smokes.forEach((smoke) => {
                    if (smoke.end_tick > sortedTicks[sortedTicks.length - 1]) {
                        smoke.end_tick = sortedTicks[sortedTicks.length - 1];
                    }
                    
                    for (let i = smoke.start_tick; i <= smoke.end_tick; i++) {
                        if (data.ticks[i]) {
                            data.ticks[i].smokes.push(smoke);
                        }
                    }
                });
                
                data.infernos.forEach((inferno) => {
                    if (inferno.end_tick > sortedTicks[sortedTicks.length - 1]) {
                        inferno.end_tick = sortedTicks[sortedTicks.length - 1];
                    }
                    
                    for (let i = inferno.start_tick; i <= inferno.end_tick; i++) {
                        if (data.ticks[i]) {
                            data.ticks[i].infernos.push(inferno);
                        }
                    }
                });
                
                // Keep track of flashes to extend their life into the next FLASH_FADE_TICKS ticks
                const flashes = {};
                
                // Store the trajectory of grenades in each tick
                const grenadeTrajectories = {};
                Object.entries(data.ticks).forEach(([tick, tick_data]) => {
                    tick_data.grenades.forEach((grenade) => {
                        if (!grenadeTrajectories[grenade.entity_id]) {
                            grenadeTrajectories[grenade.entity_id] = [];
                        }
                        grenadeTrajectories[grenade.entity_id].push({
                            X: grenade.X,
                            Y: grenade.Y,
                            tick: Number(tick),
                        });
                        
                        grenade.trajectory = [
                        ...grenadeTrajectories[grenade.entity_id],
                        ];
                        
                        if (grenade.grenade_type === "flashbang") {
                            flashes[grenade.entity_id] = grenade;
                        }
                    });
                });
                
                Object.values(flashes).forEach((flash) => {
                    for (let i = 0; i < FLASH_FADE_TICKS; i++) {
                        const nextTick = flash.tick + i + 1;
                        if (data.ticks[nextTick]) {
                            data.ticks[nextTick].grenades.push({
                                ...flash,
                                tick: nextTick,
                            });
                        }
                    }
                });
            }
            
            return {
                ticks: data.ticks,
                tickNumbers: sortedTicks,
                meta: data.meta,
                smokes: data.smokes,
                infernos: data.infernos,
                kills: data.kills,
            };
        } else {
            console.error(
            "Error loading chunk:",
            data.error || "No data received"
            );
            return null;
        }
    } catch (error) {
        console.error("Failed to fetch data:", error);
        return null;
    }
};

// Mouse interaction handlers
const mousedown = ref(false);

const handleMouseMove = (event) => {
    if (mousedown.value) {
        const dx = event.movementX;
        const dy = event.movementY;
        mapPosition.value = {
            x: mapPosition.value.x + dx,
            y: mapPosition.value.y + dy,
            scale: mapPosition.value.scale
        };
    }
};

const handleMouseDown = () => {
    mousedown.value = true;
};

const handleMouseUp = () => {
    mousedown.value = false;
};

const handleScroll = (event) => {
    event.preventDefault();
    event.stopPropagation();
    mapPosition.value = handleDemoScroll(event, mapPosition.value.x, mapPosition.value.y, mapPosition.value.scale);
};

const resizeDebounce = ref(null);

const handleResize = () => {
    clearTimeout(resizeDebounce.value);
    resizeDebounce.value = setTimeout(() => {
        if (!canvasContainer.value || !mapCanvas.value || !gameCanvas.value) return;
        
        mapCanvas.value.width = canvasContainer.value.clientWidth;
        mapCanvas.value.height = canvasContainer.value.clientHeight;
        gameCanvas.value.width = canvasContainer.value.clientWidth;
        gameCanvas.value.height = canvasContainer.value.clientHeight;
        drawMap();
        render();
    }, 10);
};
const handleFullscreenChange = () => {
    isFullscreen.value = !!document.fullscreenElement;
};

const clearDemo = () => {
    clearInterval(interval.value);
    round.value = null;
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
    if (canvasContainer.value) {
        canvasContainer.value.removeEventListener('wheel', handleScroll);
        canvasContainer.value.removeEventListener('mousedown', handleMouseDown);
        canvasContainer.value.removeEventListener('mouseup', handleMouseUp);
        canvasContainer.value.removeEventListener('mousemove', handleMouseMove);
    }

    window.removeEventListener('resize', handleResize);

    playing.value = false;
    playbackSpeed.value = 1;
    currentTickIndex.value = 0;
    mapPosition.value = { x: 0, y: 0, scale: 1 };
    cachedImage.value = null;
    isFullscreen.value = false;
    allowPlay.value = false;
    steamIdTeamLookup.value = {};
    weapons.value = {};
    round.value = null;
    currentTickIndex.value = 0;
    currentTick.value = null;

    Bus.remove('clearDemo', clearDemo)
}


onMounted(() => {
    initializeRound();
    
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    nextTick(() => {
        // Initialize canvases
        drawMap();
        
        // Add event listeners
        canvasContainer.value.addEventListener('wheel', handleScroll);
        canvasContainer.value.addEventListener('mousedown', handleMouseDown);
        canvasContainer.value.addEventListener('mouseup', handleMouseUp);
        canvasContainer.value.addEventListener('mousemove', handleMouseMove);
        window.addEventListener('resize', handleResize);
    });

    Bus.on('clearDemo', clearDemo);
});

onUnmounted(() => {
    clearDemo();
});

watch([currentTick, mapPosition], () => {
    render(); // Update tick whenever currentTick changes
});
</script>

<style lang="scss" scoped>
.round-viewer {
    position: relative;
    width: 100%;
    height: 100%;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
    
}
.round-viewer:fullscreen {
    background-color: #0a1929;
    width: 100vw;
    height: 100vh;
    border-radius: 0;
}
.round-viewer:-webkit-full-screen {
    background-color: #0a1929;
    width: 100vw;
    height: 100vh;
    border-radius: 0;
}
.round-viewer:-moz-full-screen {
    background-color: #0a1929;
    width: 100vw;
    height: 100vh;
    border-radius: 0;
}
.round-viewer.fullscreen {
    /* No position or dimension properties here - those come from the actual fullscreen mode */
}
#canvas-container {
    position: absolute;
    width: 100%;
    top: 0;
    left: 0;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    overflow: hidden;
    z-index: 0;
}

canvas {
    position: absolute;
    display: block;
    width: 100%;
    height: 100%;
}

#mapCanvas {
    z-index: 1; /* Background canvas (map) */
}

#gameCanvas {
    z-index: 2; /* Overlay canvas for player positions */
}

#overlay {
    z-index: 3;
    width: 100%;
    top: 0;
    left: 0;
    position: absolute;
    height: 100%;
    display: grid;
    grid-template-columns: 1fr 300px;
    grid-auto-rows: auto 1fr auto;
    gap: 20px;
    user-select: none;
    pointer-events: none;
    padding: 20px;
    font-family: 'Bai Jamjuree', sans-serif;
    
    > * {
        pointer-events: all;
    }
    
    .fullscreen & {
        grid-template-columns: 1fr 400px;
        padding: 30px;
    }
}

#viewer-controls {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 10;
}

.fullscreen-button {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(32, 32, 50, 0.75);
    color: var(--main-color);
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    backdrop-filter: blur(4px);
    
    i {
        font-size: 1.5rem;
    }
    
    &:hover {
        background-color: #2a2a40;
        color: #58bc82;
    }
}

#scoreboard-container {
    grid-row: span 3;
    border-radius: 10px;
    padding: 0.5rem;
    background-color: rgba(32, 32, 50, 0.75);
    backdrop-filter: blur(4px);
    
    .fullscreen & {
        padding: 1rem;
    }
}

#round-info {
    display: flex;
    flex-direction: column;
    width: 100%;
    height: fit-content;
    pointer-events: none !important;
    justify-content: space-between;
}

#round-clock {
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  align-items: flex-start;
  border-radius: 10px;
  width: fit-content;
  padding: 1rem;
  background-color: rgba(32, 32, 50, 0.75);
  backdrop-filter: blur(4px);
  transition: all 0.3s ease;
  
  .round-number {
    font-size: 0.9rem;
    font-weight: 600;
    line-height: 1;
    color: rgba(255, 255, 255, 0.7);
    letter-spacing: 0.5px;
    text-transform: uppercase;
  }
  
  .time-remaining {
    font-size: 2.5rem;
    font-weight: 700;
    color: #ffffff;
    line-height: 1.2;
  }
  
  /* Compact styling for non-fullscreen mode */
  &.compact {
    padding: 0.5rem 0.7rem;
    
    .round-number {
      font-size: 0.7rem;
    }
    
    .time-remaining {
      font-size: 1.5rem;
    }
  }
}

/* Adjust overlay layout for non-fullscreen */
.round-viewer:not(.fullscreen) #overlay {
  grid-template-columns: 1fr;
  grid-template-rows: auto 1fr auto;
  
  /* Make sure round-info is positioned better */
  #round-info {
    justify-content: flex-start;
  }
}

/* Hide round feed in non-fullscreen mode */
.round-viewer:not(.fullscreen) #round-feed {
  display: none;
}

/* Fullscreen-specific styles */
.round-viewer.fullscreen #round-info,
.round-viewer:fullscreen #round-info {
  display: flex;
  width: 100%;
  height: fit-content;
  pointer-events: none !important;
  justify-content: space-between;
}

/* Maintain the existing styles for fullscreen mode */
.round-viewer.fullscreen {
    #round-clock {
        padding: 1.5rem;
        
        .round-number {
            font-size: 1.1rem;
        }
        
        .time-remaining {
            font-size: 3rem;
        }
    }
}

#round-feed {
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: flex-end;
    max-width: 100%;
    pointer-events: none !important;
    
    .feed-item {
        display: flex;
        gap: 6px;
        align-items: center;
        background-color: rgba(32, 32, 50, 0.75);
        backdrop-filter: blur(4px);
        border-radius: 6px;
        padding: 6px 10px;
        margin-bottom: 6px;
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.9);
        height: 32px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        max-width: 100%;
        
        .event-time {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.75rem;
            margin-right: 2px;
        }
        
        .event-icon {
            height: 16px;
            width: auto;
        }
        
        .weapon-icon {
            height: 20px;
            width: auto;
        }
        
        .player-name {
            max-width: 90px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            
            &.attacker {
                font-weight: 600;
            }
            
            &.victim {
                font-weight: 500;
            }
        }
        
        .assist-plus {
            color: rgba(255, 255, 255, 0.6);
        }
    }
}

#playback-controls {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    height: 56px;
    position: absolute;
    bottom: 0;
    background-color: rgba(32, 32, 50, 0.75);
    backdrop-filter: blur(4px);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(45, 45, 69, 0.5);
    
    .control-buttons {
        display: flex;
        gap: 8px;
    }
    
    .timeline-slider {
        flex: 1;
    }
}

.playback-button, .speed-button {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #202032;
    color: var(--main-color);
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    
    i {
        font-size: 1.5rem;
    }
    
    &:hover {
        background-color: #2a2a40;
        color: #58bc82;
    }
    
    &.loading {
        cursor: not-allowed;
        
        .loading-icon {
            animation: spin 1.5s linear infinite;
        }
    }
}

.speed-button {
    width: auto;
    min-width: 40px;
    padding: 0 12px;
    font-weight: 600;
    font-size: 0.9rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

:deep(.dark-slider) {
    height: 4px;
    width: 100%;
    
    .p-slider-range {
        background-color: #58bc82;
    }
    
    .p-slider-handle {
        background-color: #58bc82;
        border: 3px solid #58bc82;
        width: 16px;
        height: 16px;
        margin-top: -8px;
        margin-left: -8px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        
        &:hover {
            background-color: #ffffff;
            border-color: #58bc82;
            transform: scale(1.1);
        }
        
        &:focus {
            box-shadow: 0 0 0 4px rgba(88, 188, 130, 0.3);
        }

        &::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background-color: #58bc82;
            transform: translate(-50%, -50%);
        }
    }
}

// Responsive adjustments
@media (max-width: 768px) {
    #overlay {
        grid-template-columns: 1fr;
        grid-template-rows: auto auto 1fr auto;
        
        #scoreboard-container {
            grid-row: 2;
        }
    }
    
    .round-viewer:not(.fullscreen) {
        height: 500px;
    }
}

// Fix for fullscreen mode
.round-viewer.fullscreen {
    #round-feed {
        max-width: 100%;
        
        .feed-item {
            font-size: 1rem;
            height: 40px;
            
            .event-icon {
                height: 20px;
            }
            
            .weapon-icon {
                height: 24px;
            }
            
            .player-name {
                max-width: 150px;
            }
        }
    }
    
    #round-clock {
        padding: 1.5rem;
        margin-bottom: 20px;
        
        .round-number {
            font-size: 1.1rem;
        }
        
        .time-remaining {
            font-size: 3rem;
        }
    }
    
    .playback-button, .speed-button {
        width: 50px;
        height: 50px;
        
        i {
            font-size: 1.8rem;
        }
    }
    #scoreboard-container {
        background-color: transparent !important;
        backdrop-filter: none;
        height: fit-content;
        width: 400px;
    }
    .speed-button {
        width: auto;
        min-width: 50px;
        padding: 0 16px;
        font-size: 1.1rem;
    }
}
</style>