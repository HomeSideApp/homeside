export type HouseholdAccessProfile = 'admin' | 'member' | 'buyer' | 'reader';
export type HouseholdAccessLevel = 'none' | 'read' | 'write';
export type HouseholdArea =
    'shopping_lists' | 'recipes' | 'economy' | 'contacts';
export type HouseholdModuleAccess = Record<HouseholdArea, HouseholdAccessLevel>;
export type HouseholdCapabilities = Record<
    HouseholdArea,
    { read: boolean; write: boolean }
>;
export type HouseholdAccessDefaults = Record<
    HouseholdAccessProfile,
    HouseholdModuleAccess
>;
