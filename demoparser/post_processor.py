"""
post_processor.py

All heavy post-processing that runs AFTER a demo is made visible to the player.
Called from demo_processor.py as a fire-and-forget asyncio task.

Pipeline (all concurrent, all non-fatal):
  1. compute_position_dominance  — resolve player positions → callout zones, store in demo_stats
  2. run_antistrat_analysis       — auto-link demo to opponent team profiles (existing)

Heatmaps (kill_data / death_data) are already computed BEFORE the demo is made
visible in _process_rounds → create_heatmap_dict → INSERT demo_heatmaps.
No change needed there.
"""

from __future__ import annotations

import asyncio
import gc
import json
import os
from collections import defaultdict
from typing import Any

import aiomysql
from loguru import logger


# ─── Zone cache ───────────────────────────────────────────────────────────────
# Loaded once per process from the DB, keyed by map name (e.g. "de_overpass")
_zone_cache: dict[str, list[dict]] = {}
_zone_cache_lock = asyncio.Lock()


async def _load_zones_for_map(pool: aiomysql.Pool, map_name: str) -> list[dict]:
    """
    Load callout zones for a map from map_callout_zones + maps tables.
    Cached in-process — zones rarely change.
    Returns list of { id, canonical_name, zone_type, polygon, color, layer_order }
    sorted by auto-tag priority (point > zone > area, then layer order).
    """
    global _zone_cache
    if map_name in _zone_cache:
        return _zone_cache[map_name]

    async with _zone_cache_lock:
        # Double-check after acquiring lock
        if map_name in _zone_cache:
            return _zone_cache[map_name]

        async with pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cursor:
                await cursor.execute(
                    """
                    SELECT z.id, z.canonical_name, z.zone_type, z.polygon,
                           z.color, z.sort_order,
                           COALESCE(cl.order, 0) AS layer_order
                    FROM   map_callout_zones z
                    JOIN   maps m ON m.id = z.map_id
                    LEFT JOIN callout_layers cl ON cl.id = z.layer_id
                    WHERE  m.name = %s
                    ORDER BY
                        CASE z.zone_type WHEN 'point' THEN 2 WHEN 'zone' THEN 1 ELSE 0 END DESC,
                        COALESCE(cl.order, 0) DESC,
                        z.sort_order ASC
                    """,
                    (map_name,),
                )
                rows = await cursor.fetchall()

        zones = []
        for row in rows:
            polygon = row["polygon"]
            if isinstance(polygon, str):
                try:
                    polygon = json.loads(polygon)
                except Exception:
                    polygon = []
            zones.append({
                "id":             row["id"],
                "canonical_name": row["canonical_name"],
                "zone_type":      row["zone_type"],
                "polygon":        polygon,
                "color":          row["color"] or "#f97316",
                "priority":       ({"point": 2, "zone": 1, "area": 0}.get(row["zone_type"], 0) * 1000)
                                  + (row["layer_order"] * 10)
                                  + (row["sort_order"] or 0),
            })

        _zone_cache[map_name] = zones
        logger.info(f"[PostProcess] Loaded {len(zones)} callout zones for {map_name}")
        return zones


# ─── Point-in-polygon ─────────────────────────────────────────────────────────

def _point_in_polygon(x: float, y: float, polygon: list) -> bool:
    """
    Ray-casting algorithm for point-in-polygon.
    Polygon is a list of [x, y] or (x, y) pairs in CS2 world coordinates.
    """
    if not polygon or len(polygon) < 3:
        return False

    inside = False
    n = len(polygon)
    j = n - 1

    for i in range(n):
        xi = polygon[i][0] if isinstance(polygon[i], (list, tuple)) else polygon[i].get("x", 0)
        yi = polygon[i][1] if isinstance(polygon[i], (list, tuple)) else polygon[i].get("y", 0)
        xj = polygon[j][0] if isinstance(polygon[j], (list, tuple)) else polygon[j].get("x", 0)
        yj = polygon[j][1] if isinstance(polygon[j], (list, tuple)) else polygon[j].get("y", 0)

        if ((yi > y) != (yj > y)) and (x < (xj - xi) * (y - yi) / (yj - yi + 1e-10) + xi):
            inside = not inside
        j = i

    return inside


def _resolve_zone(x: float, y: float, zones: list[dict]) -> dict | None:
    """
    Find the highest-priority zone containing point (x, y).
    Zones are pre-sorted by priority descending so we return the first match.
    """
    for zone in zones:
        if _point_in_polygon(x, y, zone["polygon"]):
            return zone
    return None


# ─── Position dominance computation ──────────────────────────────────────────

