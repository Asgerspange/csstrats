"""
tactic_detector.py

Post-processing step that detects tactically interesting rounds from a
parsed demo. Runs concurrently with spatial_processor and antistrat_analyzer
inside the post_processor.py asyncio.gather — adds zero user-facing latency
because the demo is already visible by the time this runs.

Detects:
  - T-side site executes (coordinated push + utility)
  - CT-side defensive setups (freeze-end positioning)
  - Post-plant retakes (CT repositioning after bomb plant)
  - Defaults / map control patterns (T spread, late rotation)
  - Utility lineups (grenade throw positions matching callout zones)
  - Pistol round strategies (round 1 / 13)
  - Eco / force-buy rounds (low equipment value with notable aggression)

Each detected round is inserted into the detected_tactics table with a
tactical_value_score (0-100), evidence JSON, and recording window ticks.
"""

from __future__ import annotations

import asyncio
import json
import math
import os
from collections import defaultdict
from dataclasses import dataclass, field
from typing import Any

import aiomysql
import pandas as pd
from loguru import logger

# Reuse the zone loader + point-in-polygon from post_processor
from post_processor import _load_zones_for_map, _resolve_zone

# ─── Config ───────────────────────────────────────────────────────────────────

ODIN_BASE_URL: str = os.getenv("ODIN_BASE_URL", "https://api.ordbogen.ai/v1")
ODIN_API_KEY: str  = os.getenv("ODIN_API_KEY", "")
ODIN_MODEL: str    = os.getenv("ODIN_MODEL", "odin-medium")

TICKS_PER_SECOND = 64
EXECUTE_WINDOW_SECONDS = 5.0
DEFAULT_WINDOW_SECONDS = 20.0
UTILITY_WINDOW_SECONDS = 10.0
PISTOL_EQUIP_THRESHOLD = 4000
ECO_EQUIP_THRESHOLD = 5000
FORCE_EQUIP_THRESHOLD = 10000


# ─── Data classes ─────────────────────────────────────────────────────────────

@dataclass
class DetectedTactic:
    round_index: int
    side: str                          # "t" | "ct"
    pattern_type: str                  # "execute" | "ct_setup" | "retake" | "default" | "utility_lineup" | "pistol" | "eco"
    pattern_subtype: str | None
    bombsite: str | None
    confidence: float
    tactical_value_score: float
    evidence: dict[str, Any] = field(default_factory=dict)
    description: str | None = None
    start_tick: int | None = None
    end_tick: int | None = None
    key_ticks: list[dict] = field(default_factory=list)
    player_steamids: list[str] = field(default_factory=list)
    player_names: list[str] = field(default_factory=list)
    team_name: str | None = None
    opponent_name: str | None = None


# ─── Helpers ──────────────────────────────────────────────────────────────────

def _safe_str(v) -> str:
    s = str(v) if v is not None else ""
    return "" if s in ("None", "nan", "NaN") else s

def _safe_float(v, default=0.0) -> float:
    try:
        f = float(v)
        return default if (f != f or abs(f) > 1e9) else f
    except (TypeError, ValueError):
        return default

def _side_str(v) -> str:
    s = str(v or "").upper()
    if s in ("T", "TERRORIST"):
        return "T"
    if s in ("CT", "COUNTERTERRORIST"):
        return "CT"
    return ""

def _seconds_after_tick(seconds: float, ref_tick: int) -> int:
    return ref_tick + int(seconds * TICKS_PER_SECOND)


def _classify_bombsite(x: float, y: float, zones: list[dict]) -> str | None:
    """Resolve which bombsite a position is in, if any."""
    zone = _resolve_zone(x, y, zones)
    if zone is None:
        return None
    name = (zone.get("canonical_name") or "").lower()
    # Common bombsite names across maps
    if "site a" in name or name == "a":
        return "A"
    if "site b" in name or name == "b":
        return "B"
    # Some maps use specific names like "long", "short", "mid", "connector"
    # We only tag bombsites here; non-site zones return None
    return None


def _all_zones_for_pos(x: float, y: float, zones: list[dict]) -> list[str]:
    """Return all zone names containing a point (for position classification)."""
    result = []
    for zone in zones:
        from post_processor import _point_in_polygon
        poly = zone.get("polygon", [])
        if _point_in_polygon(x, y, poly):
            result.append(zone.get("canonical_name", ""))
    return result


def _avg_equipment(round_data: pd.DataFrame, side: str) -> float:
    """Average max equipment_value_total for players on a side in a round."""
    side_col = "side" if "side" in round_data.columns else "team_name"
    col = "equipment_value_total" if "equipment_value_total" in round_data.columns else None
    if col is None or side_col not in round_data.columns:
        return 9999.0
    side_rows = round_data[round_data[side_col].apply(_side_str) == side]
    if side_rows.empty:
        return 9999.0
    return float(side_rows.groupby("steamid")[col].max().mean())


def _team_names(round_data: pd.DataFrame) -> tuple[str, str]:
    """Extract (team_a_name, team_b_name) from round data."""
    side_col = "side" if "side" in round_data.columns else "team_name"
    clan_col = "team_clan_name" if "team_clan_name" in round_data.columns else None
    if clan_col is None:
        return "Team A", "Team B"
    t_rows = round_data[round_data[side_col].apply(_side_str) == "T"]
    ct_rows = round_data[round_data[side_col].apply(_side_str) == "CT"]
    t_name = _safe_str(t_rows[clan_col].iloc[0]) if not t_rows.empty else "Team A"
    ct_name = _safe_str(ct_rows[clan_col].iloc[0]) if not ct_rows.empty else "Team B"
    return t_name, ct_name


