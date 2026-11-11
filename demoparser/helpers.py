from collections import defaultdict, Counter
import json
import pandas as pd

def get_total_kda_dict(kills):
    """Per-player KDA counts for t / ct / all sides.

    Single O(N) pass over the kills records with Counter-based accumulators
    instead of the previous O(N × steamids) repeated full scans.
    """
    total_KDA = []

    kills_records = kills.to_dict('records')

    # name lookup: steamid -> first known attacker_name
    name_by_sid = {}
    for k in kills_records:
        sid = k.get("attacker_steamid")
        if sid is None or pd.isna(sid):
            continue
        nm = k.get("attacker_name")
        if nm is not None and not pd.isna(nm) and sid not in name_by_sid:
            name_by_sid[sid] = nm

    # Counters keyed by (steamid, side)
    kills_c   = Counter()
    deaths_c  = Counter()
    assists_c = Counter()

    for k in kills_records:
        a = k.get("attacker_steamid")
        v = k.get("victim_steamid")
        h = k.get("assister_steamid")
        a_side = k.get("attacker_side")
        v_side = k.get("victim_side")
        h_side = k.get("assister_side")

        if a is not None and not pd.isna(a):
            kills_c[(a, "all")] += 1
            if a_side in ("t", "ct"):
                kills_c[(a, a_side)] += 1
        if v is not None and not pd.isna(v):
            deaths_c[(v, "all")] += 1
            if v_side in ("t", "ct"):
                deaths_c[(v, v_side)] += 1
        if h is not None and not pd.isna(h):
            assists_c[(h, "all")] += 1
            if h_side in ("t", "ct"):
                assists_c[(h, h_side)] += 1

    for sid, name in name_by_sid.items():
        for side in ("t", "ct", "all"):
            total_KDA.append({
                "name": name,
                "steamid": sid,
                "side": side,
                "kills": kills_c.get((sid, side), 0),
                "deaths": deaths_c.get((sid, side), 0),
                "assists": assists_c.get((sid, side), 0),
            })

    return total_KDA

def get_hitgroup_dict(kills):
    result = []
    kills["attacker_steamid"] = kills["attacker_steamid"].astype(str)

    grouped = kills.groupby(["attacker_steamid", "attacker_side", "hitgroup"]).size().unstack(fill_value=0)

    for steamid in kills["attacker_steamid"].unique():
        if steamid == "None" or pd.isna(steamid):
            continue
        
        filtered_kills = kills[kills["attacker_steamid"] == steamid]
        if filtered_kills.empty:
            continue

        name = filtered_kills["attacker_name"].iloc[0]
        for side in ["t", "ct", "all"]:
            if side == "all":
                hit_counts = grouped.loc[steamid].sum() if steamid in grouped.index else {}
            else:
                hit_counts = grouped.xs((steamid, side), level=["attacker_steamid", "attacker_side"], drop_level=False).sum() if (steamid, side) in grouped.index else {}

            result.append({
                "name": name,
                "steamid": steamid,
                "side": side,
                "headshots": int(hit_counts.get("head", 0) + hit_counts.get("neck", 0)),
                "chest": int(hit_counts.get("chest", 0)),
                "stomach": int(hit_counts.get("stomach", 0)),
                "left_arm": int(hit_counts.get("left_arm", 0)),
                "right_arm": int(hit_counts.get("right_arm", 0)),
                "left_leg": int(hit_counts.get("left_leg", 0)),
                "right_leg": int(hit_counts.get("right_leg", 0)),
                "generic": int(hit_counts.get("generic", 0))
            })

    return result

