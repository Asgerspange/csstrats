import type { Ref } from 'vue';
import { UTILITY_TYPES, PLAYER_ROLES, SMOKE_WORLD_RADIUS } from './useRoundPlanner';
import type { RenderParams, Vec2, MapPosition } from './types';

const UTIL_COLORS: Record<string, string> = Object.fromEntries(UTILITY_TYPES.map(u => [u.key, u.color]));
const ROLE_LABELS: Record<string, string> = Object.fromEntries(PLAYER_ROLES.map(r => [r.key, r.label]));

const ARROW_PATH = new Path2D('M 6 -7.44 a 9.6 9.6 180 0 0 -6 -2.16 a 9.6 9.6 180 0 0 0 19.2 a 9.6 9.6 180 0 0 6 -2.16 l 9.84 -7.44 z');

// ─── Canvas accessor types ────────────────────────────────────────────────────

interface Canvases {
    mapCanvas:  HTMLCanvasElement | null;
    mapCtx:     CanvasRenderingContext2D | null;
    gameCanvas: HTMLCanvasElement | null;
    ctx:        CanvasRenderingContext2D | null;
}

interface RendererOptions {
    getCanvases:   () => Canvases;
    toGameCoords:  (worldX: number, worldY: number) => Vec2;
    mapPosition:   Ref<MapPosition>;
    cachedImage:   Ref<HTMLImageElement | null>;
}

// ─── Composable ───────────────────────────────────────────────────────────────