async def compute_position_dominance(
    pool: aiomysql.Pool,
    demo_id: int,
    map_name: str,
    frame_df,          # pandas DataFrame with tick data
    rounds_df,         # pandas DataFrame with round metadata
) -> None:
    """
    For each player in the demo, compute how much time (in ticks) they spend
    in each callout zone per side (T/CT).

    Uses frame_df which is already loaded in memory during demo processing.
    Samples every 8th tick to reduce computation (~8x speedup, still accurate).

    Output stored as JSON in demo_stats.position_dominance:
    {
        "steamid": {
            "T":  [{ zone_id, zone_name, zone_type, color, ticks, pct }],
            "CT": [...]
        }
    }
    """
    try:
        zones = await _load_zones_for_map(pool, map_name)
        if not zones:
            logger.info(f"[PostProcess] No callout zones for {map_name}, skipping position dominance")
            return

        SAMPLE_RATE = 8  # process every 8th tick — ~64ms intervals at 64 tick

        # Build per-round (freeze_end, official_end) windows once. Instead of
        # materialising a set of every sampled tick (which could be tens of
        # thousands of ints held alive for the whole loop), we keep the windows
        # and do a cheap range check per sampled tick.
        round_windows: list[tuple[int, int, int]] = []  # (round_num, freeze_end, official_end)
        if rounds_df is not None and not rounds_df.empty:
            for _, rnd in rounds_df.iterrows():
                freeze_end   = int(rnd.get("freeze_end",   rnd.get("start", 0)) or 0)
                official_end = int(rnd.get("official_end", rnd.get("end",   0)) or 0)
                rn           = int(rnd.get("round_num", rnd.get("round_number", 0)) or 0)
                if rn > 0 and freeze_end > 0 and official_end > freeze_end:
                    round_windows.append((rn, freeze_end, official_end))

        # Sort by freeze_end so we can bisect to find the round containing a tick.
        import bisect
        round_windows.sort(key=lambda w: w[1])
        fe_starts = [w[1] for w in round_windows]

        def _round_for_tick(tick: int) -> int | None:
            # rightmost window whose freeze_end <= tick
            lo = bisect.bisect_right(fe_starts, tick) - 1
            if lo < 0:
                return None
            rn, fe, oe = round_windows[lo]
            return rn if fe <= tick <= oe else None

        # steamid → side → zone_id → tick count
        dominance: dict[str, dict[str, dict[int, int]]] = defaultdict(
            lambda: {"T": defaultdict(int), "CT": defaultdict(int)}
        )

        # Total ticks alive per player per side (denominator for %)
        alive_ticks: dict[str, dict[str, int]] = defaultdict(lambda: {"T": 0, "CT": 0})

        # Narrow to the columns we actually need before iterating — avoids
        # carrying the full frame_df (with dozens of props columns) through the
        # groupby. We only need tick, steamid, health, X, Y, side/team_name.
        need = {"tick", "steamid", "health", "X", "Y"}
        side_col = "team_name" if "team_name" in frame_df.columns else "side"
        need.add(side_col)
        avail = [c for c in need if c in frame_df.columns]

        # Group by tick once — previously the code did
        # `frame_df[frame_df["tick"] == tick]` for every sampled tick, which is
        # O(ticks × rows). A single groupby is O(rows).
        tick_groups = frame_df.groupby("tick", sort=True)

        tick_index = 0
        for tick, group in tick_groups:
            if tick_index % SAMPLE_RATE != 0:
                tick_index += 1
                continue
            tick_index += 1

            rn = _round_for_tick(int(tick))
            if rn is None:
                continue

            # itertuples is ~10x faster and lighter than iterrows (no Series
            # object per row). We index by attribute name via namedtuple.
            for row in group[avail].itertuples(index=False):
                sid    = str(getattr(row, "steamid", ""))
                health = float(getattr(row, "health", 0) or 0)
                x      = float(getattr(row, "X", 0) or 0)
                y      = float(getattr(row, "Y", 0) or 0)
                side   = str(getattr(row, side_col, "") or "").upper()

                if not sid or sid in ("", "None", "0") or health <= 0:
                    continue

                if side in ("T", "TERRORIST"):
                    side = "T"
                elif side in ("CT", "COUNTERTERRORIST"):
                    side = "CT"
                else:
                    continue

                alive_ticks[sid][side] += 1

                zone = _resolve_zone(x, y, zones)
                if zone:
                    dominance[sid][side][zone["id"]] += 1

        # Release the groupby and the narrowed frame references.
        del tick_groups

        # Build output
        zone_lookup = {z["id"]: z for z in zones}
        result = {}

        for sid, sides in dominance.items():
            result[sid] = {}
            for side, zone_counts in sides.items():
                total = max(1, alive_ticks[sid][side])
                sorted_zones = sorted(zone_counts.items(), key=lambda kv: kv[1], reverse=True)

                result[sid][side] = [
                    {
                        "zone_id":   zid,
                        "zone_name": zone_lookup[zid]["canonical_name"] if zid in zone_lookup else "Unknown",
                        "zone_type": zone_lookup[zid]["zone_type"]      if zid in zone_lookup else "area",
                        "color":     zone_lookup[zid]["color"]          if zid in zone_lookup else "#f97316",
                        "ticks":     count,
                        "pct":       round(count / total * 100, 1),
                    }
                    for zid, count in sorted_zones
                    if zid in zone_lookup
                ]

        dominance_json = json.dumps(result)

        # Store in demo_stats — add column if it doesn't exist yet (graceful)
        async with pool.acquire() as conn:
            async with conn.cursor() as cursor:
                try:
                    await cursor.execute(
                        "UPDATE demo_stats SET position_dominance = %s WHERE demo_id = %s",
                        (dominance_json, demo_id),
                    )
                    await conn.commit()
                    logger.info(
                        f"[PostProcess] Position dominance stored for demo {demo_id} "
                        f"({len(result)} players, {map_name})"
                    )
                except Exception as e:
                    # Column may not exist yet — non-fatal
                    logger.warning(f"[PostProcess] Could not store position_dominance: {e}")

    except Exception as exc:
        logger.error(f"[PostProcess] Position dominance failed for demo {demo_id}: {exc}")


