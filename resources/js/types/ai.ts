export interface AiProviderConfiguration {
    temperature?: number | null;
    timeout?: number;
    max_tokens?: number;
    model_capabilities?: {
        reasoning?: boolean;
        reasoning_effort?: string;
        tool_call_format?: string;
        context_tokens?: number | null;
    };
}

export interface AiProvider {
    id: string;
    name: string;
    type: string;
    driver: string;
    base_url: string;
    model: string;
    module: string;
    modules?: string[];
    default_modules?: string[];
    module_label: string;
    enabled: boolean;
    is_default: boolean;
    configuration: AiProviderConfiguration | null;
    privacy_level: string;
    fallback_policy: string;
    // Catalog-aligned spec columns.
    family: string | null;
    description: string | null;
    attachment: boolean;
    reasoning: boolean;
    reasoning_options: Record<string, unknown> | null;
    tool_call: boolean;
    structured_output: boolean;
    temperature: boolean | null;
    open_weights: boolean;
    modalities_input: string[] | null;
    modalities_output: string[] | null;
    context_window: number | null;
    max_input_tokens: number | null;
    max_output_tokens: number | null;
    cost_input: number | null;
    cost_output: number | null;
    cost_cache_read: number | null;
    cost_cache_write: number | null;
}

/** A provider entry from the models.dev catalog. */
export interface CatalogProvider {
    slug: string;
    name: string;
    logo_url: string | null;
    models_count: number;
}

/** A provider entry nested inside a catalog model (eager-loaded). */
export interface CatalogModelProvider {
    slug: string;
    name: string;
    logo_url: string;
}

/** A model entry from the models.dev catalog. */
export interface CatalogModel {
    id: string;
    model_id: string;
    provider_slug: string;
    name: string;
    provider: CatalogModelProvider | null;
    family: string | null;
    description: string | null;
    attachment: boolean;
    reasoning: boolean;
    reasoning_options: Record<string, unknown> | null;
    tool_call: boolean;
    structured_output: boolean;
    temperature: boolean | null;
    open_weights: boolean;
    modalities_input: string[] | null;
    modalities_output: string[] | null;
    context_window: number | null;
    max_input_tokens: number | null;
    max_output_tokens: number | null;
    cost_input: number | null;
    cost_output: number | null;
    cost_cache_read: number | null;
    cost_cache_write: number | null;
}

/** Prefill data returned by CatalogPrefill when a model is selected. */
export interface CatalogPrefillData {
    name: string | null;
    type: string | null;
    model: string | null;
    base_url: string | null;
    privacy_level: string | null;
    family: string | null;
    description: string | null;
    attachment: boolean;
    reasoning: boolean;
    reasoning_options: Record<string, unknown> | null;
    tool_call: boolean;
    structured_output: boolean;
    temperature: boolean | null;
    open_weights: boolean;
    modalities_input: string[] | null;
    modalities_output: string[] | null;
    context_window: number | null;
    max_input_tokens: number | null;
    max_output_tokens: number | null;
    cost_input: number | null;
    cost_output: number | null;
    cost_cache_read: number | null;
    cost_cache_write: number | null;
}

/** Paginated response from Inertia partial reloads for catalog results. */
export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}
