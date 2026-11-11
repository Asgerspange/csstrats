import useImages from '@/composables/useImages';

/**
 * Weapon categorisation + loadout helpers shared by the tactic dialogs
 * (library and stratbook). Keep the category lists here only — duplicating
 * them per-component drifts out of sync.
 */
const PRIMARY_TYPES = [
    'AK-47', 'M4A4', 'M4A1-S', 'Galil-AR', 'FAMAS', 'AUG', 'SG-553',
    'AWP', 'SSG-08', 'SCAR-20', 'G3SG1',
    'MAC-10', 'MP9', 'MP7', 'MP5-SD', 'UMP-45', 'P90', 'PP-Bizon',
];

const SECONDARY_TYPES = [
    'Glock-18', 'USP-S', 'P2000', 'Desert-Eagle', 'Dual-Berettas',
    'Five-SeveN', 'P250', 'Tec-9', 'CZ75-Auto', 'R8-Revolver',
    'Nova', 'XM1014', 'MAG-7', 'Sawed-Off', 'M249', 'Negev',
];

const UTILITY_TYPES = [
    'C4', 'Flashbang', 'HE-Grenade', 'Smoke-Grenade', 'Molotov',
    'Incendiary-Grenade', 'Decoy-Grenade', 'Zeus-x27', 'Defuser',
    'item_kevlar', 'item_assault',
];

export function useWeaponLoadout() {
    const { Images } = useImages();
    const { data: weapons } = Images.get('weapon');

    function findWeapon(name: string): string {
        const weapon = weapons.value?.find((w: any) => w.name.toLowerCase() === name.toLowerCase());
        return weapon ? weapon.url : '';
    }

    function getGroupedLoadout(loadoutArray: any) {
        const list = Array.isArray(loadoutArray) ? loadoutArray : [];
        return {
            primaries: list.filter((w: string) => PRIMARY_TYPES.includes(w)),
            secondaries: list.filter((w: string) => SECONDARY_TYPES.includes(w)),
            utilities: list.filter((w: string) => UTILITY_TYPES.includes(w)),
        };
    }

    return { weapons, findWeapon, getGroupedLoadout };
}
