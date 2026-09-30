export interface ImageProcessingPayload {
    crop: { x: number; y: number; width: number; height: number } | null;
    rotate: number;
    brightness: number;
    contrast: number;
    greyscale: boolean;
    sharpen: boolean;
}

export interface EconomyMember {
    id: string;
    user: { id: string; name: string };
    /** Contact of the member resolved from the viewer's address book. */
    contact?: {
        display_name: string;
        avatar_url: string | null;
        email: string | null;
        phone: string | null;
        labels?: Array<{ id: string; name: string }>;
    } | null;
}

export interface EconomyItem {
    id?: string;
    name: string;
    quantity: number;
    unit_amount: string;
    subtotal: string;
    tax_amount: string | null;
    total: string;
}

export interface EconomyTax {
    id?: string;
    name: string;
    rate: string;
    taxable_base: string;
    amount: string;
    /** Alias used by the detail table, mirroring the persisted column name. */
    tax_amount?: string;
}

export interface EconomyParticipant {
    household_member_id: string;
    split_type: 'equal' | 'fixed' | 'percentage';
    amount: string;
    percentage: number | null;
}

export type PaymentMethodKind =
    | 'cash'
    | 'card'
    | 'bank_transfer'
    | 'digital_wallet'
    | 'crypto'
    | 'cheque'
    | 'other';

export interface PaymentMethod {
    id: string;
    name: string;
    slug: string;
    icon: string | null;
    color: string;
    kind: PaymentMethodKind;
    is_global: boolean;
    is_active: boolean;
    sort_order: number;
    accounts_count?: number;
}

export interface CryptoAsset {
    id: string;
    symbol: string;
    name: string;
    decimal_places: number;
}

export interface CryptoPortfolioSlice {
    asset_id: string;
    symbol: string;
    name: string;
    decimal_places: number;
    quantity_minor: number;
    price: number | null;
    value_minor: number | null;
    accounts_count: number;
}

export interface CryptoPortfolio {
    base_currency: string;
    total_value_minor: number;
    updated_at: string | null;
    has_prices: boolean;
    slices: CryptoPortfolioSlice[];
}

export interface EconomicAccount {
    id: string;
    name: string;
    currency: string;
    decimal_places: number;
    initial_balance_minor: number;
    initial_balance_at: string | null;
    balance_minor: number | null;
    icon: string | null;
    color: string;
    last_four_digits: string | null;
    include_in_totals: boolean;
    archived_at: string | null;
    is_archived: boolean;
    crypto_asset_id?: string | null;
    crypto_asset?: CryptoAsset | null;
    payment_methods?: PaymentMethod[];
}

export interface EconomyAttachment {
    id: string;
    original_filename: string;
    mime_type: string;
    size: number;
    sort_order: number;
    is_mine: boolean;
    uploaded_by?: { id: string; name: string } | null;
    created_at: string | null;
}

export interface EconomyTransaction {
    id: string;
    type: 'expense' | 'income';
    scope: 'personal' | 'shared';
    title: string;
    amount: string;
    currency: string;
    account?: EconomicAccount | null;
    attachments?: EconomyAttachment[];
    place: string | null;
    occurred_at: string | null;
    notes: string | null;
    recurrence_parent_id: string | null;
    recurrence_frequency: RecurrenceFrequency | null;
    recurrence_interval: number | null;
    recurrence_weekdays: number[];
    recurrence_ends_at: string | null;
    recurrence_next_at: string | null;
    household_id: string | null;
    origin?: string | null;
    created_by: { id: string; name: string };
    contact?: {
        id: string;
        display_name: string;
        avatar_url: string | null;
    } | null;
    items?: EconomyItem[];
    taxes?: Array<Omit<EconomyTax, 'amount'> & { tax_amount: string }>;
    participants?: Array<{
        id: string;
        split_type: 'equal' | 'fixed' | 'percentage';
        amount: string;
        percentage: number | null;
        household_member: EconomyMember;
    }>;
    source_document?: EconomyDocument | null;
}

export type RecurrenceFrequency = 'daily' | 'weekly' | 'monthly' | 'yearly';

export interface EconomyDocument {
    id: string;
    original_filename: string;
    mime_type: string;
    size: number;
    processing?: ImageProcessingPayload | null;
}