def _players_for_side(round_data: pd.DataFrame, side: str) -> list[dict]:
    """Extract unique players for a side with their steamid and name."""
    side_col = "side" if "side" in round_data.columns else "team_name"
    name_col = "name" if "name" in round_data.columns else None
    clan_col = "team_clan_name" if "team_clan_name" in round_data.columns else None
    if side_col not in round_data.columns:
        return []
    side_rows = round_data[round_data[side_col].apply(_side_str) == side]
    if side_rows.empty:
        return []
    dedup = side_rows.drop_duplicates(subset=["steamid"])
    players = []
    for _, row in dedup.iterrows():
        players.append({
            "steamid": _safe_str(row.get("steamid")),
            "name": _safe_str(row.get(name_col)) if name_col else "",
            "team_clan_name": _safe_str(row.get(clan_col)) if clan_col else "",
        })
    return players


# ─── Detection: Site execute ──────────────────────────────────────────────────

def _detect_execute(
    round_num: int,
    round_data: pd.DataFrame,
    kills_df: pd.DataFrame,
    grenades_df: pd.DataFrame,
    r_info: dict,
    zones: list[dict],
) -> DetectedTactic | None:
    """
    T-side site execute: 3+ players converge on a bombsite zone within 5s
    of freeze_end, with utility (smokes/flashes) thrown in the 10s before
    convergence.
    """
    side_col = "side" if "side" in round_data.columns else "team_name"
    freeze_end = int(r_info.get("freeze_end", r_info.get("start", 0)) or 0)
    round_end = int(r_info.get("official_end", r_info.get("end", 0)) or 0)

    window_end = _seconds_after_tick(EXECUTE_WINDOW_SECONDS, freeze_end)

    t_data = round_data[round_data[side_col].apply(_side_str) == "T"]
    if t_data.empty:
        return None

    # Track each T player's position at freeze_end and at window_end
    early = t_data[(t_data["tick"] >= freeze_end) & (t_data["tick"] <= window_end)]
    if early.empty:
        return None

    # For each tick in the window, count how many T players are in each bombsite
    site_convergence: dict[str, list[int]] = defaultdict(list)  # site -> [steamids]
    for tick, tick_group in early.groupby("tick"):
        if tick < freeze_end:
            continue
        per_site: dict[str, set[str]] = defaultdict(set)
        for _, row in tick_group.iterrows():
            x, y = _safe_float(row.get("X")), _safe_float(row.get("Y"))
            site = _classify_bombsite(x, y, zones)
            if site:
                sid = _safe_str(row.get("steamid"))
                per_site[site].add(sid)
        for site, sids in per_site.items():
            if len(sids) >= 3:
                site_convergence[site].extend(sids)

    if not site_convergence:
        return None

    # Pick the site with the most unique players converging
    best_site = max(site_convergence.keys(), key=lambda s: len(set(site_convergence[s])))
    converging_steamids = set(site_convergence[best_site])
    if len(converging_steamids) < 3:
        return None

    # Count utility thrown in the 10s before the window_end
    utility_count = 0
    utility_types = []
    if grenades_df is not None and not grenades_df.empty:
        r_grenades = grenades_df[grenades_df["round_num"] == round_num] if "round_num" in grenades_df.columns else pd.DataFrame()
        if not r_grenades.empty:
            util_window = r_grenades[(r_grenades["tick"] >= freeze_end) & (r_grenades["tick"] <= window_end)]
            for _, g in util_window.iterrows():
                gtype = _safe_str(g.get("grenade_type", "")).lower()
                if gtype in ("smoke", "flashbang", "molotov", "he_grenade"):
                    utility_count += 1
                    utility_types.append(gtype)

    # Count T kills in this round within the window (entry kills)
    t_steamids = set(_safe_str(s) for s in t_data["steamid"].unique())
    round_kills = kills_df[kills_df["round_num"] == round_num] if "round_num" in kills_df.columns else pd.DataFrame()
    entry_kills = 0
    if not round_kills.empty:
        early_kills = round_kills[round_kills["tick"] <= window_end]
        entry_kills = int(early_kills[early_kills["attacker_steamid"].apply(_safe_str).isin(t_steamids)].shape[0])

    # Score
    convergence_score = min(len(converging_steamids) / 5.0, 1.0) * 25
    utility_score = min(utility_count / 3.0, 1.0) * 20
    coordination_score = min(entry_kills / 2.0, 1.0) * 25
    # Educational value: well-defined execute with utility is educational
    edu_score = min(0.3 + (utility_count / 5.0) * 0.7, 1.0) * 30
    total_score = convergence_score + utility_score + coordination_score + edu_score

    # Require at least 2 utility grenades for a real execute
    if utility_count < 2:
        return None

    if total_score < 50:
        return None

    # Key ticks: first contact (first kill), utility ticks
    key_ticks = []
    if entry_kills > 0 and not round_kills.empty:
        first_kill = round_kills[round_kills["tick"] <= window_end].nsmallest(1, "tick")
        if not first_kill.empty:
            key_ticks.append({"tick": int(first_kill.iloc[0]["tick"]), "label": "First entry kill"})
    key_ticks.append({"tick": freeze_end, "label": "Round start (freeze end)"})

    # Player info
    players = _players_for_side(round_data, "T")
    t_name, ct_name = _team_names(round_data)

    return DetectedTactic(
        round_index=round_num,
        side="t",
        pattern_type="execute",
        pattern_subtype=f"{best_site}_site_execute",
        bombsite=best_site,
        confidence=min(1.0, total_score / 100),
        tactical_value_score=round(total_score, 1),
        evidence={
            "converging_players": len(converging_steamids),
            "site": best_site,
            "utility_count": utility_count,
            "utility_types": utility_types,
            "entry_kills": entry_kills,
            "window_seconds": EXECUTE_WINDOW_SECONDS,
        },
        start_tick=freeze_end,
        end_tick=round_end,
        key_ticks=key_ticks,
        player_steamids=[p["steamid"] for p in players],
        player_names=[p["name"] for p in players],
        team_name=t_name,
        opponent_name=ct_name,
    )


