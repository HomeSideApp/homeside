export interface ListCategorySummary {
    id: string;
    name: string;
    color: string;
    icon?: string | null;
}

export interface ListProduct {
    id: string;
    name: string;
    slug: string;
    icon: string | null;
    is_personal?: boolean;
    category?: ListCategorySummary | null;
}

export interface ListCategory extends ListCategorySummary {
    slug: string;
    icon: string | null;
    products: ListProduct[];
}

export interface StoreSummary {
    id: string;
    name: string;
}

export interface ListItem {
    id: string;
    product?: ListProduct | null;
    custom_name: string | null;
    quantity: number;
    unit: string | null;
    is_checked: boolean;
    notes: string | null;
    icon: string | null;
    image_url: string | null;
    category?: ListCategorySummary | null;
    store?: StoreSummary | null;
    added_by_user?: {
        id: string;
        name: string;
    } | null;
}

export interface IconOption {
    name: string;
    url: string;
    type: 'static';
}