# ─── Main entry point ─────────────────────────────────────────────────────────

async def run_post_processing(
    pool: aiomysql.Pool,
    demo_id: int,
    user_id: int,
    map_name: str,
    frame_df,
    kills_df,
    rounds_df,
    stats_json_list: list[dict],
    smokes_df=None,
    infernos_df=None,
    grenades_df=None,
    shots_df=None,
    planted_df=None,
) -> None:
    """
    Fire-and-forget post-processing pipeline.
    Called from demo_processor.py AFTER demo is made visible.

    Runs all tasks concurrently — any individual failure is logged but
    never propagates to break the caller.
    """
    logger.info(f"[PostProcess] Starting post-processing for demo {demo_id} ({map_name})")

    try:
        # Import here to avoid circular imports
        from antistrat_analyzer import run_antistrat_analysis
        from spatial_processor  import compute_spatial_data
        from tactic_detector    import detect_tactics

        await asyncio.gather(
            # 1. Position dominance — zone tick counts per player
            compute_position_dominance(pool, demo_id, map_name, frame_df, rounds_df),

            # 2. Spatial data — paths, utility, shots, plants, entries
            compute_spatial_data(
                pool        = pool,
                demo_id     = demo_id,
                frame_df    = frame_df,
                rounds_df   = rounds_df,
                smokes_df   = smokes_df,
                infernos_df = infernos_df,
                grenades_df = grenades_df,
                shots_df    = shots_df,
                planted_df  = planted_df,
            ),

            # 3. AntiStrat auto-link
            run_antistrat_analysis(
                pool            = pool,
                demo_id         = demo_id,
                user_id         = user_id,
                frame_df        = frame_df,
                kills_df        = kills_df,
                rounds_meta     = rounds_df.to_dict(orient="records") if hasattr(rounds_df, "to_dict") else [],
                stats_json_list = stats_json_list,
            ),

            # 4. Tactic detection — scan rounds for tactical patterns
            detect_tactics(
                pool            = pool,
                demo_id         = demo_id,
                map_name        = map_name,
                frame_df        = frame_df,
                kills_df        = kills_df,
                rounds_df       = rounds_df,
                smokes_df       = smokes_df,
                infernos_df     = infernos_df,
                grenades_df     = grenades_df,
                shots_df        = shots_df,
                planted_df      = planted_df,
                stats_json_list = stats_json_list,
            ),

            return_exceptions=True,
        )

        logger.info(f"[PostProcess] Completed post-processing for demo {demo_id}")

    except Exception as exc:
        logger.error(f"[PostProcess] Post-processing pipeline failed for demo {demo_id}: {exc}")
    finally:
        # This runs as a fire-and-forget asyncio task. Drop the big DataFrame
        # references the moment the work is done so the GC can reclaim the
        # multi-hundred-MB frame_df / kills_df / etc. before the next demo in
        # the queue starts parsing. Without this they linger until the task
        # object itself is collected.
        del frame_df, kills_df, rounds_df, smokes_df, infernos_df, grenades_df, shots_df, planted_df
        gc.collect()