# ─── Detection: CT setup ──────────────────────────────────────────────────────

def _detect_ct_setup(
    round_num: int,
    round_data: pd.DataFrame,
    kills_df: pd.DataFrame,
    grenades_df: pd.DataFrame,
    r_info: dict,
    zones: list[dict],
) -> DetectedTactic | None:
    """
    CT defensive setup: classify freeze-end positioning.
    stacked = 3+ on one bombsite
    spread = 2-1-2 or 1-3-1
    aggressive = pushed past defensive line
    Also detects retake patterns after bomb plant.
    """
    side_col = "side" if "side" in round_data.columns else "team_name"
    freeze_end = int(r_info.get("freeze_end", r_info.get("start", 0)) or 0)
    round_end = int(r_info.get("official_end", r_info.get("end", 0)) or 0)

    ct_data = round_data[round_data[side_col].apply(_side_str) == "CT"]
    if ct_data.empty:
        return None

    # Position at freeze_end + 2s (after players settle)
    settle_tick = _seconds_after_tick(2.0, freeze_end)
    settle_data = ct_data[(ct_data["tick"] >= freeze_end) & (ct_data["tick"] <= settle_tick)]
    if settle_data.empty:
        return None

    # Classify CT positions by bombsite
    site_counts: dict[str, set[str]] = defaultdict(set)
    off_site_players = set()
    for _, row in settle_data.iterrows():
        x, y = _safe_float(row.get("X")), _safe_float(row.get("Y"))
        sid = _safe_str(row.get("steamid"))
        site = _classify_bombsite(x, y, zones)
        if site:
            site_counts[site].add(sid)
        else:
            off_site_players.add(sid)

    # Classify the setup
    setup_type = None
    setup_site = None
    if site_counts:
        max_site = max(site_counts.keys(), key=lambda s: len(site_counts[s]))
        max_count = len(site_counts[max_site])
        if max_count >= 3:
            setup_type = "stacked"
            setup_site = max_site
        elif len(site_counts) >= 2 and max_count == 2:
            setup_type = "spread"
        else:
            return None  # 1 player on a site isn't a "setup"
    else:
        return None

    # Utility for CT (smokes, mollies on site)
    utility_count = 0
    utility_types = []
    if grenades_df is not None and not grenades_df.empty:
        r_grenades = grenades_df[grenades_df["round_num"] == round_num] if "round_num" in grenades_df.columns else pd.DataFrame()
        if not r_grenades.empty:
            if "entity_id" in r_grenades.columns:
                r_grenades = r_grenades.drop_duplicates(subset=["entity_id"], keep="first")
            ct_steamids = set(_safe_str(s) for s in ct_data["steamid"].unique())
            for _, g in r_grenades.iterrows():
                thrower = _safe_str(g.get("thrower_steamid", g.get("steamid", "")))
                gtype = _safe_str(g.get("grenade_type", "")).lower()
                if gtype in ("smoke", "molotov", "he_grenade") and thrower in ct_steamids:
                    utility_count += 1
                    utility_types.append(gtype)

    # Score — only stacked setups qualify without utility
    if setup_type != "stacked" and utility_count < 2:
        return None

    setup_clarity = 25.0 if setup_type == "stacked" else 15.0
    coord_score = min((len(site_counts) + 1) / 3.0, 1.0) * 25
    utility_score = min(utility_count / 3.0, 1.0) * 20
    edu_score = 30.0 if setup_type == "stacked" else 20.0
    total_score = setup_clarity + coord_score + utility_score + edu_score

    if total_score < 50:
        return None

    key_ticks = [{"tick": freeze_end, "label": "CT setup (freeze end)"}]

    # First kill timing
    ct_steamids = set(_safe_str(s) for s in ct_data["steamid"].unique())
    round_kills = kills_df[kills_df["round_num"] == round_num] if "round_num" in kills_df.columns else pd.DataFrame()
    if not round_kills.empty:
        ct_kills = round_kills[round_kills["attacker_steamid"].apply(_safe_str).isin(ct_steamids)]
        if not ct_kills.empty:
            first_ct_kill = ct_kills.nsmallest(1, "tick")
            key_ticks.append({"tick": int(first_ct_kill.iloc[0]["tick"]), "label": "First CT kill"})

    players = _players_for_side(round_data, "CT")
    t_name, ct_name = _team_names(round_data)

    return DetectedTactic(
        round_index=round_num,
        side="ct",
        pattern_type="ct_setup",
        pattern_subtype=f"{setup_type}_{setup_site or 'defense'}",
        bombsite=setup_site,
        confidence=min(1.0, total_score / 100),
        tactical_value_score=round(total_score, 1),
        evidence={
            "setup_type": setup_type,
            "site_distribution": {s: len(v) for s, v in site_counts.items()},
            "off_site_players": len(off_site_players),
            "utility_count": utility_count,
            "utility_types": utility_types,
        },
        start_tick=freeze_end,
        end_tick=round_end,
        key_ticks=key_ticks,
        player_steamids=[p["steamid"] for p in players],
        player_names=[p["name"] for p in players],
        team_name=ct_name,
        opponent_name=t_name,
    )


# ─── Detection: Retake ───────────────────────────────────────────────────────

