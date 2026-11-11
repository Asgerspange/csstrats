/**
 * A human label for a tactic.
 *
 * Stratbook tactics (and custom tactics) carry an explicit `name`. Library
 * tactics don't, so we derive a concise label from the pro team, side, and
 * type. Map is intentionally excluded (it's shown alongside the label in every
 * surface) to avoid redundancy.
 */
export function tacticLabel(t: any): string {
    if (t?.name) return t.name;
    const parts = [t?.team_name, t?.type].filter(Boolean);
    return parts.length ? parts.join(' ') : 'Untitled tactic';
}
