<?php

namespace Tests\Feature;

use App\Models\ListItem;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsolidatedDatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_consolidated_migrations_create_the_current_application_schema(): void
    {
        $expectedColumns = [
            'users' => [
                'active_household_id',
                'households_enabled',
                'locale',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'shares_personal_products',
            ],
            'households' => ['description', 'image_url', 'color'],
            'household_invitations' => ['status'],
            'ai_providers' => ['user_id', 'driver', 'privacy_level', 'fallback_policy', 'is_default'],
            'products' => ['image_url', 'needs_image', 'is_approved', 'is_personal'],
            'shopping_lists' => ['household_id'],
            'list_items' => ['source_type', 'source_id', 'original_quantity', 'original_unit', 'scaled_servings'],
            'recipes' => [
                'owner_id',
                'derived_from_recipe_id',
                'collection_id',
                'cover_image_path',
                'yield_text',
                'prep_time_seconds',
                'cook_time_seconds',
                'total_time_seconds',
                'difficulty',
                'cuisine',
                'locale',
                'cooking_method',
                'recipe_category',
                'suitable_for_diet',
                'keywords',
                'author',
                'source_url',
                'source_name',
                'source_type',
                'notes',
            ],
            'recipe_ingredients' => [
                'section_id',
                'name',
                'preparation',
                'quantity_text',
                'optional',
                'original_text',
                'order',
            ],
            'module_ai_configurations' => ['provider_model_id', 'instructions', 'additional_instructions'],
            'ai_runs' => ['user_message', 'reply', 'metadata'],
            'economic_transactions' => [
                'recurrence_parent_id',
                'recurrence_frequency',
                'recurrence_interval',
                'recurrence_weekdays',
                'recurrence_ends_at',
                'recurrence_next_at',
            ],
        ];

        foreach ($expectedColumns as $table => $columns) {
            $this->assertTrue(
                Schema::hasColumns($table, $columns),
                "The [{$table}] table is missing one or more consolidated columns.",
            );
        }

        foreach (['list_shares', 'invitations', 'agent_conversations', 'agent_conversation_messages', 'personal_product_usage'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "The obsolete [{$table}] table still exists.");
        }
    }

    public function test_model_relationships_use_columns_that_exist_in_the_schema(): void
    {
        $store = Store::factory()->create();
        $listItem = ListItem::factory()->create(['store_id' => $store->id]);
        $permissionGroup = PermissionGroup::create(['name' => 'Database']);
        $permission = Permission::create([
            'name' => 'Review database',
            'route_name' => 'database.review',
            'description' => 'Review the database schema',
            'permission_group_id' => $permissionGroup->id,
        ]);

        $this->assertSame([$listItem->id], $store->listItems()->pluck('id')->all());
        $this->assertSame($permissionGroup->id, $permission->permissionsGroup->id);
    }
}