def _detect_retake(
    round_num: int,
    round_data: pd.DataFrame,
    kills_df: pd.DataFrame,
    planted_df: pd.DataFrame,
    r_info: dict,
    zones: list[dict],
) -> DetectedTactic | None:
    """
    Post-plant retake: after bomb plant, CT repositions to retake the site.
    Uses planted_df for plant tick/position, falls back to round meta.
    """
    plant_tick = None
    plant_x = 0.0
    plant_y = 0.0

    # Try planted_df first
    if planted_df is not None and not planted_df.empty:
        r_plants = planted_df[planted_df["round_num"] == round_num] if "round_num" in planted_df.columns else pd.DataFrame()
        if not r_plants.empty:
            plant_tick = int(r_plants.iloc[0].get("tick", 0))
            plant_x = _safe_float(r_plants.iloc[0].get("X"))
            plant_y = _safe_float(r_plants.iloc[0].get("Y"))

    # Fall back to round meta
    if not plant_tick:
        meta_plant = r_info.get("bomb_plant")
        if meta_plant and isinstance(meta_plant, int) and meta_plant > 0:
            plant_tick = int(meta_plant)

    if not plant_tick:
        return None

    # Determine bombsite
    plant_site = None
    if plant_x or plant_y:
        plant_site = _classify_bombsite(plant_x, plant_y, zones)
    if not plant_site:
        meta_site = _safe_str(r_info.get("bomb_site", ""))
        if meta_site.startswith("bombsite_"):
            plant_site = meta_site.replace("bombsite_", "").upper()
        elif meta_site in ("a", "b"):
            plant_site = meta_site.upper()

    if not plant_site:
        return None

    round_end = int(r_info.get("official_end", r_info.get("end", 0)) or 0)

    side_col = "side" if "side" in round_data.columns else "team_name"
    ct_data = round_data[round_data[side_col].apply(_side_str) == "CT"]
    if ct_data.empty:
        return None

    # CT positions 5s after plant vs 10s after plant — are they moving toward site?
    post_plant = ct_data[(ct_data["tick"] >= plant_tick) & (ct_data["tick"] <= round_end)]
    if post_plant.empty:
        return None

    # Count CT players near the planted site in the 5-10s window after plant
    late_window_start = _seconds_after_tick(5.0, plant_tick)
    late_window_end = _seconds_after_tick(10.0, plant_tick)
    late = post_plant[(post_plant["tick"] >= late_window_start) & (post_plant["tick"] <= late_window_end)]

    retaking_players = set()
    if not late.empty and plant_site:
        for _, row in late.iterrows():
            x, y = _safe_float(row.get("X")), _safe_float(row.get("Y"))
            site = _classify_bombsite(x, y, zones)
            if site == plant_site:
                retaking_players.add(_safe_str(row.get("steamid")))

    if len(retaking_players) < 2:
        return None

    # CT kills after plant
    ct_steamids = set(_safe_str(s) for s in ct_data["steamid"].unique())
    round_kills = kills_df[kills_df["round_num"] == round_num] if "round_num" in kills_df.columns else pd.DataFrame()
    retake_kills = 0
    if not round_kills.empty:
        post_kills = round_kills[round_kills["tick"] >= plant_tick]
        retake_kills = int(post_kills[post_kills["attacker_steamid"].apply(_safe_str).isin(ct_steamids)].shape[0])

    # Did CT win the round?
    winner = _safe_str(r_info.get("winner", "")).upper()
    ct_won = winner.startswith("CT")

    # Score — only fire if CT actually retakes (won) or gets 2+ kills
    if not ct_won and retake_kills < 2:
        return None

    retake_coord = min(len(retaking_players) / 4.0, 1.0) * 30
    kill_score = min(retake_kills / 3.0, 1.0) * 25
    edu_score = 25.0 if ct_won else 15.0
    total_score = retake_coord + kill_score + 10 + edu_score

    if total_score < 50:
        return None

    key_ticks = [
        {"tick": plant_tick, "label": "Bomb planted"},
        {"tick": late_window_start, "label": "Retake begins"},
    ]

    players = _players_for_side(round_data, "CT")
    t_name, ct_name = _team_names(round_data)

    return DetectedTactic(
        round_index=round_num,
        side="ct",
        pattern_type="retake",
        pattern_subtype=f"{plant_site}_retake" if plant_site else "retake",
        bombsite=plant_site,
        confidence=min(1.0, total_score / 100),
        tactical_value_score=round(total_score, 1),
        evidence={
            "plant_tick": plant_tick,
            "plant_site": plant_site,
            "retaking_players": len(retaking_players),
            "retake_kills": retake_kills,
            "ct_won": ct_won,
        },
        start_tick=plant_tick,
        end_tick=round_end,
        key_ticks=key_ticks,
        player_steamids=[p["steamid"] for p in players],
        player_names=[p["name"] for p in players],
        team_name=ct_name,
        opponent_name=t_name,
    )


# ─── Detection: Default / map control ────────────────────────────────────────

