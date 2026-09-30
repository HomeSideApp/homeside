export interface HouseholdModule {
    module: string;
    label: string;
    description: string;
    icon: string;
    enabled: boolean;
}

export interface HouseholdTag {
    id: string;
    name: string;
    slug: string;
    type: string;
}

export interface HouseholdSummary {
    id: string;
    name: string;
}
