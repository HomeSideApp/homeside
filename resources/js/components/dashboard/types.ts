export interface HouseholdEconomyTotals {
    expenses_minor: number;
    income_minor: number;
    balance_minor: number;
    personal_expenses_minor: number;
    shared_expenses_minor: number;
}

export interface HouseholdMonthlyPoint {
    month: string;
    expenses_minor: number;
    income_minor: number;
}

export interface HouseholdPlacePoint {
    place: string;
    total_minor: number;
}

export interface HouseholdMemberPoint {
    creator_id: string;
    name: string;
    total_minor: number;
}

export interface HouseholdEconomyOverview {
    currency: string | null;
    other_currencies: Record<string, number>;
    current_month: string;
    totals: HouseholdEconomyTotals;
    monthly: HouseholdMonthlyPoint[];
    by_place: HouseholdPlacePoint[];
    by_member: HouseholdMemberPoint[];
}

export interface PersonalEconomyTotals {
    own_minor: number;
    shared_participation_minor: number;
    personal_total_minor: number;
    without_house_minor: number;
}

export interface PersonalMonthlyPoint {
    month: string;
    own_minor: number;
    shared_minor: number;
}

export interface PersonalHouseholdPoint {
    household_id: string | null;
    name: string | null;
    total_minor: number;
}

export interface PersonalEconomyOverview {
    currency: string | null;
    other_currencies: Record<string, number>;
    current_month: string;
    totals: PersonalEconomyTotals;
    monthly: PersonalMonthlyPoint[];
    by_household: PersonalHouseholdPoint[];
}