def _detect_default(
    round_num: int,
    round_data: pd.DataFrame,
    kills_df: pd.DataFrame,
    r_info: dict,
    zones: list[dict],
) -> DetectedTactic | None:
    """
    T-side default: players spread across 3+ zones in first 20s.
    No bombsite convergence. Late-round position shift toward a site.
    """
    side_col = "side" if "side" in round_data.columns else "team_name"
    freeze_end = int(r_info.get("freeze_end", r_info.get("start", 0)) or 0)
    round_end = int(r_info.get("official_end", r_info.get("end", 0)) or 0)

    t_data = round_data[round_data[side_col].apply(_side_str) == "T"]
    if t_data.empty:
        return None

    window_end = _seconds_after_tick(DEFAULT_WINDOW_SECONDS, freeze_end)
    early = t_data[(t_data["tick"] >= freeze_end) & (t_data["tick"] <= window_end)]
    if early.empty:
        return None

    # Count distinct zones T players occupy at the mid-point (10s)
    mid_tick = _seconds_after_tick(10.0, freeze_end)
    mid_data = early[early["tick"] == mid_tick] if mid_tick in early["tick"].values else early[early["tick"] <= mid_tick]
    if mid_data.empty:
        mid_data = early

    occupied_zones = set()
    for _, row in mid_data.iterrows():
        x, y = _safe_float(row.get("X")), _safe_float(row.get("Y"))
        zone_names = _all_zones_for_pos(x, y, zones)
        occupied_zones.update(zone_names)

    # Check for late convergence toward a site (last 10s before round end)
    late_window_start = max(round_end - int(10.0 * TICKS_PER_SECOND), freeze_end + int(20.0 * TICKS_PER_SECOND))
    late = t_data[(t_data["tick"] >= late_window_start) & (t_data["tick"] <= round_end)]
    late_site = None
    if not late.empty:
        site_convergence = defaultdict(set)
        for _, row in late.iterrows():
            x, y = _safe_float(row.get("X")), _safe_float(row.get("Y"))
            site = _classify_bombsite(x, y, zones)
            if site:
                site_convergence[site].add(_safe_str(row.get("steamid")))
        for site, sids in site_convergence.items():
            if len(sids) >= 3:
                late_site = site
                break

    # Only keep defaults with a late rotation, or exceptional map control (5+ zones)
    if not late_site and len(occupied_zones) < 5:
        return None

    # Score
    map_control = min(len(occupied_zones) / 5.0, 1.0) * 25
    transition = 25.0 if late_site else 10.0
    edu = 30.0 if late_site else 15.0
    total_score = map_control + transition + 10.0 + edu

    if total_score < 50:
        return None

    key_ticks = [
        {"tick": freeze_end, "label": "Round start"},
        {"tick": mid_tick, "label": "Map control phase"},
    ]
    if late_site:
        key_ticks.append({"tick": late_window_start, "label": f"Late rotation to {late_site}"})

    players = _players_for_side(round_data, "T")
    t_name, ct_name = _team_names(round_data)

    return DetectedTactic(
        round_index=round_num,
        side="t",
        pattern_type="default",
        pattern_subtype=f"default_{late_site or 'map_control'}",
        bombsite=late_site,
        confidence=min(1.0, total_score / 100),
        tactical_value_score=round(total_score, 1),
        evidence={
            "occupied_zones": sorted(occupied_zones),
            "zone_count": len(occupied_zones),
            "late_site": late_site,
        },
        start_tick=freeze_end,
        end_tick=round_end,
        key_ticks=key_ticks,
        player_steamids=[p["steamid"] for p in players],
        player_names=[p["name"] for p in players],
        team_name=t_name,
        opponent_name=ct_name,
    )


# ─── Detection: Utility lineup ────────────────────────────────────────────────

def _detect_utility_lineup(
    round_num: int,
    round_data: pd.DataFrame,
    grenades_df: pd.DataFrame,
    r_info: dict,
    zones: list[dict],
) -> DetectedTactic | None:
    """
    Utility lineup: 3+ tactical grenades (smokes, mollies) landing in
    callout zones, thrown within a 15s window. Flashbangs don't count
    toward the minimum (they're too common).
    """
    if grenades_df is None or grenades_df.empty:
        return None

    r_grenades = grenades_df[grenades_df["round_num"] == round_num] if "round_num" in grenades_df.columns else pd.DataFrame()
    if r_grenades.empty:
        return None

    freeze_end = int(r_info.get("freeze_end", r_info.get("start", 0)) or 0)
    round_end = int(r_info.get("official_end", r_info.get("end", 0)) or 0)

    # Deduplicate by entity_id if present (real awpy grenades are one row per
    # throw, but reconstructed per-tick data has multiple rows per grenade).
    if "entity_id" in r_grenades.columns:
        r_grenades = r_grenades.drop_duplicates(subset=["entity_id"], keep="first")

    # Only count tactical grenades that land in named zones
    utility_throws = []
    for _, g in r_grenades.iterrows():
        gtype = _safe_str(g.get("grenade_type", "")).lower()
        if gtype not in ("smoke", "molotov", "he_grenade"):
            continue
        x = _safe_float(g.get("X"))
        y = _safe_float(g.get("Y"))
        zone_names = _all_zones_for_pos(x, y, zones)
        if zone_names:
            utility_throws.append({
                "type": gtype,
                "land_zones": zone_names,
                "tick": int(g.get("tick", 0)),
                "x": x,
                "y": y,
            })

    # Require at least 3 smokes+mollies to qualify as a "lineup"
    smoke_count = sum(1 for t in utility_throws if t["type"] == "smoke")
    molotov_count = sum(1 for t in utility_throws if t["type"] == "molotov")
    if smoke_count + molotov_count < 3:
        return None

    he_count = sum(1 for t in utility_throws if t["type"] == "he_grenade")

    # Score
    precision = min((smoke_count + molotov_count) / 5.0, 1.0) * 30
    utility_quality = min((smoke_count * 2 + molotov_count * 1.5) / 6.0, 1.0) * 25
    coordination = 25.0
    edu = 20.0
    total_score = precision + utility_quality + coordination + edu

    if total_score < 50:
        return None

    key_ticks = [{"tick": t["tick"], "label": f"{t['type']} on {t['land_zones'][0]}"} for t in utility_throws[:5]]

    # Determine side from the grenade throwers
    side_col = "side" if "side" in round_data.columns else "team_name"
    t_data = round_data[round_data[side_col].apply(_side_str) == "T"]
    ct_data = round_data[round_data[side_col].apply(_side_str) == "CT"]
    t_name, ct_name = _team_names(round_data)

    t_steamids = set(_safe_str(s) for s in t_data["steamid"].unique())
    ct_steamids = set(_safe_str(s) for s in ct_data["steamid"].unique())
    t_throws = sum(1 for ut in utility_throws if any(
        _safe_str(g.get("thrower_steamid", g.get("steamid", ""))) in t_steamids
        for _, g in r_grenades[r_grenades["tick"] == ut["tick"]].iterrows()
    ))
    ct_throws = sum(1 for ut in utility_throws if any(
        _safe_str(g.get("thrower_steamid", g.get("steamid", ""))) in ct_steamids
        for _, g in r_grenades[r_grenades["tick"] == ut["tick"]].iterrows()
    ))
    side = "t" if t_throws >= ct_throws else "ct"
    players = _players_for_side(round_data, side.upper())
    team_name = t_name if side == "t" else ct_name
    opp_name = ct_name if side == "t" else t_name

    return DetectedTactic(
        round_index=round_num,
        side=side,
        pattern_type="utility_lineup",
        pattern_subtype="utility_coordinated",
        bombsite=None,
        confidence=min(1.0, total_score / 100),
        tactical_value_score=round(total_score, 1),
        evidence={
            "throws": len(utility_throws),
            "smokes": smoke_count,
            "molotovs": molotov_count,
            "he_grenades": he_count,
            "land_zones": list(set(z for t in utility_throws for z in t["land_zones"])),
        },
        start_tick=freeze_end,
        end_tick=round_end,
        key_ticks=key_ticks,
        player_steamids=[p["steamid"] for p in players],
        player_names=[p["name"] for p in players],
        team_name=team_name,
        opponent_name=opp_name,
    )