def parse_bomb_events(pickups, drops, plants, last_round_tick):
    tickdict = {}

    for drop in drops.to_dict(orient="records"):
        # Find the next closest pickup tick, default to last_round_tick
        closest_pickup = pickups.loc[pickups["tick"] > drop["tick"]]
        if not closest_pickup.empty:
            closest_pickup = closest_pickup.iloc[0]
        else:
            closest_pickup = None

        next_tick = (
            closest_pickup["tick"]
            if closest_pickup is not None and not closest_pickup.empty
            else last_round_tick
        )

        max_ticks = min(next_tick - drop["tick"], 64)

        # Set closest_pickup to drop if there are no pickups, this is not totally accurate but it's better than nothing
        if closest_pickup is None or closest_pickup.empty:
            closest_pickup = drop

        for tick in range(drop["tick"], drop["tick"] + max_ticks):
            t = (tick - drop["tick"]) / max_ticks
            # Safely handle None values for coordinates
            drop_X = drop["user_X"] if drop["user_X"] is not None else 0.0
            drop_Y = drop["user_Y"] if drop["user_Y"] is not None else 0.0
            drop_Z = drop["user_Z"] if drop["user_Z"] is not None else 0.0
            pickup_X = closest_pickup["user_X"] if closest_pickup["user_X"] is not None else 0.0
            pickup_Y = closest_pickup["user_Y"] if closest_pickup["user_Y"] is not None else 0.0
            pickup_Z = closest_pickup["user_Z"] if closest_pickup["user_Z"] is not None else 0.0

            tickdict[tick] = {
                "X": float(
                    drop_X + t * (pickup_X - drop_X)
                ),
                "Y": float(
                    drop_Y + t * (pickup_Y - drop_Y)
                ),
                "Z": float(
                    drop_Z + t * (pickup_Z - drop_Z)
                ),
                "planted_ticks": None,
            }

        for tick in range(drop["tick"] + max_ticks, next_tick):
            tickdict[tick] = {
                "X": float(closest_pickup["user_X"]) if closest_pickup["user_X"] is not None else 0.0,
                "Y": float(closest_pickup["user_Y"]) if closest_pickup["user_Y"] is not None else 0.0,
                "Z": float(closest_pickup["user_Z"]) if closest_pickup["user_Z"] is not None else 0.0,
                "planted_ticks": None,
            }

    plants = plants.to_dict(orient="records")
    if len(plants) == 0:
        return tickdict

    plant = plants[0]
    max_tick = min(last_round_tick, 64 * 40 + plant["tick"])
    for tick in range(plant["tick"], max_tick):
        tickdict[tick] = {
            "X": float(plant["user_X"]),
            "Y": float(plant["user_Y"]),
            "Z": float(plant["user_Z"]),
            "planted_ticks": tick - plant["tick"],
        }

    return tickdict

def polars_to_pandas_safe(polars_df):
    """
    Preserve SteamIDs as exact string representations without scientific notation.

    No .clone() — polars `with_columns` is non-mutating, so the caller's frame
    is never modified. The previous clone doubled the memory of every converted
    frame, and casting one column at a time produced N intermediate frames.
    All eligible columns are now cast in a single `with_columns` call.
    """
    import polars as pl

    cast_cols = [
        pl.col(col).cast(pl.Utf8)
        for col in polars_df.columns
        if str(polars_df.schema[col]) in ('UInt64', 'u64', 'Int64', 'i64')
    ]
    if cast_cols:
        polars_df = polars_df.with_columns(cast_cols)

    return polars_df.to_pandas()
def create_heatmap_dict(kills):
    heatmap_kill_dict = defaultdict(lambda: defaultdict(lambda: defaultdict(int)))
    heatmap_death_dict = defaultdict(lambda: defaultdict(lambda: defaultdict(int)))
    
    if "attacker_steamid" in kills.columns:
        kills["attacker_steamid"] = kills["attacker_steamid"].astype(str)
    
    if "victim_steamid" in kills.columns:
        kills["victim_steamid"] = kills["victim_steamid"].astype(str)

    for record in kills.to_dict(orient="records"):
        # Skip records with missing or identical Steam IDs
        if not record["attacker_steamid"] or not record["victim_steamid"] or pd.isna(record["attacker_steamid"]) or pd.isna(record["victim_steamid"]):
            continue
        if record["attacker_steamid"] == "None" or record["victim_steamid"] == "None":
            continue
        if record["attacker_steamid"] == record["victim_steamid"]:
            continue
        
        # Use strings directly instead of converting to int
        heatmap_kill_dict[record["attacker_steamid"]][record["round_num"]][(record["attacker_X"], record["attacker_Y"])] += 1
        heatmap_death_dict[record["victim_steamid"]][record["round_num"]][(record["victim_X"], record["victim_Y"])] += 1

    heatmap_dict = {
        "kills": json.dumps(
                {str(id): {round: [{"x": coords[0], "y": coords[1], "value": value} for coords, value in round_data.items() ] for round, round_data in id_data.items()} for id, id_data in heatmap_kill_dict.items()}
            ),
        "deaths": json.dumps(
                {str(id): {round: [{"x": coords[0], "y": coords[1], "value": value} for coords, value in round_data.items() ] for round, round_data in id_data.items()} for id, id_data in heatmap_death_dict.items()}
            )
        }

    return heatmap_dict
        
_TICK_FIELDS = frozenset({
    "tick", "X", "Y", "Z",
    "active_weapon_name", "armor_value", "armor", "balance", "health",
    "inventory", "name", "steamid", "team_clan_name", "side", "yaw",
    "kills_total", "deaths_total", "assists_total", "pitch",
})

