"""
enrichment.py

Stamps callout labels onto a stored round record so it can be served as a
curated "admin" demo. This replaces the old admindemoparser awpy-callout step,
converging on the maintained `map_callout_zones` callout system (the one the
/admin/callouts editor manages and post_processor already uses).

A round record is the decompressed frame_data structure:
    { "ticks": { tick: { "players": [...], "grenades": [...], ... } },
      "meta": {...}, "smokes": [...], "infernos": [...], "kills": {...} }

We add, matching the keys the 3D viewer reads:
  - players[].place          — callout under the player's (X, Y)
  - ticks[t].grenades[].place / .from
  - smokes[].place / .from   (referenced by start_tick)
  - infernos[].place / .from

`place` is the landing callout; `from` is the thrower's callout at the throw
tick. Zones come from `post_processor._load_zones_for_map`; resolution reuses
its ray-casting `_resolve_zone`.
"""

from __future__ import annotations

from typing import Optional

from post_processor import _resolve_zone


def _place(x, y, zones: list, cache: dict) -> Optional[str]:
    """Resolve the callout name under (x, y), memoized per rounded coordinate."""
    if x is None or y is None or not zones:
        return None
    key = (round(float(x)), round(float(y)))
    if key in cache:
        return cache[key]
    zone = _resolve_zone(float(x), float(y), zones)
    name = zone["canonical_name"] if zone else None
    cache[key] = name
    return name


def _players_at(ticks: dict, tick) -> list:
    """Players at a tick, tolerating int vs str keys (JSON round-trips to str)."""
    if tick is None:
        return []
    bucket = ticks.get(tick)
    if bucket is None:
        bucket = ticks.get(str(tick))
    return (bucket or {}).get("players", []) if bucket else []


def _thrower_place(players: list, thrower_steamid) -> Optional[str]:
    """The thrower's stamped `place` among the given players, if found."""
    if thrower_steamid is None:
        return None
    target = str(thrower_steamid)
    return next(
        (p.get("place") for p in players if str(p.get("steamid")) == target),
        None,
    )


def enrich_record(record: dict, zones: list, cache: Optional[dict] = None) -> dict:
    """Stamp `place`/`from` onto players, grenades, smokes and infernos in place."""
    if cache is None:
        cache = {}

    ticks = record.get("ticks") or {}

    # 1. Players first — grenade `from` lookups depend on these being set.
    for bucket in ticks.values():
        for player in (bucket or {}).get("players", []):
            player["place"] = _place(player.get("X"), player.get("Y"), zones, cache)

    # 2. Grenades live per-tick; thrower is in the same tick's players.
    for bucket in ticks.values():
        players = (bucket or {}).get("players", [])
        for grenade in (bucket or {}).get("grenades", []):
            grenade["place"] = _place(grenade.get("X"), grenade.get("Y"), zones, cache)
            grenade["from"] = _thrower_place(players, grenade.get("thrower_steamid"))

    # 3. Smokes / infernos are top-level lists referencing a start_tick.
    for item in (record.get("smokes") or []):
        item["place"] = _place(item.get("X"), item.get("Y"), zones, cache)
        item["from"] = _thrower_place(
            _players_at(ticks, item.get("start_tick")), item.get("thrower_steamid")
        )

    for item in (record.get("infernos") or []):
        item["place"] = _place(item.get("X"), item.get("Y"), zones, cache)
        item["from"] = _thrower_place(
            _players_at(ticks, item.get("start_tick")), item.get("thrower_steamid")
        )

    return record