# ─── Detection: Pistol round ─────────────────────────────────────────────────

def _detect_pistol(
    round_num: int,
    round_data: pd.DataFrame,
    kills_df: pd.DataFrame,
    r_info: dict,
) -> DetectedTactic | None:
    """
    Pistol round: round 1 or 13. Equipment value below pistol threshold.
    """
    if round_num not in (1, 13):
        return None

    freeze_end = int(r_info.get("freeze_end", r_info.get("start", 0)) or 0)
    round_end = int(r_info.get("official_end", r_info.get("end", 0)) or 0)

    t_equip = _avg_equipment(round_data, "T")
    ct_equip = _avg_equipment(round_data, "CT")

    if t_equip > PISTOL_EQUIP_THRESHOLD * 5 and ct_equip > PISTOL_EQUIP_THRESHOLD * 5:
        return None

    # Determine which side has pistols (lower equipment)
    pistol_side = "t" if t_equip < ct_equip else "ct"
    if t_equip > PISTOL_EQUIP_THRESHOLD * 5 and ct_equip > PISTOL_EQUIP_THRESHOLD * 5:
        return None

    # Kills
    round_kills = kills_df[kills_df["round_num"] == round_num] if "round_num" in kills_df.columns else pd.DataFrame()
    total_kills = len(round_kills) if not round_kills.empty else 0

    winner = _safe_str(r_info.get("winner", "")).upper()

    # Score: pistol rounds are always educational
    clarity = 25.0
    aggression = min(total_kills / 5.0, 1.0) * 25
    coord = 25.0
    edu = 30.0
    total_score = clarity + aggression + coord + edu

    key_ticks = [{"tick": freeze_end, "label": "Pistol round start"}]
    if total_kills > 0 and not round_kills.empty:
        first_kill = round_kills.nsmallest(1, "tick")
        if not first_kill.empty:
            key_ticks.append({"tick": int(first_kill.iloc[0]["tick"]), "label": "First kill"})

    players = _players_for_side(round_data, pistol_side.upper())
    t_name, ct_name = _team_names(round_data)
    team_name = t_name if pistol_side == "t" else ct_name
    opp_name = ct_name if pistol_side == "t" else t_name

    return DetectedTactic(
        round_index=round_num,
        side=pistol_side,
        pattern_type="pistol",
        pattern_subtype=f"pistol_round_{round_num}",
        bombsite=None,
        confidence=min(1.0, total_score / 100),
        tactical_value_score=round(total_score, 1),
        evidence={
            "round": round_num,
            "t_equip": round(t_equip, 0),
            "ct_equip": round(ct_equip, 0),
            "total_kills": total_kills,
            "winner": winner,
        },
        start_tick=freeze_end,
        end_tick=round_end,
        key_ticks=key_ticks,
        player_steamids=[p["steamid"] for p in players],
        player_names=[p["name"] for p in players],
        team_name=team_name,
        opponent_name=opp_name,
    )


# ─── Detection: Eco / force-buy ───────────────────────────────────────────────