def create_tick_dict(round_data, columns=None):
    # Narrow to only the fields we need before calling to_dict (much less work in C)
    keep = [c for c in round_data.columns if c in _TICK_FIELDS]
    df = round_data[keep].copy()

    # Coalesce armor/armor_value → armor_value (both column names exist in some awpy versions)
    if "armor" in df.columns:
        if "armor_value" in df.columns:
            df["armor_value"] = df["armor_value"].where(
                df["armor_value"].notna() & (df["armor_value"] != 0), df["armor"]
            )
            df.drop(columns=["armor"], inplace=True)
        else:
            df.rename(columns={"armor": "armor_value"}, inplace=True)

    if "side" in df.columns:
        df.rename(columns={"side": "team_name"}, inplace=True)

    # Vectorised coordinate rounding + NaN fill (C-level, no Python loop needed)
    for col in ("X", "Y", "Z"):
        if col in df.columns:
            df[col] = df[col].round(1).fillna(0)

    str_cols  = [c for c in ("active_weapon_name", "name", "steamid", "team_clan_name", "team_name") if c in df.columns]
    num_cols  = [c for c in ("balance", "health", "armor_value", "yaw", "kills_total", "deaths_total", "assists_total", "pitch") if c in df.columns]
    if str_cols:
        df[str_cols] = df[str_cols].fillna("")
    if num_cols:
        df[num_cols] = df[num_cols].fillna(0)

    # Single to_dict call (C-level); Python loop only does pop+append (~2 ops/row vs ~17 before)
    records = df.to_dict(orient="records")
    tick_dict = defaultdict(lambda: {"players": [], "weapon_fires": [], "bomb": None, "grenades": []})
    for record in records:
        tick_dict[record.pop("tick")]["players"].append(record)
    return tick_dict

def create_filtered_kill_tick_dict(round_data):
    # Fields to keep
    fields_to_keep = [
        "tick", "headshot", "thrusmoke", "victim_name", "victim_side", 
        "assistedflash", "attacker_side", "attackerblind", "attackerinair", 
        "victim_steamid", "assister_steamid", "attacker_steamid", 
        "attacker_name", "assister_name", "noscope", "weapon"
    ]
    
    data_records = round_data.to_dict(orient="records")
    tick_dict = defaultdict(list)

    for record in data_records:
        # Filter to only keep desired fields
        filtered_record = {field: record.get(field) for field in fields_to_keep if field in record}
        tick_dict[record["tick"]].append(filtered_record)

    # Sort by tick (lowest to highest)
    sorted_tick_dict = dict(sorted(tick_dict.items()))
    
    return sorted_tick_dict
def create_kill_tick_dict(round_data):
    data_records = round_data.to_dict(orient="records")
    tick_dict = defaultdict(list)

    for record in data_records:
        tick_dict[record["tick"]].append(record)

    return tick_dict

import numpy as np