export function useRoundPlannerRenderer({ getCanvases, toGameCoords, mapPosition, cachedImage }: RendererOptions) {

    function render(params: RenderParams): void {
        const {
            players, selectedSlot, currentSecond,
            getPlayerPosAtTime, activeMode,
            pendingCursorPos, pendingUtilThrowAt, isPlayback,
            draggingWaypoint, hoveredWaypoint,
            planName, side, mapMetadata,
        } = params;

        const { mapCanvas, mapCtx, gameCanvas, ctx } = getCanvases();
        if (!mapCanvas || !gameCanvas || !ctx || !mapCtx) return;

        const container = document.getElementById('canvas-container');
        if (!container) return;

        if (mapCanvas.width !== container.clientWidth || mapCanvas.height !== container.clientHeight) {
            mapCanvas.width   = container.clientWidth;
            mapCanvas.height  = container.clientHeight;
            gameCanvas.width  = container.clientWidth;
            gameCanvas.height = container.clientHeight;
        }

        const { x: ox, y: oy, scale: sc } = mapPosition.value;

        // ── Map ───────────────────────────────────────────────────────────────
        mapCtx.setTransform(1, 0, 0, 1, 0, 0);
        mapCtx.clearRect(0, 0, mapCanvas.width, mapCanvas.height);
        if (cachedImage.value?.complete && cachedImage.value.naturalWidth > 0) {
            mapCtx.setTransform(sc, 0, 0, sc, ox, oy);
            mapCtx.drawImage(cachedImage.value, 0, 0);
        }

        // ── Game layer ────────────────────────────────────────────────────────
        ctx.setTransform(sc, 0, 0, sc, ox, oy);
        ctx.clearRect(-ox / sc, -oy / sc, mapCanvas.width / sc, mapCanvas.height / sc);

        // ── Plan name watermark (screen-space) ────────────────────────────────
        // Suppressed for snapshots so saved thumbnails stay clean.
        if (planName && !params.captureForSnapshot) {
            ctx.save();
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.globalAlpha = 0.4;
            ctx.font        = 'bold 11px "Bai Jamjuree",sans-serif';
            ctx.textAlign   = 'left';
            ctx.fillStyle   = side === 'T' ? '#dba43f' : '#5b7fc4';
            ctx.fillText(`[${side}] ${planName}`, 12, 20);
            ctx.restore();
            ctx.setTransform(sc, 0, 0, sc, ox, oy);
        }

        players.forEach((player, slot) => {
            const isSelected = slot === selectedSlot;
            const color      = player.color;

            // ── Path line ─────────────────────────────────────────────────────
            if (player.path.length >= 2) {
                ctx.save();
                ctx.globalAlpha = isSelected ? 0.9 : 0.35;
                ctx.strokeStyle = color;
                ctx.lineWidth   = isSelected ? 2.5 : 1.5;
                ctx.lineCap     = 'round';
                ctx.lineJoin    = 'round';
                ctx.setLineDash([]);
                ctx.beginPath();
                const p0 = toGameCoords(player.path[0].x, player.path[0].y);
                ctx.moveTo(p0.x, p0.y);
                for (let i = 1; i < player.path.length; i++) {
                    const gc = toGameCoords(player.path[i].x, player.path[i].y);
                    ctx.lineTo(gc.x, gc.y);
                }
                ctx.stroke();

                if (isSelected) {
                    for (let i = 1; i < player.path.length; i++) {
                        if (player.path[i].speed === 'walk') {
                            const a = toGameCoords(player.path[i - 1].x, player.path[i - 1].y);
                            const b = toGameCoords(player.path[i].x,     player.path[i].y);
                            ctx.strokeStyle = color;
                            ctx.lineWidth   = 2.5;
                            ctx.setLineDash([4, 4]);
                            ctx.beginPath();
                            ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y);
                            ctx.stroke();
                            ctx.setLineDash([]);
                        }
                    }
                }
                ctx.restore();
            }

            // ── Waypoint dots (edit, selected) ────────────────────────────────
            if (!isPlayback && isSelected) {
                player.path.forEach((pt, i) => {
                    const gc        = toGameCoords(pt.x, pt.y);
                    const isDragged = draggingWaypoint?.slot === slot && draggingWaypoint?.index === i;
                    const isHovered = !isDragged && hoveredWaypoint?.slot === slot && hoveredWaypoint?.index === i;

                    ctx.save();
                    ctx.globalAlpha = 0.9;

                    if (isDragged) {
                        ctx.shadowBlur  = 14;
                        ctx.shadowColor = color;
                        ctx.fillStyle   = '#fff';
                        ctx.strokeStyle = color;
                        ctx.lineWidth   = 2.5;
                        ctx.beginPath();
                        ctx.arc(gc.x, gc.y, 7, 0, Math.PI * 2);
                        ctx.fill(); ctx.stroke();
                    } else if (isHovered) {
                        ctx.shadowBlur  = 8;
                        ctx.shadowColor = color;
                        ctx.fillStyle   = '#fff';
                        ctx.strokeStyle = color;
                        ctx.lineWidth   = 2;
                        ctx.beginPath();
                        ctx.arc(gc.x, gc.y, 6, 0, Math.PI * 2);
                        ctx.fill(); ctx.stroke();
                        ctx.shadowBlur  = 0;
                        ctx.globalAlpha = 0.4;
                        ctx.strokeStyle = color;
                        ctx.lineWidth   = 1;
                        ctx.beginPath();
                        ctx.arc(gc.x, gc.y, 10, 0, Math.PI * 2);
                        ctx.stroke();
                    } else {
                        ctx.fillStyle   = i === 0 ? color : '#fff';
                        ctx.strokeStyle = color;
                        ctx.lineWidth   = 1.5;
                        ctx.beginPath();
                        ctx.arc(gc.x, gc.y, i === 0 ? 5.5 : 3.5, 0, Math.PI * 2);
                        ctx.fill(); ctx.stroke();
                    }

                    ctx.shadowBlur  = 0;
                    ctx.globalAlpha = isDragged || isHovered ? 1 : 0.65;
                    ctx.fillStyle   = isDragged || isHovered ? color : '#fff';
                    ctx.font        = 'bold 8px "Bai Jamjuree",sans-serif';
                    ctx.textAlign   = 'center';
                    ctx.fillText(`${pt.t.toFixed(1)}s`, gc.x, gc.y - 12);

                    // Wait indicator below the dot when player waits here
                    if ((pt.waitAfter ?? 0) > 0) {
                        const dotR = isDragged ? 7 : (i === 0 ? 5.5 : 3.5);
                        ctx.globalAlpha = 0.9;
                        ctx.fillStyle   = '#FFC107';
                        ctx.font        = `bold 7px "Bai Jamjuree",sans-serif`;
                        ctx.textAlign   = 'center';
                        ctx.fillText(`+${(pt.waitAfter!).toFixed(1)}s wait`, gc.x, gc.y + dotR + 9);
                    }
                    ctx.restore();
                });
            }

            // ── Utilities ─────────────────────────────────────────────────────
            player.utilities.forEach(util => {
                const tc     = toGameCoords(util.throwPos.x, util.throwPos.y);
                const lc     = toGameCoords(util.landPos.x,  util.landPos.y);
                const uColor = UTIL_COLORS[util.type] ?? '#fff';

                const FLIGHT = util.type === 'smoke' ? 1.8 : 1.0;
                const EFFECT = util.type === 'smoke'   ? 18
                             : util.type === 'molotov' ?  7
                             : util.type === 'flash'   ?  1
                             :                            0.5;

                const elapsed  = currentSecond - util.throwAt;
                const inFlight = elapsed >= 0 && elapsed < FLIGHT;
                const inEffect = elapsed >= FLIGHT && elapsed < FLIGHT + EFFECT;
                const isActive = inFlight || inEffect;

                // Static arc preview
                if (!inFlight) {
                    ctx.save();
                    ctx.globalAlpha = isActive ? 0.2 : (isSelected ? 0.6 : 0.25);
                    ctx.strokeStyle = uColor;
                    ctx.lineWidth   = 1.2;
                    ctx.setLineDash([3, 3]);
                    ctx.beginPath();
                    ctx.moveTo(tc.x, tc.y);
                    ctx.quadraticCurveTo((tc.x + lc.x) / 2, (tc.y + lc.y) / 2 - 20, lc.x, lc.y);
                    ctx.stroke();
                    ctx.setLineDash([]);

                    ctx.globalAlpha = isActive ? 0.1 : (isSelected ? 0.85 : 0.35);
                    ctx.fillStyle   = uColor;
                    ctx.strokeStyle = '#000';
                    ctx.lineWidth   = 1;
                    ctx.beginPath();
                    ctx.arc(tc.x, tc.y, 4, 0, Math.PI * 2);
                    ctx.fill(); ctx.stroke();

                    ctx.globalAlpha = isActive ? 0.1 : (isSelected ? 0.7 : 0.25);
                    ctx.strokeStyle = uColor;
                    ctx.lineWidth   = 1.5;
                    ctx.beginPath();
                    ctx.arc(lc.x, lc.y, 8, 0, Math.PI * 2);
                    ctx.stroke();
                    ctx.restore();
                }

                // In-flight grenade
                if (inFlight) {
                    const frac = elapsed / FLIGHT;
                    const mx   = (tc.x + lc.x) / 2;
                    const my   = (tc.y + lc.y) / 2 - 20;
                    const inv  = 1 - frac;
                    const gx   = inv * inv * tc.x + 2 * inv * frac * mx + frac * frac * lc.x;
                    const gy   = inv * inv * tc.y + 2 * inv * frac * my + frac * frac * lc.y;

                    const TRAIL_STEPS = 12;
                    for (let s = 0; s < TRAIL_STEPS; s++) {
                        const t0  = Math.max(0, frac - (s + 1) * 0.06);
                        const t1  = Math.max(0, frac - s * 0.06);
                        if (t0 >= t1) continue;
                        const i0  = 1 - t0, i1 = 1 - t1;
                        const x0  = i0 * i0 * tc.x + 2 * i0 * t0 * mx + t0 * t0 * lc.x;
                        const y0  = i0 * i0 * tc.y + 2 * i0 * t0 * my + t0 * t0 * lc.y;
                        const x1  = i1 * i1 * tc.x + 2 * i1 * t1 * mx + t1 * t1 * lc.x;
                        const y1  = i1 * i1 * tc.y + 2 * i1 * t1 * my + t1 * t1 * lc.y;
                        ctx.save();
                        ctx.globalAlpha = (1 - s / TRAIL_STEPS) * 0.6;
                        ctx.strokeStyle = uColor;
                        ctx.lineWidth   = 2 * (1 - s / TRAIL_STEPS);
                        ctx.lineCap     = 'round';
                        ctx.beginPath();
                        ctx.moveTo(x0, y0); ctx.lineTo(x1, y1);
                        ctx.stroke();
                        ctx.restore();
                    }

                    ctx.save();
                    ctx.globalAlpha = 1;
                    ctx.fillStyle   = uColor;
                    ctx.strokeStyle = '#000';
                    ctx.lineWidth   = 1.5;
                    ctx.shadowBlur  = 8;
                    ctx.shadowColor = uColor;
                    ctx.beginPath();
                    ctx.arc(gx, gy, 5, 0, Math.PI * 2);
                    ctx.fill(); ctx.stroke();
                    ctx.restore();
                }

                // Landing effects
                if (inEffect) {
                    const ep = (elapsed - FLIGHT) / EFFECT;
                    ctx.save();

                    if (util.type === 'smoke') {
                        const maxR  = mapMetadata ? SMOKE_WORLD_RADIUS / mapMetadata.scale : 22;
                        const r     = maxR * Math.min(1, ep / 0.15);
                        const alpha = ep < 0.85 ? 0.55 : 0.55 * (1 - (ep - 0.85) / 0.15);
                        ctx.globalAlpha = alpha;
                        ctx.fillStyle   = '#b8c8d8';
                        ctx.beginPath();
                        ctx.arc(lc.x, lc.y, r, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.globalAlpha = alpha * 0.7;
                        ctx.strokeStyle = uColor;
                        ctx.lineWidth   = 1.5;
                        ctx.beginPath();
                        ctx.arc(lc.x, lc.y, r, 0, Math.PI * 2);
                        ctx.stroke();

                    } else if (util.type === 'molotov') {
                        const baseR = mapMetadata ? 36 / mapMetadata.scale : 10;
                        const pulse = 1 + 0.08 * Math.sin(currentSecond * 12);
                        const r     = baseR * pulse;
                        const alpha = ep < 0.8 ? 0.6 : 0.6 * (1 - (ep - 0.8) / 0.2);
                        ctx.globalAlpha = alpha * 0.5;
                        ctx.fillStyle   = '#FF6030';
                        ctx.beginPath();
                        ctx.arc(lc.x, lc.y, r, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.globalAlpha = alpha;
                        ctx.strokeStyle = '#FFAB91';
                        ctx.lineWidth   = 2;
                        ctx.beginPath();
                        ctx.arc(lc.x, lc.y, r, 0, Math.PI * 2);
                        ctx.stroke();

                    } else if (util.type === 'flash') {
                        const r     = 30 * ep;
                        const alpha = Math.max(0, 1 - ep * 2);
                        ctx.globalAlpha = alpha;
                        const grad  = ctx.createRadialGradient(lc.x, lc.y, 0, lc.x, lc.y, r);
                        grad.addColorStop(0, 'rgba(255,255,220,0.9)');
                        grad.addColorStop(1, 'rgba(255,255,220,0)');
                        ctx.fillStyle = grad;
                        ctx.beginPath();
                        ctx.arc(lc.x, lc.y, r, 0, Math.PI * 2);
                        ctx.fill();

                    } else {
                        const r     = 20 * ep;
                        const alpha = Math.max(0, 1 - ep * 1.5);
                        ctx.globalAlpha = alpha;
                        ctx.strokeStyle = uColor;
                        ctx.lineWidth   = 3 * (1 - ep);
                        ctx.beginPath();
                        ctx.arc(lc.x, lc.y, r, 0, Math.PI * 2);
                        ctx.stroke();
                        ctx.globalAlpha = alpha * 0.4;
                        ctx.fillStyle   = uColor;
                        ctx.beginPath();
                        ctx.arc(lc.x, lc.y, r * 0.5, 0, Math.PI * 2);
                        ctx.fill();
                    }
                    ctx.restore();
                }

                // Label (edit, selected, not active)
                if (isSelected && !inFlight && !inEffect) {
                    ctx.save();
                    ctx.globalAlpha = 0.75;
                    ctx.fillStyle   = uColor;
                    ctx.font        = 'bold 7px "Bai Jamjuree",sans-serif';
                    ctx.textAlign   = 'center';
                    ctx.fillText(`${util.type.toUpperCase()} @${util.throwAt.toFixed(1)}s`, lc.x, lc.y + 20);
                    ctx.restore();
                }
            });

            // ── Player arrow ──────────────────────────────────────────────────
            if (player.path.length === 0) return;

            const pos = getPlayerPosAtTime(slot, currentSecond);
            if (!pos) return;

            const gc  = toGameCoords(pos.x, pos.y);
            const yaw = _getYaw(slot, currentSecond, getPlayerPosAtTime, players);

            ctx.save();
            ctx.globalAlpha = isSelected ? 1 : 0.6;
            ctx.translate(gc.x, gc.y);
            ctx.rotate(yaw);
            ctx.fillStyle  = color;
            if (isSelected) { ctx.shadowBlur = 10; ctx.shadowColor = color; }
            ctx.fill(ARROW_PATH);
            ctx.shadowBlur = 0;
            ctx.restore();

            // Name + role
            ctx.save();
            ctx.globalAlpha = isSelected ? 1 : 0.6;
            ctx.fillStyle   = color;
            ctx.font        = `bold ${isSelected ? 11 : 9}px "Bai Jamjuree",sans-serif`;
            ctx.textAlign   = 'center';
            ctx.shadowBlur  = 3;
            ctx.shadowColor = '#000';
            ctx.fillText(player.name, gc.x, gc.y - 16);

            if (player.role && player.role !== 'none') {
                ctx.font        = `${isSelected ? 8 : 7}px "Bai Jamjuree",sans-serif`;
                ctx.globalAlpha = isSelected ? 0.8 : 0.5;
                ctx.fillText(ROLE_LABELS[player.role] ?? player.role.toUpperCase(), gc.x, gc.y - 6);
            }
            ctx.restore();
        });

        // ── Pending path preview ──────────────────────────────────────────────
        if (activeMode === 'path' && pendingCursorPos) {
            const player = players[selectedSlot];
            if (player?.path.length > 0) {
                const last = player.path[player.path.length - 1];
                const lc   = toGameCoords(last.x, last.y);
                const cc   = toGameCoords(pendingCursorPos.x, pendingCursorPos.y);

                ctx.save();
                ctx.globalAlpha = 0.4;
                ctx.strokeStyle = player.color;
                ctx.lineWidth   = 1.5;
                ctx.setLineDash([4, 3]);
                ctx.beginPath();
                ctx.moveTo(lc.x, lc.y); ctx.lineTo(cc.x, cc.y);
                ctx.stroke();
                ctx.setLineDash([]);

                const ghostYaw = Math.atan2(-(pendingCursorPos.y - last.y), pendingCursorPos.x - last.x);
                ctx.globalAlpha = 0.3;
                ctx.translate(cc.x, cc.y);
                ctx.rotate(ghostYaw);
                ctx.fillStyle = player.color;
                ctx.fill(ARROW_PATH);
                ctx.restore();
            }
        }

        // ── Pending util land preview ─────────────────────────────────────────
        // Arc origin = player's actual position at currentSecond (where they stand
        // when they throw), NOT the last waypoint position.
        if (activeMode === 'util_land' && pendingCursorPos) {
            const player    = players[selectedSlot];
            // Use the locked throw time if set (from "throw from waypoint"), else currentSecond
            const throwTime = pendingUtilThrowAt ?? currentSecond;
            const throwPosW = getPlayerPosAtTime(selectedSlot, throwTime);
            if (player && throwPosW) {
                const tc = toGameCoords(throwPosW.x, throwPosW.y);
                const cc = toGameCoords(pendingCursorPos.x, pendingCursorPos.y);
                ctx.save();
                ctx.globalAlpha = 0.5;
                ctx.strokeStyle = player.color;
                ctx.lineWidth   = 1.4;
                ctx.setLineDash([3, 3]);
                ctx.beginPath();
                ctx.moveTo(tc.x, tc.y);
                ctx.quadraticCurveTo((tc.x + cc.x) / 2, (tc.y + cc.y) / 2 - 20, cc.x, cc.y);
                ctx.stroke();
                ctx.setLineDash([]);
                // Throw origin dot
                ctx.globalAlpha = 0.8;
                ctx.fillStyle   = player.color;
                ctx.beginPath();
                ctx.arc(tc.x, tc.y, 4, 0, Math.PI * 2);
                ctx.fill();
                // Land target ring
                ctx.globalAlpha = 0.5;
                ctx.strokeStyle = '#fff';
                ctx.lineWidth   = 1.5;
                ctx.beginPath();
                ctx.arc(cc.x, cc.y, 8, 0, Math.PI * 2);
                ctx.stroke();
                ctx.restore();
            }
        }
    }

    function _getYaw(
        slot: number,
        t: number,
        getPlayerPosAtTime: (slot: number, t: number) => Vec2 | null,
        players: import('./types').Player[]
    ): number {
        const pos1 = getPlayerPosAtTime(slot, t);
        const pos2 = getPlayerPosAtTime(slot, t + 0.05);
        if (!pos1 || !pos2) return -Math.PI / 2;

        let dx = pos2.x - pos1.x;
        let dy = pos2.y - pos1.y;

        // During a wait the player is stationary — look toward the next waypoint
        if (Math.abs(dx) < 0.01 && Math.abs(dy) < 0.01) {
            const path = players[slot]?.path ?? [];
            // Find which waypoint we're waiting at
            for (let i = 0; i < path.length; i++) {
                const wp = path[i];
                const departureT = wp.t + (wp.waitAfter ?? 0);
                if (t >= wp.t && t < departureT) {
                    // Face toward the next waypoint if it exists
                    const next = path[i + 1];
                    if (next) { dx = next.x - wp.x; dy = next.y - wp.y; break; }
                    // Last waypoint — face the direction we arrived from
                    const prev = path[i - 1];
                    if (prev) { dx = wp.x - prev.x; dy = wp.y - prev.y; break; }
                }
            }
        }

        // Final fallback: look at recent movement
        if (Math.abs(dx) < 0.01 && Math.abs(dy) < 0.01) {
            const pos0 = getPlayerPosAtTime(slot, t - 0.1);
            if (pos0) { dx = pos1.x - pos0.x; dy = pos1.y - pos0.y; }
        }

        if (dx === 0 && dy === 0) return -Math.PI / 2;
        return Math.atan2(-dy, dx);
    }

    return { render };
}