def _detect_eco(
    round_num: int,
    round_data: pd.DataFrame,
    kills_df: pd.DataFrame,
    r_info: dict,
) -> DetectedTactic | None:
    """
    Eco or force-buy: low equipment value with notable aggression or
    unexpected result (win/kill count).
    """
    if round_num in (1, 13):
        return None  # Handled by pistol detection

    freeze_end = int(r_info.get("freeze_end", r_info.get("start", 0)) or 0)
    round_end = int(r_info.get("official_end", r_info.get("end", 0)) or 0)

    t_equip = _avg_equipment(round_data, "T")
    ct_equip = _avg_equipment(round_data, "CT")

    # Determine if either side is eco/force
    eco_side = None
    eco_type = None
    eco_equip = 9999.0

    if t_equip < ECO_EQUIP_THRESHOLD:
        eco_side = "t"
        eco_equip = t_equip
        eco_type = "eco" if t_equip < ECO_EQUIP_THRESHOLD else "force"
    elif ct_equip < ECO_EQUIP_THRESHOLD:
        eco_side = "ct"
        eco_equip = ct_equip
        eco_type = "eco" if ct_equip < ECO_EQUIP_THRESHOLD else "force"

    if eco_side is None:
        return None

    # Check for notable aggression (early kills) or unexpected result
    round_kills = kills_df[kills_df["round_num"] == round_num] if "round_num" in kills_df.columns else pd.DataFrame()
    eco_steamids = set()
    side_col = "side" if "side" in round_data.columns else "team_name"
    eco_data = round_data[round_data[side_col].apply(_side_str) == eco_side.upper()]
    if not eco_data.empty:
        eco_steamids = set(_safe_str(s) for s in eco_data["steamid"].unique())

    eco_kills = 0
    if not round_kills.empty:
        eco_kills = int(round_kills[round_kills["attacker_steamid"].apply(_safe_str).isin(eco_steamids)].shape[0])

    winner = _safe_str(r_info.get("winner", "")).upper()
    eco_won = winner.startswith(eco_side.upper())

    # Only detect if there's something notable — eco win or 3+ kills
    if eco_kills < 3 and not eco_won:
        return None

    # Score
    surprise = 25.0 if eco_won else 15.0
    aggression = min(eco_kills / 4.0, 1.0) * 25
    coord = 25.0
    edu = 25.0 if eco_won else 15.0
    total_score = surprise + aggression + coord + edu

    if total_score < 55:
        return None

    key_ticks = [{"tick": freeze_end, "label": f"{eco_type} round start"}]
    if eco_kills > 0 and not round_kills.empty:
        eco_kill_ticks = round_kills[round_kills["attacker_steamid"].apply(_safe_str).isin(eco_steamids)]
        if not eco_kill_ticks.empty:
            first = eco_kill_ticks.nsmallest(1, "tick")
            if not first.empty:
                key_ticks.append({"tick": int(first.iloc[0]["tick"]), "label": "First eco kill"})

    players = _players_for_side(round_data, eco_side.upper())
    t_name, ct_name = _team_names(round_data)
    team_name = t_name if eco_side == "t" else ct_name
    opp_name = ct_name if eco_side == "t" else t_name

    return DetectedTactic(
        round_index=round_num,
        side=eco_side,
        pattern_type="eco",
        pattern_subtype=f"{eco_type}_round_{round_num}",
        bombsite=None,
        confidence=min(1.0, total_score / 100),
        tactical_value_score=round(total_score, 1),
        evidence={
            "eco_side": eco_side,
            "eco_type": eco_type,
            "equip_value": round(eco_equip, 0),
            "kills": eco_kills,
            "won_round": eco_won,
        },
        start_tick=freeze_end,
        end_tick=round_end,
        key_ticks=key_ticks,
        player_steamids=[p["steamid"] for p in players],
        player_names=[p["name"] for p in players],
        team_name=team_name,
        opponent_name=opp_name,
    )


# ─── AI description via Odin ──────────────────────────────────────────────────

async def _generate_ai_description(
    tactic: DetectedTactic,
    map_name: str,
) -> str | None:
    """Generate a natural-language description of the tactic via Odin AI."""
    if not ODIN_API_KEY:
        return None

    import aiohttp

    prompt = (
        f"You are a CS2 tactical analyst. Describe this round tactic in 1-2 sentences.\n"
        f"Map: {map_name}\n"
        f"Round: {tactic.round_index}\n"
        f"Side: {tactic.side.upper()}\n"
        f"Pattern: {tactic.pattern_type} ({tactic.pattern_subtype or 'N/A'})\n"
        f"Bombsite: {tactic.bombsite or 'N/A'}\n"
        f"Score: {tactic.tactical_value_score}/100\n"
        f"Evidence: {json.dumps(tactic.evidence)}\n"
        f"Team: {tactic.team_name}\n"
        f"Opponent: {tactic.opponent_name}\n"
        f"Provide a concise, informative description for a tactics website admin."
    )

    try:
        async with aiohttp.ClientSession() as session:
            async with session.post(
                f"{ODIN_BASE_URL}/chat/completions",
                json={
                    "model": ODIN_MODEL,
                    "messages": [{"role": "user", "content": prompt}],
                    "max_tokens": 150,
                    "temperature": 0.3,
                },
                headers={"Authorization": f"Bearer {ODIN_API_KEY}"},
                timeout=aiohttp.ClientTimeout(total=45),
            ) as resp:
                if resp.status == 200:
                    data = await resp.json()
                    content = data.get("choices", [{}])[0].get("message", {}).get("content", "")
                    return content.strip() if content else None
    except Exception as e:
        logger.warning(f"[TacticDetector] Odin description failed: {e}")

    return None


def _pattern_description(tactic: DetectedTactic, map_name: str) -> str:
    """Fallback pattern-based description when AI is unavailable."""
    ptype = tactic.pattern_type
    subtype = tactic.pattern_subtype or ""
    ev = tactic.evidence

    if ptype == "execute":
        site = tactic.bombsite or "a bombsite"
        return f"Coordinated {site} site execute by {tactic.team_name} on {map_name}. {ev.get('converging_players', 0)} players converge with {ev.get('utility_count', 0)} utility grenades."
    elif ptype == "ct_setup":
        setup = ev.get("setup_type", "defensive")
        return f"{setup.capitalize()} CT setup by {tactic.team_name} on {map_name}. {ev.get('site_distribution', {})} positioning with {ev.get('utility_count', 0)} utility grenades."
    elif ptype == "retake":
        site = tactic.bombsite or "the"
        return f"Post-plant {site} retake by {tactic.team_name} on {map_name}. {ev.get('retaking_players', 0)} players retake with {ev.get('retake_kills', 0)} kills. {'Round won.' if ev.get('ct_won') else 'Round lost.'}"
    elif ptype == "default":
        zones = ev.get("occupied_zones", [])
        return f"Default map control by {tactic.team_name} on {map_name}. Spread across {ev.get('zone_count', 0)} zones ({', '.join(zones[:4])}).{f' Late rotation to {tactic.bombsite}.' if tactic.bombsite else ''}"
    elif ptype == "utility_lineup":
        return f"Coordinated utility usage by {tactic.team_name} on {map_name}. {ev.get('smokes', 0)} smokes, {ev.get('molotovs', 0)} mollies, {ev.get('flashes', 0)} flashes landing on {', '.join(ev.get('land_zones', [])[:3])}."
    elif ptype == "pistol":
        return f"Pistol round {tactic.round_index} on {map_name}. {tactic.team_name} ({tactic.side.upper()}) with ${ev.get('t_equip' if tactic.side == 't' else 'ct_equip', 0)} equipment vs ${ev.get('ct_equip' if tactic.side == 't' else 't_equip', 0)}. {ev.get('total_kills', 0)} kills. Winner: {ev.get('winner', 'N/A')}."
    elif ptype == "eco":
        return f"{ev.get('eco_type', 'eco').capitalize()} round by {tactic.team_name} on {map_name}. ${ev.get('equip_value', 0)} equipment, {ev.get('kills', 0)} kills. {'Won the round!' if ev.get('won_round') else 'Round lost.'}"
    return f"{tactic.pattern_type} on {map_name}, round {tactic.round_index}."


