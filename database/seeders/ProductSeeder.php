<?php

namespace Database\Seeders;

use App\Actions\Translations\PublishTranslation;
use App\Data\Translations\PublishTranslationData;
use App\Enums\TranslationFieldStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the product catalog with English (en-US) as the source language and
 * Spanish (es-ES) as a published translation for every category and product.
 *
 * Slugs are preserved from the original Spanish catalog because they are
 * stable identifiers and are never translated — this keeps existing records
 * intact and avoids duplicates on re-seed.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create a user to assign products to
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $publishTranslation = app(PublishTranslation::class);

        /**
         * Each product carries the legacy Spanish slug (stable identifier),
         * the English source name and the Spanish localized name.
         *
         * @var list<array{name: string, slug: string, icon: string, color: string, sort_order: int, es: string, products: list<array{name: string, slug: string, icon: string, es: string}>}>
         */
        $categories = [
            [
                'name' => 'Fruits & vegetables',
                'slug' => 'frutas-vegetales',
                'icon' => 'spinach.webp',
                'color' => '#26A69A',
                'sort_order' => 1,
                'es' => 'Frutas & vegetales',
                'products' => [
                    ['name' => 'Avocado', 'slug' => 'aguacate', 'icon' => 'avocado.webp', 'es' => 'Aguacate'],
                    ['name' => 'Garlic', 'slug' => 'ajo', 'icon' => 'garlic.webp', 'es' => 'Ajo'],
                    ['name' => 'Basil', 'slug' => 'albahaca', 'icon' => 'basil.webp', 'es' => 'Albahaca'],
                    ['name' => 'Apricot', 'slug' => 'albaricoque', 'icon' => 'apricot.webp', 'es' => 'Albaricoque'],
                    ['name' => 'Celery', 'slug' => 'apio', 'icon' => 'celery.webp', 'es' => 'Apio'],
                    ['name' => 'Blueberries', 'slug' => 'arandanos', 'icon' => 'blueberry.webp', 'es' => 'Arándanos'],
                    ['name' => 'Broccoli', 'slug' => 'brocoli', 'icon' => 'spinach.webp', 'es' => 'Brócoli'],
                    ['name' => 'Onion', 'slug' => 'cebolla', 'icon' => 'onion.webp', 'es' => 'Cebolla'],
                    ['name' => 'Cherry', 'slug' => 'cereza', 'icon' => 'cherry.webp', 'es' => 'Cereza'],
                    ['name' => 'Mushroom', 'slug' => 'champinon', 'icon' => 'mushroom.webp', 'es' => 'Champiñón'],
                    ['name' => 'Chili pepper', 'slug' => 'chile', 'icon' => 'chili-pepper.webp', 'es' => 'Chile'],
                    ['name' => 'Spinach', 'slug' => 'espinaca', 'icon' => 'spinach.webp', 'es' => 'Espinaca'],
                    ['name' => 'Strawberry', 'slug' => 'fresa', 'icon' => 'strawberry.webp', 'es' => 'Fresa'],
                    ['name' => 'Peas', 'slug' => 'guisantes', 'icon' => 'peas.webp', 'es' => 'Guisantes'],
                    ['name' => 'Ginger', 'slug' => 'jengibre', 'icon' => 'ginger.webp', 'es' => 'Jengibre'],
                    ['name' => 'Apple', 'slug' => 'manzana', 'icon' => 'apple.webp', 'es' => 'Manzana'],
                    ['name' => 'Orange', 'slug' => 'naranja', 'icon' => 'orange.webp', 'es' => 'Naranja'],
                    ['name' => 'Potato', 'slug' => 'papa', 'icon' => 'potato.webp', 'es' => 'Papa'],
                    ['name' => 'Cucumber', 'slug' => 'pepino', 'icon' => 'cucumber.webp', 'es' => 'Pepino'],
                    ['name' => 'Bell pepper', 'slug' => 'pimiento', 'icon' => 'paprika.webp', 'es' => 'Pimiento'],
                    ['name' => 'Radish', 'slug' => 'rabano', 'icon' => 'radish.webp', 'es' => 'Rábano'],
                    ['name' => 'Tomato', 'slug' => 'tomate', 'icon' => 'tomato.webp', 'es' => 'Tomate'],
                    ['name' => 'Carrot', 'slug' => 'zanahoria', 'icon' => 'carrot.webp', 'es' => 'Zanahoria'],
                    ['name' => 'Sweet potato', 'slug' => 'boniato', 'icon' => 'sweet-potato.webp', 'es' => 'Boniato'],
                ],
            ],
            [
                'name' => 'Dairy & eggs',
                'slug' => 'lacteos-huevos',
                'icon' => 'milk-carton.webp',
                'color' => '#42A5F5',
                'sort_order' => 2,
                'es' => 'Lácteos & huevos',
                'products' => [
                    ['name' => 'Eggs', 'slug' => 'huevos', 'icon' => 'eggs.webp', 'es' => 'Huevos'],
                    ['name' => 'Milk', 'slug' => 'leche', 'icon' => 'milk-carton.webp', 'es' => 'Leche'],
                    ['name' => 'Almond milk', 'slug' => 'leche-de-almendras', 'icon' => 'almond.webp', 'es' => 'Leche de almendras'],
                    ['name' => 'Oat milk', 'slug' => 'leche-de-avena', 'icon' => 'oat-milk.webp', 'es' => 'Leche de avena'],
                    ['name' => 'Mozzarella', 'slug' => 'mozzarella', 'icon' => 'mozzarella.webp', 'es' => 'Mozzarella'],
                    ['name' => 'Yogurt', 'slug' => 'yogur', 'icon' => 'yogurt.webp', 'es' => 'Yogur'],
                ],
            ],
            [
                'name' => 'Meat & fish',
                'slug' => 'carnes-pescados',
                'icon' => 'meat.webp',
                'color' => '#EF5350',
                'sort_order' => 3,
                'es' => 'Carnes & pescados',
                'products' => [
                    ['name' => 'Chorizo', 'slug' => 'chorizo', 'icon' => 'sausages.webp', 'es' => 'Chorizo'],
                    ['name' => 'Prawns', 'slug' => 'gambas', 'icon' => 'prawn.webp', 'es' => 'Gambas'],
                    ['name' => 'Ham', 'slug' => 'jamon', 'icon' => 'salami.webp', 'es' => 'Jamón'],
                    ['name' => 'Shellfish', 'slug' => 'marisco', 'icon' => 'shellfish.webp', 'es' => 'Marisco'],
                    ['name' => 'Fish', 'slug' => 'pescado', 'icon' => 'fish-food.webp', 'es' => 'Pescado'],
                    ['name' => 'Chicken', 'slug' => 'pollo', 'icon' => 'meat.webp', 'es' => 'Pollo'],
                    ['name' => 'Salami', 'slug' => 'salami', 'icon' => 'salami.webp', 'es' => 'Salami'],
                    ['name' => 'Tofu', 'slug' => 'tofu', 'icon' => 'tofu.webp', 'es' => 'Tofu'],
                ],
            ],
            [
                'name' => 'Bakery & cereals',
                'slug' => 'panaderia-cereales',
                'icon' => 'bread.webp',
                'color' => '#FFA726',
                'sort_order' => 4,
                'es' => 'Panadería & cereales',
                'products' => [
                    ['name' => 'Rice', 'slug' => 'arroz', 'icon' => 'grains-of-rice.webp', 'es' => 'Arroz'],
                    ['name' => 'Oats', 'slug' => 'avena', 'icon' => 'rolled-oats.webp', 'es' => 'Avena'],
                    ['name' => 'Biscuits', 'slug' => 'biscuits', 'icon' => 'biscuits.webp', 'es' => 'Bizcochos'],
                    ['name' => 'Croissant', 'slug' => 'croissant', 'icon' => 'croissant.webp', 'es' => 'Croissant'],
                    ['name' => 'Cookies', 'slug' => 'galletas', 'icon' => 'cookies.webp', 'es' => 'Galletas'],
                    ['name' => 'Macaroni', 'slug' => 'macarrones', 'icon' => 'pasta.webp', 'es' => 'Macarrones'],
                    ['name' => 'Bread', 'slug' => 'pan', 'icon' => 'bread.webp', 'es' => 'Pan'],
                    ['name' => 'Sandwich bread', 'slug' => 'pan-de-molde', 'icon' => 'bread-loaf.webp', 'es' => 'Pan de molde'],
                    ['name' => 'Pasta', 'slug' => 'pasta', 'icon' => 'noodles.webp', 'es' => 'Pasta'],
                    ['name' => 'Penne', 'slug' => 'penne', 'icon' => 'penne.webp', 'es' => 'Penne'],
                    ['name' => 'Pizza', 'slug' => 'pizza', 'icon' => 'pizza-cutter.webp', 'es' => 'Pizza'],
                ],
            ],
            [
                'name' => 'Drinks',
                'slug' => 'bebidas',
                'icon' => 'wine-bottle.webp',
                'color' => '#AB47BC',
                'sort_order' => 5,
                'es' => 'Bebidas',
                'products' => [
                    ['name' => 'Water', 'slug' => 'agua', 'icon' => 'water.webp', 'es' => 'Agua'],
                    ['name' => 'Coffee', 'slug' => 'cafe', 'icon' => 'coffee-beans.webp', 'es' => 'Café'],
                    ['name' => 'Champagne', 'slug' => 'champan', 'icon' => 'champagne-bottle.webp', 'es' => 'Champán'],
                    ['name' => 'Coca cola', 'slug' => 'coca-cola', 'icon' => 'cola.webp', 'es' => 'Coca cola'],
                    ['name' => 'Wine', 'slug' => 'vino', 'icon' => 'wine-bottle.webp', 'es' => 'Vino'],
                    ['name' => 'Energy drink', 'slug' => 'bebida-energetica', 'icon' => 'energy-drink.webp', 'es' => 'Bebida energética'],
                ],
            ],
            [
                'name' => 'Snacks & sweets',
                'slug' => 'snacks-dulces',
                'icon' => 'chocolate-bar.webp',
                'color' => '#FF7043',
                'sort_order' => 6,
                'es' => 'Snacks & dulces',
                'products' => [
                    ['name' => 'Chocolate', 'slug' => 'chocolate', 'icon' => 'chocolate-bar.webp', 'es' => 'Chocolate'],
                    ['name' => 'Chocolate spread', 'slug' => 'chocolate-untable', 'icon' => 'chocolate-spread.webp', 'es' => 'Chocolate untable'],
                    ['name' => 'Nuts', 'slug' => 'frutos-secos', 'icon' => 'nut.webp', 'es' => 'Frutos secos'],
                    ['name' => 'Cashews', 'slug' => 'maranones', 'icon' => 'pecan.webp', 'es' => 'Marañones'],
                    ['name' => 'Walnuts', 'slug' => 'nueces', 'icon' => 'brazil-nut.webp', 'es' => 'Nueces'],
                    ['name' => 'Potato chips', 'slug' => 'patatas-fritas', 'icon' => 'potato-chips.webp', 'es' => 'Patatas fritas'],
                    ['name' => 'Nachos', 'slug' => 'nachos', 'icon' => 'nachos.webp', 'es' => 'Nachos'],
                    ['name' => 'Honey', 'slug' => 'miel', 'icon' => 'honey.webp', 'es' => 'Miel'],
                    ['name' => 'Jam', 'slug' => 'mermelada', 'icon' => 'jam.webp', 'es' => 'Mermelada'],
                    ['name' => 'Sugar', 'slug' => 'azucar', 'icon' => 'sugar.webp', 'es' => 'Azúcar'],
                ],
            ],
            [
                'name' => 'Frozen',
                'slug' => 'congelados',
                'icon' => 'french-fries.webp',
                'color' => '#78909C',
                'sort_order' => 7,
                'es' => 'Congelados',
                'products' => [
                    ['name' => 'Frozen french fries', 'slug' => 'patatas-fritas-congeladas', 'icon' => 'french-fries.webp', 'es' => 'Patatas fritas congeladas'],
                    ['name' => 'Frozen sushi', 'slug' => 'sushi-congelado', 'icon' => 'sushi.webp', 'es' => 'Sushi congelado'],
                ],
            ],
            [
                'name' => 'Cleaning',
                'slug' => 'limpieza',
                'icon' => 'cleaning-a-surface.webp',
                'color' => '#26C6DA',
                'sort_order' => 8,
                'es' => 'Limpieza',
                'products' => [
                    ['name' => 'Sponges', 'slug' => 'esponjas', 'icon' => 'sponge.webp', 'es' => 'Esponjas'],
                    ['name' => 'Gloves', 'slug' => 'guantes', 'icon' => 'rubber-gloves.webp', 'es' => 'Guantes'],
                    ['name' => 'Dish soap', 'slug' => 'lavavajillas', 'icon' => 'washing-dishes.webp', 'es' => 'Lavavajillas'],
                    ['name' => 'Eco-friendly cleaner', 'slug' => 'limpiador-ecologico', 'icon' => 'eco-friendly-cleaning.webp', 'es' => 'Limpiador ecológico'],
                    ['name' => 'All-purpose cleaner', 'slug' => 'limpiador-multiusos', 'icon' => 'cleaning-a-surface.webp', 'es' => 'Limpiador multiusos'],
                    ['name' => 'Mops', 'slug' => 'fregonas', 'icon' => 'broom.webp', 'es' => 'Fregonas'],
                ],
            ],
            [
                'name' => 'Hygiene & personal care',
                'slug' => 'higiene',
                'icon' => 'shampoo.webp',
                'color' => '#EC407A',
                'sort_order' => 9,
                'es' => 'Higiene & cuidado personal',
                'products' => [
                    ['name' => 'Shampoo', 'slug' => 'champu', 'icon' => 'shampoo.webp', 'es' => 'Champú'],
                    ['name' => 'Deodorant', 'slug' => 'desodorante', 'icon' => 'deodorant-stick.webp', 'es' => 'Desodorante'],
                    ['name' => 'Shower gel', 'slug' => 'gel-de-ducha', 'icon' => 'soap-dispenser.webp', 'es' => 'Gel de ducha'],
                    ['name' => 'Soap', 'slug' => 'jabon', 'icon' => 'soap-bubble.webp', 'es' => 'Jabón'],
                    ['name' => 'Razor', 'slug' => 'maquinilla', 'icon' => 'shaving.webp', 'es' => 'Maquinilla'],
                    ['name' => 'Tampons', 'slug' => 'tampones', 'icon' => 'tampon.webp', 'es' => 'Tampones'],
                    ['name' => 'Perfume', 'slug' => 'perfume', 'icon' => 'perfume-bottle.webp', 'es' => 'Perfume'],
                    ['name' => 'Dental care kit', 'slug' => 'kit-de-limpieza-dental', 'icon' => 'tooth-cleaning-kit.webp', 'es' => 'Kit de limpieza dental'],
                ],
            ],
            [
                'name' => 'Canned & preserved',
                'slug' => 'conservas',
                'icon' => 'tin-can.webp',
                'color' => '#8D6E63',
                'sort_order' => 10,
                'es' => 'Conservas & enlatados',
                'products' => [
                    ['name' => 'Canned peas', 'slug' => 'guisantes-en-conserva', 'icon' => 'peas.webp', 'es' => 'Guisantes en conserva'],
                    ['name' => 'Cans', 'slug' => 'latas', 'icon' => 'tin-can.webp', 'es' => 'Latas'],
                ],
            ],
            [
                'name' => 'Spices & condiments',
                'slug' => 'especias',
                'icon' => 'powder.webp',
                'color' => '#FFCA28',
                'sort_order' => 11,
                'es' => 'Especias & condimentos',
                'products' => [
                    ['name' => 'Mustard', 'slug' => 'mostaza', 'icon' => 'mustard.webp', 'es' => 'Mostaza'],
                    ['name' => 'Vinegar', 'slug' => 'vinagre', 'icon' => 'rice-vinegar.webp', 'es' => 'Vinagre'],
                    ['name' => 'Worcestershire sauce', 'slug' => 'salsa-worcester', 'icon' => 'worcestershire-sauce.webp', 'es' => 'Salsa worcester'],
                    ['name' => 'Rice vinegar', 'slug' => 'vinagre-de-arroz', 'icon' => 'rice-vinegar.webp', 'es' => 'Vinagre de arroz'],
                ],
            ],
            [
                'name' => 'Household & other',
                'slug' => 'hogar',
                'icon' => 'matches.webp',
                'color' => '#BDBDBD',
                'sort_order' => 12,
                'es' => 'Hogar & otros',
                'products' => [
                    ['name' => 'Matches', 'slug' => 'fosforos', 'icon' => 'matches.webp', 'es' => 'Fósforos'],
                    ['name' => 'Batteries', 'slug' => 'pilas', 'icon' => 'flat-alkaline-battery.webp', 'es' => 'Pilas'],
                    ['name' => 'Adhesive tape', 'slug' => 'cinta-adhesiva', 'icon' => 'tape.webp', 'es' => 'Cinta adhesiva'],
                ],
            ],
        ];

        foreach ($categories as $categoryData) {
            /** @var list<array{name: string, slug: string, icon: string, es: string}> $products */
            $products = $categoryData['products'];
            $categorySpanishName = $categoryData['es'];
            unset($categoryData['products'], $categoryData['es']);

            $category = Category::firstOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );

            // Migrate the source column to English when the category already
            // existed with the legacy Spanish source name.
            if ($category->name !== $categoryData['name']) {
                $category->update(['name' => $categoryData['name']]);
            }

            $this->seedTranslation($publishTranslation, $category, $categorySpanishName);

            foreach ($products as $productData) {
                $product = Product::firstOrCreate(
                    ['slug' => $productData['slug']],
                    [
                        'name' => $productData['name'],
                        'category_id' => $category->id,
                        'icon' => $productData['icon'],
                        'created_by' => $user->id,
                    ]
                );

                if ($product->name !== $productData['name']) {
                    $product->update(['name' => $productData['name']]);
                }

                $this->seedTranslation($publishTranslation, $product, $productData['es']);
            }
        }
    }

    /**
     * Seed the published es-ES translation for a catalog entity, keeping the
     * Spanish name from the legacy catalog as the localized value.
     */
    private function seedTranslation(PublishTranslation $publishTranslation, Category|Product $entity, string $spanishName): void
    {
        $existing = $entity->translations()
            ->where('locale', 'es-ES')
            ->where('field', 'name')
            ->first();

        if ($existing === null) {
            $entity->putTranslation('name', 'es-ES', $spanishName, TranslationFieldStatus::Approved);
        }

        $publishTranslation->execute(new PublishTranslationData(
            translatableType: $entity->translatableType(),
            translatableId: $entity->id,
            locale: 'es-ES',
            publish: true,
        ));
    }
}