def create_stats_dict(stats, frame_df, rounds):
    """Build the per-player stats JSON.

    Optimised to avoid the previous O(players × frame_df) repeated full scans
    and the per-round `.copy()` of all frames for that round. We group frame_df
    by steamid once for the per-player aggregates, and group by round_num once
    for the clutch detection, reusing the same groupby slices.
    """
    stats_dict = defaultdict(lambda: defaultdict(lambda: defaultdict(int)))
    stats_records = stats.to_dict(orient="records")

    # Process all stats records first to build the base structure
    for stat in stats_records:
        name = stat['name']
        side = stat['side']
        stats_dict[name][side]['n_rounds'] = stat['n_rounds']
        stats_dict[name][side]['dmg'] = stat['dmg']
        stats_dict[name][side]['adr'] = stat['adr']
        stats_dict[name][side]['steamid'] = stat['steamid']
        stats_dict[name][side]['name'] = name
        stats_dict[name][side]['side'] = side

    # Map steamid to player name for easier lookup
    steamid_to_name = {stat['steamid']: stat['name'] for stat in stats_records}

    # Per-player aggregates via a single groupby on steamid — previously this
    # did one full frame_df scan per player (10 players = 10 full scans of a
    # multi-million-row frame).
    _agg_cols = [
        "rank", "ace_rounds_total", "4k_rounds_total", "3k_rounds_total",
        "utility_damage_total", "enemies_flashed_total", "mvps",
        "headshot_kills_total", "side",
    ]
    available_agg = [c for c in _agg_cols if c in frame_df.columns]

    for steamid, name in steamid_to_name.items():
        # Skip the print() that previously fired for every player on stdout.
        player_frames = frame_df[frame_df['steamid'] == steamid][available_agg]
        if player_frames.empty:
            continue

        rank_series = player_frames["rank"] if "rank" in player_frames.columns else None
        if rank_series is not None and not rank_series.empty:
            try:
                rank_value = int(rank_series.iloc[0])
            except (TypeError, ValueError):
                rank_value = 0
            stats_dict[name]["all"]['rank'] = rank_value
            stats_dict[name]["all"]['rank_type'] = "premier" if rank_value > 18 else "mm"

        def _max_int(col):
            if col in player_frames.columns:
                try:
                    return int(player_frames[col].max())
                except (TypeError, ValueError):
                    return 0
            return 0

        stats_dict[name]["all"]['5k_rounds'] = _max_int('ace_rounds_total')
        stats_dict[name]["all"]['4k_rounds'] = _max_int('4k_rounds_total')
        stats_dict[name]["all"]['3k_rounds'] = _max_int('3k_rounds_total')
        stats_dict[name]["all"]['utility_damage'] = _max_int('utility_damage_total')
        stats_dict[name]["all"]['enemies_flashed'] = _max_int('enemies_flashed_total')
        stats_dict[name]["all"]['mvps'] = _max_int('mvps')
        stats_dict[name]["all"]['headshot_kills'] = _max_int('headshot_kills_total')

        # Initialize clutch win counters
        for k in ('1v1', '1v2', '1v3', '1v4', '1v5'):
            stats_dict[name]["all"][k] = 0

        if 'side' in player_frames.columns:
            for side in player_frames['side'].dropna().unique():
                if side:
                    for k in ('1v1', '1v2', '1v3', '1v4', '1v5'):
                        stats_dict[name][side][k] = 0

    # Clutch detection — group frame_df by round_num once and find the last
    # tick <= round end per round, instead of copying every round's frames.
    clutch_cols = ["tick", "side", "health", "name", "steamid", "round_num"]
    clutch_avail = [c for c in clutch_cols if c in frame_df.columns]
    rounds_iter = rounds.iterrows() if hasattr(rounds, "iterrows") else []

    for _, r_meta in rounds_iter:
        round_num = r_meta['round_num']
        winner_side = r_meta['winner']  # 't' or 'ct'
        round_end_tick = r_meta['end']

        rnd_frames = frame_df[frame_df['round_num'] == round_num][clutch_avail]
        if rnd_frames.empty:
            continue

        valid = rnd_frames[rnd_frames['tick'] <= round_end_tick] if 'tick' in rnd_frames.columns else rnd_frames
        if valid.empty:
            continue

        max_tick = valid['tick'].max()
        last_frame = valid[valid['tick'] == max_tick]

        t_frames = last_frame[last_frame['side'] == 't'] if 'side' in last_frame.columns else last_frame.iloc[0:0]
        ct_frames = last_frame[last_frame['side'] == 'ct'] if 'side' in last_frame.columns else last_frame.iloc[0:0]

        t_alive_count = int((t_frames['health'] > 0).sum()) if 'health' in t_frames.columns else 0
        ct_alive_count = int((ct_frames['health'] > 0).sum()) if 'health' in ct_frames.columns else 0

        if (winner_side == 't' and t_alive_count == 1) or (winner_side == 'ct' and ct_alive_count == 1):
            if winner_side == 't':
                clutch_players = t_frames[t_frames['health'] > 0]
                enemies_alive = ct_alive_count
            else:
                clutch_players = ct_frames[ct_frames['health'] > 0]
                enemies_alive = t_alive_count

            if not clutch_players.empty and 1 <= enemies_alive <= 5:
                clutch_key = f'1v{enemies_alive}'
                for _, clutch_player in clutch_players.iterrows():
                    player_name = clutch_player['name']
                    player_side = clutch_player['side']
                    player_steamid = clutch_player['steamid']

                    if player_name not in stats_dict or player_side not in stats_dict[player_name]:
                        if player_steamid not in steamid_to_name:
                            continue

                    stats_dict[player_name][player_side][clutch_key] += 1
                    stats_dict[player_name]['all'][clutch_key] += 1

    # Convert the nested defaultdict to a list of records format
    records = []
    for name, sides in stats_dict.items():
        for side, s in sides.items():
            record = {}
            for k, v in s.items():
                if isinstance(v, np.integer):
                    record[k] = int(v)
                elif isinstance(v, np.floating):
                    record[k] = float(v)
                elif isinstance(v, np.ndarray):
                    record[k] = v.tolist()
                else:
                    record[k] = v
            records.append(record)

    stats_json = json.dumps(records, indent=4)

    return stats_json

def get_kills_for_player(steamid, kills, round_num=None):
    """
    Get kills for a specific player.
    """
    kills = kills[kills["attacker_steamid"] == steamid]
    
    if round_num is not None:
        kills = kills[kills["round_num"] == round_num]
    
    # Convert to a list of dictionaries
    return kills.to_dict(orient="records")