# ─── DB insertion ─────────────────────────────────────────────────────────────

async def _insert_tactics(
    pool: aiomysql.Pool,
    demo_id: int,
    tactics: list[DetectedTactic],
) -> None:
    if not tactics:
        return

    async with pool.acquire() as conn:
        async with conn.cursor() as cursor:
            for t in tactics:
                await cursor.execute(
                    """INSERT INTO detected_tactics
                       (demo_id, round_index, side, pattern_type, pattern_subtype,
                        bombsite, confidence, tactical_value_score, evidence,
                        description, start_tick, end_tick, key_ticks,
                        player_steamids, player_names, team_name, opponent_name,
                        status, created_at, updated_at)
                       VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 'detected', NOW(), NOW())""",
                    (
                        demo_id, t.round_index, t.side, t.pattern_type, t.pattern_subtype,
                        t.bombsite, t.confidence, t.tactical_value_score,
                        json.dumps(t.evidence), t.description,
                        t.start_tick, t.end_tick, json.dumps(t.key_ticks),
                        json.dumps(t.player_steamids), json.dumps(t.player_names),
                        t.team_name, t.opponent_name,
                    ),
                )
            await conn.commit()
    logger.info(f"[TacticDetector] Inserted {len(tactics)} detected tactics for demo {demo_id}")


# ─── Main entry point ─────────────────────────────────────────────────────────

async def detect_tactics(
    pool: aiomysql.Pool,
    demo_id: int,
    map_name: str,
    frame_df: pd.DataFrame,
    kills_df: pd.DataFrame,
    rounds_df: pd.DataFrame,
    smokes_df: pd.DataFrame | None = None,
    infernos_df: pd.DataFrame | None = None,
    grenades_df: pd.DataFrame | None = None,
    shots_df: pd.DataFrame | None = None,
    planted_df: pd.DataFrame | None = None,
    stats_json_list: list[dict] | None = None,
) -> None:
    """
    Detect tactics from a parsed demo. Runs as part of the post-processing
    pipeline, concurrent with spatial_processor and antistrat_analyzer.
    """
    try:
        zones = await _load_zones_for_map(pool, map_name)
        if not zones:
            logger.info(f"[TacticDetector] No callout zones for {map_name}, skipping detection")
            return

        # Build rounds metadata
        rounds_meta: list[dict] = []
        if rounds_df is not None and not rounds_df.empty:
            rounds_meta = rounds_df.to_dict(orient="records")

        if not rounds_meta:
            logger.warning(f"[TacticDetector] No round metadata for demo {demo_id}")
            return

        # Team compositions from round 1
        r1 = frame_df[frame_df["round_num"] == 1] if "round_num" in frame_df.columns else pd.DataFrame()

        detected: list[DetectedTactic] = []

        for r_info in rounds_meta:
            round_num = int(r_info.get("round_num", r_info.get("round_number", 0)) or 0)
            if round_num <= 0:
                continue

            round_data = frame_df[frame_df["round_num"] == round_num] if "round_num" in frame_df.columns else pd.DataFrame()
            if round_data.empty:
                continue

            # Run all detectors — keep only the single best per round
            candidates: list[DetectedTactic] = []

            # Pistol round (highest priority, exclusive)
            pistol = _detect_pistol(round_num, round_data, kills_df, r_info)
            if pistol:
                candidates.append(pistol)

            # Eco/force (also exclusive with pistol)
            eco = _detect_eco(round_num, round_data, kills_df, r_info)
            if eco:
                candidates.append(eco)

            # Execute (T-side)
            execute = _detect_execute(round_num, round_data, kills_df, grenades_df, r_info, zones)
            if execute:
                candidates.append(execute)

            # CT setup
            ct_setup = _detect_ct_setup(round_num, round_data, kills_df, grenades_df, r_info, zones)
            if ct_setup:
                candidates.append(ct_setup)

            # Retake
            retake = _detect_retake(round_num, round_data, kills_df, planted_df, r_info, zones)
            if retake:
                candidates.append(retake)

            # Default / map control
            default = _detect_default(round_num, round_data, kills_df, r_info, zones)
            if default:
                candidates.append(default)

            # Utility lineup
            utility = _detect_utility_lineup(round_num, round_data, grenades_df, r_info, zones)
            if utility:
                candidates.append(utility)

            # Keep only the single highest-scoring tactic per round
            if candidates:
                best = max(candidates, key=lambda t: t.tactical_value_score)
                detected.append(best)

        # Generate AI descriptions for high-scoring tactics (batch)
        if ODIN_API_KEY:
            for t in detected:
                if t.tactical_value_score >= 60:
                    t.description = await _generate_ai_description(t, map_name)

        # Fill in pattern-based descriptions for any remaining
        for t in detected:
            if not t.description:
                t.description = _pattern_description(t, map_name)

        await _insert_tactics(pool, demo_id, detected)

        logger.info(f"[TacticDetector] Detection complete for demo {demo_id}: {len(detected)} tactics found")

    except Exception as exc:
        logger.error(f"[TacticDetector] Detection failed for demo {demo_id}: {exc}")
