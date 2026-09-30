<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed application permissions and attach them to the system roles.
     *
     * @return void This seeder method does not return a value.
     */
    public function run(): void
    {
        // Permission Groups
        $dashboardGroup = PermissionGroup::firstOrCreate(['name' => 'Dashboard'], ['description' => 'Permisos del dashboard']);
        $listasGroup = PermissionGroup::firstOrCreate(['name' => 'Listas'], ['description' => 'Permisos de listas de compra']);
        $productosGroup = PermissionGroup::firstOrCreate(['name' => 'Productos'], ['description' => 'Permisos de productos']);
        $recetasGroup = PermissionGroup::firstOrCreate(['name' => 'Recetas'], ['description' => 'Permisos de recetas']);
        $hogaresGroup = PermissionGroup::firstOrCreate(['name' => 'Hogares'], ['description' => 'Permisos de gestión de hogares']);
        $economiaGroup = PermissionGroup::firstOrCreate(['name' => 'Economía'], ['description' => 'Permisos del módulo económico']);
        $contactsGroup = PermissionGroup::firstOrCreate(['name' => 'Contactos'], ['description' => 'Permisos de contactos y fuentes']);
        $adminGroup = PermissionGroup::firstOrCreate(['name' => 'Admin'], ['description' => 'Permisos de administración']);

        foreach (['contacts.view', 'contacts.create', 'contacts.update', 'contacts.delete', 'contacts.home.view', 'contacts.home.create', 'contacts.home.update', 'contacts.home.delete', 'contacts.sources.view', 'contacts.sources.create', 'contacts.sources.update', 'contacts.sources.delete', 'contacts.sources.sync'] as $contactPermission) {
            Permission::firstOrCreate(['route_name' => $contactPermission], [
                'name' => $contactPermission,
                'description' => 'Permiso de contactos: '.$contactPermission,
                'permission_group_id' => $contactsGroup->id,
            ]);
        }

        // Dashboard
        Permission::firstOrCreate(['route_name' => 'dashboard'], ['name' => 'view dashboard', 'description' => 'Ver dashboard', 'permission_group_id' => $dashboardGroup->id]);

        // Listas (anidadas bajo households/{household}/)
        Permission::firstOrCreate(['route_name' => 'households.lists.index'], ['name' => 'view lists', 'description' => 'Ver listas', 'permission_group_id' => $listasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.lists.show'], ['name' => 'view list', 'description' => 'Ver una lista', 'permission_group_id' => $listasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.lists.create'], ['name' => 'create lists', 'description' => 'Formulario crear listas', 'permission_group_id' => $listasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.lists.store'], ['name' => 'store lists', 'description' => 'Guardar listas', 'permission_group_id' => $listasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.lists.edit'], ['name' => 'edit lists', 'description' => 'Formulario editar listas', 'permission_group_id' => $listasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.lists.update'], ['name' => 'update lists', 'description' => 'Actualizar listas', 'permission_group_id' => $listasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.lists.destroy'], ['name' => 'delete lists', 'description' => 'Eliminar listas', 'permission_group_id' => $listasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.lists.share'], ['name' => 'share lists', 'description' => 'Compartir listas', 'permission_group_id' => $listasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.lists.items.store'], ['name' => 'store list items', 'description' => 'Añadir elementos', 'permission_group_id' => $listasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.lists.items.update'], ['name' => 'update list items', 'description' => 'Actualizar elementos', 'permission_group_id' => $listasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.lists.items.destroy'], ['name' => 'delete list items', 'description' => 'Eliminar elementos', 'permission_group_id' => $listasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.lists.search-products'], ['name' => 'search products in list', 'description' => 'Buscar productos en una lista', 'permission_group_id' => $listasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.lists.search-products.quick-create'], ['name' => 'quick create and add product', 'description' => 'Creación rápida y añadir producto a lista', 'permission_group_id' => $listasGroup->id]);

        // Productos (anidados bajo households/{household}/)
        Permission::firstOrCreate(['route_name' => 'households.products.store'], ['name' => 'store products', 'description' => 'Crear productos', 'permission_group_id' => $productosGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.products.quick-create'], ['name' => 'quick create products', 'description' => 'Creación rápida de productos', 'permission_group_id' => $productosGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.products.pending'], ['name' => 'view pending products', 'description' => 'Ver productos pendientes', 'permission_group_id' => $productosGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.products.generate-images'], ['name' => 'generate product images', 'description' => 'Generar imágenes de productos', 'permission_group_id' => $productosGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.images.approve'], ['name' => 'approve images', 'description' => 'Aprobar imágenes', 'permission_group_id' => $productosGroup->id]);

        // Recetas — Cookbook personal
        Permission::firstOrCreate(['route_name' => 'recipes.index'], ['name' => 'view my recipes', 'description' => 'Ver mis recetas', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'recipes.create'], ['name' => 'create recipe form', 'description' => 'Formulario crear receta', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'recipes.store'], ['name' => 'store recipes', 'description' => 'Guardar recetas', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'recipes.show'], ['name' => 'view recipe', 'description' => 'Ver una receta', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'recipes.edit'], ['name' => 'edit recipe form', 'description' => 'Formulario editar receta', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'recipes.update'], ['name' => 'update recipes', 'description' => 'Actualizar recetas', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'recipes.destroy'], ['name' => 'delete recipes', 'description' => 'Eliminar recetas', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'recipes.fork'], ['name' => 'fork recipes', 'description' => 'Forkear recetas', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'recipes.import'], ['name' => 'import recipes', 'description' => 'Importar recetas', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'recipes.import.json-ld'], ['name' => 'import json-ld', 'description' => 'Importar receta JSON-LD', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'recipes.import.cooklang'], ['name' => 'import cooklang', 'description' => 'Importar receta Cooklang', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'recipes.import.review'], ['name' => 'review import', 'description' => 'Revisar importación', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'recipes.export'], ['name' => 'export recipes', 'description' => 'Exportar recetas', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'recipes.export.cooklang'], ['name' => 'export cooklang', 'description' => 'Exportar receta Cooklang', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'recipes.cook'], ['name' => 'cook recipe', 'description' => 'Modo cocción', 'permission_group_id' => $recetasGroup->id]);

        // Recetas — Household sharing
        Permission::firstOrCreate(['route_name' => 'households.recipes.index'], ['name' => 'view household recipes', 'description' => 'Ver recetas del hogar', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.recipes.share'], ['name' => 'share recipes', 'description' => 'Compartir recetas con el hogar', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.recipes.unshare'], ['name' => 'unshare recipes', 'description' => 'Dejar de compartir recetas', 'permission_group_id' => $recetasGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.recipes.add-to-list'], ['name' => 'add recipe to list', 'description' => 'Añadir ingredientes de receta a lista', 'permission_group_id' => $recetasGroup->id]);

        // Admin: Documentación API
        Permission::firstOrCreate(['route_name' => 'scramble.docs.ui'], ['name' => 'view api documentation', 'description' => 'Ver documentación de la API', 'permission_group_id' => $adminGroup->id]);

        // Admin: Usuarios
        Permission::firstOrCreate(['route_name' => 'admin.users'], ['name' => 'view users', 'description' => 'Ver usuarios', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.users.create'], ['name' => 'create user form', 'description' => 'Formulario crear usuario', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.users.store'], ['name' => 'store users', 'description' => 'Guardar usuarios', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.users.edit'], ['name' => 'edit users', 'description' => 'Formulario editar usuario', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.users.update'], ['name' => 'update users', 'description' => 'Actualizar usuarios', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.users.destroy'], ['name' => 'delete users', 'description' => 'Eliminar usuarios', 'permission_group_id' => $adminGroup->id]);

        // Admin: Roles
        Permission::firstOrCreate(['route_name' => 'admin.roles'], ['name' => 'view roles', 'description' => 'Ver roles', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.roles.create'], ['name' => 'create role form', 'description' => 'Formulario crear rol', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.roles.store'], ['name' => 'store roles', 'description' => 'Guardar roles', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.roles.show'], ['name' => 'show role', 'description' => 'Ver rol', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.roles.edit'], ['name' => 'edit roles', 'description' => 'Formulario editar rol', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.roles.update'], ['name' => 'update roles', 'description' => 'Actualizar roles', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.roles.destroy'], ['name' => 'delete roles', 'description' => 'Eliminar roles', 'permission_group_id' => $adminGroup->id]);

        // Admin: Traducciones
        Permission::firstOrCreate(['route_name' => 'admin.translations'], ['name' => 'view translations', 'description' => 'Ver panel de traducciones', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.translations.store'], ['name' => 'store translations', 'description' => 'Crear traducciones', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.translations.update'], ['name' => 'update translations', 'description' => 'Actualizar traducciones', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.translations.destroy'], ['name' => 'delete translations', 'description' => 'Eliminar traducciones', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.translations.publish'], ['name' => 'publish translations', 'description' => 'Publicar y despublicar traducciones', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.translations.generate'], ['name' => 'generate translations with AI', 'description' => 'Generar traducciones con IA', 'permission_group_id' => $adminGroup->id]);

        // Admin: Categorías
        Permission::firstOrCreate(['route_name' => 'admin.categories'], ['name' => 'view categories', 'description' => 'Ver categorías', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.categories.create'], ['name' => 'create category form', 'description' => 'Formulario crear categoría', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.categories.store'], ['name' => 'create categories', 'description' => 'Crear categorías', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.categories.edit'], ['name' => 'edit categories', 'description' => 'Formulario editar categoría', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.categories.update'], ['name' => 'update categories', 'description' => 'Actualizar categorías', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.categories.destroy'], ['name' => 'delete categories', 'description' => 'Eliminar categorías', 'permission_group_id' => $adminGroup->id]);

        // Economía — casa (anidada bajo households/{household}/economy)
        // Las rutas web tienen nombre completo households.economy.* por el prefijo del grupo.
        Permission::firstOrCreate(['route_name' => 'households.economy.index'], ['name' => 'view economy', 'description' => 'Ver transacciones', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.economy.create'], ['name' => 'create economy form', 'description' => 'Formulario crear transacción', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.economy.store'], ['name' => 'store economy', 'description' => 'Guardar transacción', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.economy.show'], ['name' => 'view economy transaction', 'description' => 'Ver transacción', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.economy.edit'], ['name' => 'edit economy', 'description' => 'Editar transacción', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.economy.update'], ['name' => 'update economy', 'description' => 'Actualizar transacción', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.economy.destroy'], ['name' => 'delete economy', 'description' => 'Eliminar transacción', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.economy.imports.create'], [
            'name' => __('app.permissions.household_economy_import_create.name'),
            'description' => __('app.permissions.household_economy_import_create.description'),
            'permission_group_id' => $economiaGroup->id,
        ]);

        // Economía — cuenta privada del usuario (rutas economy/me/*, fuera del gate household+module)
        Permission::firstOrCreate(['route_name' => 'economy.me.index'], ['name' => 'view personal economy', 'description' => 'Ver resumen de cuenta privada', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'economy.me.create'], ['name' => 'create personal economy form', 'description' => 'Formulario crear transacción privada', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'economy.me.store'], ['name' => 'store personal economy', 'description' => 'Guardar transacción privada', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'economy.me.show'], ['name' => 'view personal economy transaction', 'description' => 'Ver transacción privada', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'economy.me.edit'], ['name' => 'edit personal economy', 'description' => 'Editar transacción privada', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'economy.me.update'], ['name' => 'update personal economy', 'description' => 'Actualizar transacción privada', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'economy.me.destroy'], ['name' => 'delete personal economy', 'description' => 'Eliminar transacción privada', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'economy.me.imports.create'], [
            'name' => __('app.permissions.personal_economy_import_create.name'),
            'description' => __('app.permissions.personal_economy_import_create.description'),
            'permission_group_id' => $economiaGroup->id,
        ]);
        Permission::firstOrCreate(['route_name' => 'economy.me.imports.index'], ['name' => 'list personal economy imports', 'description' => 'Listar importaciones privadas', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.economy.imports.index'], ['name' => 'list household economy imports', 'description' => 'Listar importaciones del hogar', 'permission_group_id' => $economiaGroup->id]);

        Permission::firstOrCreate(['route_name' => 'economy.me.imports.reprocess'], ['name' => 'reprocess personal economy imports', 'description' => 'Reencuadrar y reanalizar importaciones privadas', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.economy.imports.reprocess'], ['name' => 'reprocess household economy imports', 'description' => 'Reencuadrar y reanalizar importaciones del hogar', 'permission_group_id' => $economiaGroup->id]);

        // Economía — adjuntos de transacciones (web)
        foreach (['store', 'destroy', 'file'] as $attachmentAction) {
            Permission::firstOrCreate(['route_name' => "economy.me.attachments.{$attachmentAction}"], ['name' => "personal economy attachments {$attachmentAction}", 'description' => "Adjuntos de transacciones privadas ({$attachmentAction})", 'permission_group_id' => $economiaGroup->id]);
            Permission::firstOrCreate(['route_name' => "households.economy.attachments.{$attachmentAction}"], ['name' => "household economy attachments {$attachmentAction}", 'description' => "Adjuntos de transacciones de casa ({$attachmentAction})", 'permission_group_id' => $economiaGroup->id]);
        }

        // Economía — cuentas y métodos de pago del usuario
        foreach (['index', 'create', 'store', 'show', 'edit', 'update', 'archive', 'destroy'] as $action) {
            Permission::firstOrCreate(['route_name' => "economy.me.accounts.{$action}"], [
                'name' => "personal economy accounts {$action}",
                'description' => "Cuentas privadas del usuario ({$action})",
                'permission_group_id' => $economiaGroup->id,
            ]);
        }

        foreach (['index', 'store', 'update', 'destroy'] as $action) {
            Permission::firstOrCreate(['route_name' => "economy.me.payment-methods.{$action}"], [
                'name' => "personal payment methods {$action}",
                'description' => "Métodos de pago del usuario ({$action})",
                'permission_group_id' => $economiaGroup->id,
            ]);
        }

        // Admin: catálogo global de métodos de pago
        foreach (['index', 'store', 'update', 'destroy'] as $action) {
            Permission::firstOrCreate(['route_name' => "admin.payment-methods.{$action}"], [
                'name' => "admin payment methods {$action}",
                'description' => "Métodos de pago globales ({$action})",
                'permission_group_id' => $adminGroup->id,
            ]);
        }

        // Admin: Traducciones — route_names de rutas API que api.permission resuelve
        foreach (['index', 'store', 'update', 'destroy', 'publish', 'generate'] as $action) {
            Permission::firstOrCreate(['route_name' => "api.v1.admin.translations.{$action}"], ['name' => "translations api {$action}", 'description' => "API: traducciones ({$action})", 'permission_group_id' => $adminGroup->id]);
        }

        // Economía — route_names de rutas API que api.permission resuelve
        // (prefijo api.v1. → households.economy.* y economy.me.*)
        foreach (['index', 'store', 'show', 'update', 'destroy'] as $action) {
            Permission::firstOrCreate(['route_name' => "households.economy.transactions.{$action}"], ['name' => "economy api transactions {$action}", 'description' => "API: transacciones de casa ({$action})", 'permission_group_id' => $economiaGroup->id]);
            Permission::firstOrCreate(['route_name' => "households.economy.documents.{$action}"], ['name' => "economy api documents {$action}", 'description' => "API: documentos de casa ({$action})", 'permission_group_id' => $economiaGroup->id]);
            Permission::firstOrCreate(['route_name' => "households.economy.imports.{$action}"], ['name' => "economy api imports {$action}", 'description' => "API: importaciones de casa ({$action})", 'permission_group_id' => $economiaGroup->id]);
        }
        Permission::firstOrCreate(['route_name' => 'households.economy.imports.retry'], ['name' => 'economy api imports retry', 'description' => 'API: reintentar importación de casa', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.economy.imports.confirm'], ['name' => 'economy api imports confirm', 'description' => 'API: confirmar importación de casa', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.economy.imports.discard'], ['name' => 'economy api imports discard', 'description' => 'API: descartar importación de casa', 'permission_group_id' => $economiaGroup->id]);

        foreach (['index', 'store', 'show', 'update', 'destroy'] as $action) {
            Permission::firstOrCreate(['route_name' => "economy.me.transactions.{$action}"], ['name' => "personal economy api transactions {$action}", 'description' => "API: transacciones privadas ({$action})", 'permission_group_id' => $economiaGroup->id]);
            Permission::firstOrCreate(['route_name' => "economy.me.documents.{$action}"], ['name' => "personal economy api documents {$action}", 'description' => "API: documentos privados ({$action})", 'permission_group_id' => $economiaGroup->id]);
            Permission::firstOrCreate(['route_name' => "economy.me.imports.{$action}"], ['name' => "personal economy api imports {$action}", 'description' => "API: importaciones privadas ({$action})", 'permission_group_id' => $economiaGroup->id]);
            Permission::firstOrCreate(['route_name' => "economy.me.accounts.{$action}"], ['name' => "personal economy api accounts {$action}", 'description' => "API: cuentas privadas ({$action})", 'permission_group_id' => $economiaGroup->id]);
            Permission::firstOrCreate(['route_name' => "economy.me.payment-methods.{$action}"], ['name' => "personal payment methods api {$action}", 'description' => "API: métodos de pago del usuario ({$action})", 'permission_group_id' => $economiaGroup->id]);
        }
        Permission::firstOrCreate(['route_name' => 'economy.me.totals'], ['name' => 'personal economy api totals', 'description' => 'API: total personal', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'economy.me.imports.retry'], ['name' => 'personal economy api imports retry', 'description' => 'API: reintentar importación privada', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'economy.me.imports.confirm'], ['name' => 'personal economy api imports confirm', 'description' => 'API: confirmar importación privada', 'permission_group_id' => $economiaGroup->id]);
        Permission::firstOrCreate(['route_name' => 'economy.me.imports.discard'], ['name' => 'personal economy api imports discard', 'description' => 'API: descartar importación privada', 'permission_group_id' => $economiaGroup->id]);

        // Hogares
        Permission::firstOrCreate(['route_name' => 'households.index'], ['name' => 'view households', 'description' => 'Ver hogares', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.create'], ['name' => 'create household form', 'description' => 'Formulario crear hogar', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.store'], ['name' => 'store households', 'description' => 'Guardar hogar', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.show'], ['name' => 'view household', 'description' => 'Ver un hogar', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.edit'], ['name' => 'edit households', 'description' => 'Editar hogar', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.update'], ['name' => 'update households', 'description' => 'Actualizar hogar', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.destroy'], ['name' => 'delete households', 'description' => 'Eliminar hogar', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.invite'], ['name' => 'invite members', 'description' => 'Invitar miembros', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.remove'], ['name' => 'remove members', 'description' => 'Eliminar miembros', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.invite-links.store'], ['name' => 'create household invite links', 'description' => 'Crear enlaces de invitación del hogar', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.invite-links.destroy'], ['name' => 'revoke household invite links', 'description' => 'Revocar enlaces de invitación del hogar', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.switch'], ['name' => 'switch household', 'description' => 'Cambiar hogar activo', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.ai-providers.index'], ['name' => 'view ai providers', 'description' => 'Ver proveedores IA del hogar', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.ai-providers.store'], ['name' => 'create ai providers', 'description' => 'Crear proveedores IA del hogar', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.ai-providers.update'], ['name' => 'update ai providers', 'description' => 'Actualizar proveedores IA del hogar', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.ai-providers.destroy'], ['name' => 'delete ai providers', 'description' => 'Eliminar proveedores IA del hogar', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.ai-providers.test'], ['name' => 'test ai providers', 'description' => 'Probar conexión de proveedores IA', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.ai-providers.test-config'], ['name' => 'test config ai providers', 'description' => 'Probar configuración de proveedores IA sin guardar', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.settings.edit'], ['name' => 'edit household settings', 'description' => 'Editar configuración del hogar', 'permission_group_id' => $hogaresGroup->id]);
        Permission::firstOrCreate(['route_name' => 'households.settings.update'], ['name' => 'update household settings', 'description' => 'Actualizar configuración del hogar', 'permission_group_id' => $hogaresGroup->id]);

        // Admin: Productos
        Permission::firstOrCreate(['route_name' => 'admin.products'], ['name' => 'view admin products', 'description' => 'Ver productos admin', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.products.create'], ['name' => 'create product form', 'description' => 'Formulario crear producto', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.products.store'], ['name' => 'create admin products', 'description' => 'Crear productos admin', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.products.edit'], ['name' => 'edit products', 'description' => 'Formulario editar producto', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.products.update'], ['name' => 'update products', 'description' => 'Actualizar productos', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.products.destroy'], ['name' => 'delete products', 'description' => 'Eliminar productos', 'permission_group_id' => $adminGroup->id]);

        // Admin: IA Global
        Permission::firstOrCreate(['route_name' => 'admin.ai-providers'], ['name' => 'view global ai providers', 'description' => 'Ver proveedores IA globales', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.ai-providers.store'], ['name' => 'create global ai providers', 'description' => 'Crear proveedores IA globales', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.ai-providers.update'], ['name' => 'update global ai providers', 'description' => 'Actualizar proveedores IA globales', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.ai-providers.destroy'], ['name' => 'delete global ai providers', 'description' => 'Eliminar proveedores IA globales', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.ai-providers.test'], ['name' => 'test global ai providers', 'description' => 'Probar conexión de proveedores IA globales', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.ai-providers.default'], ['name' => 'default global ai providers', 'description' => 'Marcar proveedor IA global como predeterminado', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.ai-providers.test-config'], ['name' => 'test config global ai providers', 'description' => 'Probar configuración de proveedores IA globales sin guardar', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.ai-settings.global-prompt'], ['name' => 'update global ai prompt', 'description' => 'Actualizar prompt global IA', 'permission_group_id' => $adminGroup->id]);
        Permission::firstOrCreate(['route_name' => 'admin.ai-settings.module-prompt'], ['name' => 'update module ai prompt', 'description' => 'Actualizar prompt por módulo IA', 'permission_group_id' => $adminGroup->id]);

        // Job Monitoring (gestión de workers) — los endpoints API reutilizan estos
        // mismos permisos vía los alias de ApiPermissionsMiddleware.
        $jobMonitoringGroup = PermissionGroup::firstOrCreate(['name' => 'Job Monitoring'], ['description' => 'Permisos del monitor de jobs y workers']);
        Permission::firstOrCreate(['route_name' => 'jobs-monitor.index'], ['name' => 'view job monitoring dashboard', 'description' => 'Ver dashboard de job monitoring', 'permission_group_id' => $jobMonitoringGroup->id]);
        Permission::firstOrCreate(['route_name' => 'jobs-monitor.jobs'], ['name' => 'view job runs', 'description' => 'Ver listado de ejecuciones de jobs', 'permission_group_id' => $jobMonitoringGroup->id]);
        Permission::firstOrCreate(['route_name' => 'jobs-monitor.show'], ['name' => 'view job run', 'description' => 'Ver detalle de una ejecución de job', 'permission_group_id' => $jobMonitoringGroup->id]);
        Permission::firstOrCreate(['route_name' => 'jobs-monitor.retry'], ['name' => 'retry failed jobs', 'description' => 'Reintentar jobs fallidos', 'permission_group_id' => $jobMonitoringGroup->id]);

        // Roles
        $admin = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'is_system' => true]);
        $admin->givePermissionTo(Permission::all());

        $user = Role::firstOrCreate(['slug' => 'user'], ['name' => 'User', 'is_system' => true]);
        $user->givePermissionTo(Permission::whereIn('route_name', [
            'dashboard',
            'households.lists.index', 'households.lists.show', 'households.lists.create', 'households.lists.store', 'households.lists.edit', 'households.lists.update', 'households.lists.destroy', 'households.lists.share',
            'households.lists.items.store', 'households.lists.items.update', 'households.lists.items.destroy',
            'households.lists.search-products', 'households.lists.search-products.quick-create',
            'households.products.store', 'households.products.quick-create',
            'recipes.index', 'recipes.create', 'recipes.store', 'recipes.show', 'recipes.edit', 'recipes.update', 'recipes.destroy',
            'recipes.import', 'recipes.import.json-ld', 'recipes.import.cooklang', 'recipes.import.review',
            'recipes.export.cooklang', 'recipes.fork', 'recipes.cook',
            'households.recipes.index', 'households.recipes.share', 'households.recipes.unshare', 'households.recipes.add-to-list',
            'households.economy.index', 'households.economy.create', 'households.economy.store', 'households.economy.show',
            'households.economy.edit', 'households.economy.update', 'households.economy.destroy',
            'households.economy.imports.create',
            'economy.me.index', 'economy.me.create', 'economy.me.store', 'economy.me.show',
            'economy.me.edit', 'economy.me.update', 'economy.me.destroy',
            'economy.me.imports.create',
            'households.economy.transactions.index', 'households.economy.transactions.store', 'households.economy.transactions.show', 'households.economy.transactions.update', 'households.economy.transactions.destroy',
            'households.economy.documents.index', 'households.economy.documents.store', 'households.economy.documents.show', 'households.economy.documents.update', 'households.economy.documents.destroy',
            'households.economy.imports.index', 'households.economy.imports.store', 'households.economy.imports.show', 'households.economy.imports.update', 'households.economy.imports.destroy', 'households.economy.imports.retry', 'households.economy.imports.confirm', 'households.economy.imports.discard',
            'economy.me.transactions.index', 'economy.me.transactions.store', 'economy.me.transactions.show', 'economy.me.transactions.update', 'economy.me.transactions.destroy',
            'economy.me.documents.index', 'economy.me.documents.store', 'economy.me.documents.show', 'economy.me.documents.update', 'economy.me.documents.destroy',
            'economy.me.imports.index', 'economy.me.imports.store', 'economy.me.imports.show', 'economy.me.imports.update', 'economy.me.imports.destroy', 'economy.me.imports.retry', 'economy.me.imports.confirm', 'economy.me.imports.discard',
            'economy.me.totals',
            'households.index', 'households.create', 'households.store',
            'households.show', 'households.switch',
        ])->get());

        $readonly = Role::firstOrCreate(['slug' => 'readonly'], ['name' => 'Readonly', 'is_system' => true]);
        $readonly->givePermissionTo(Permission::whereIn('route_name', [
            'dashboard', 'households.lists.index', 'households.lists.show', 'recipes.index', 'recipes.show',
            'households.economy.index', 'households.economy.show', 'economy.me.index', 'economy.me.show',
            'households.economy.transactions.index', 'households.economy.transactions.show',
            'economy.me.transactions.index', 'economy.me.transactions.show', 'economy.me.totals',
        ])->get());

        $listsReader = Role::firstOrCreate(['slug' => 'lists-reader'], ['name' => 'Lectura de listas', 'is_system' => true]);
        $listsReader->givePermissionTo(Permission::whereIn('route_name', [
            'dashboard',
            'households.lists.index', 'households.lists.show',
            'households.recipes.index', 'households.recipes.show',
        ])->get());

        $listsEditor = Role::firstOrCreate(['slug' => 'lists-editor'], ['name' => 'Lectura y edición de listas', 'is_system' => true]);
        $listsEditor->givePermissionTo(Permission::whereIn('route_name', [
            'dashboard',
            'households.lists.index', 'households.lists.show', 'households.lists.create', 'households.lists.store', 'households.lists.edit', 'households.lists.update', 'households.lists.destroy', 'households.lists.share',
            'households.lists.items.store', 'households.lists.items.update', 'households.lists.items.destroy',
            'households.lists.search-products', 'households.lists.search-products.quick-create',
            'recipes.index', 'recipes.show', 'households.recipes.index',
        ])->get());

        // User - Invitation: puede usar la app pero NO puede crear hogares
        $userInvitation = Role::firstOrCreate(
            ['slug' => 'user-invitation'],
            ['name' => 'User - Invitation', 'is_system' => true]
        );
        $userInvitation->givePermissionTo(Permission::whereIn('route_name', [
            'dashboard',
            'households.lists.index', 'households.lists.show', 'households.lists.create', 'households.lists.store', 'households.lists.edit', 'households.lists.update', 'households.lists.destroy', 'households.lists.share',
            'households.lists.items.store', 'households.lists.items.update', 'households.lists.items.destroy',
            'households.lists.search-products', 'households.lists.search-products.quick-create',
            'households.products.store', 'households.products.quick-create',
            'recipes.index', 'recipes.create', 'recipes.store', 'recipes.show', 'recipes.edit', 'recipes.update', 'recipes.destroy',
            'recipes.import', 'recipes.import.json-ld', 'recipes.import.cooklang', 'recipes.import.review',
            'recipes.export', 'recipes.export.cooklang', 'recipes.fork', 'recipes.cook',
            'households.recipes.index', 'households.recipes.share', 'households.recipes.unshare', 'households.recipes.add-to-list',
            'households.economy.index', 'households.economy.create', 'households.economy.store', 'households.economy.show',
            'households.economy.edit', 'households.economy.update', 'households.economy.destroy',
            'households.economy.imports.create',
            'economy.me.index', 'economy.me.create', 'economy.me.store', 'economy.me.show',
            'economy.me.edit', 'economy.me.update', 'economy.me.destroy',
            'economy.me.imports.create',
            'households.economy.transactions.index', 'households.economy.transactions.store', 'households.economy.transactions.show', 'households.economy.transactions.update', 'households.economy.transactions.destroy',
            'households.economy.documents.index', 'households.economy.documents.store', 'households.economy.documents.show', 'households.economy.documents.update', 'households.economy.documents.destroy',
            'households.economy.imports.index', 'households.economy.imports.store', 'households.economy.imports.show', 'households.economy.imports.update', 'households.economy.imports.destroy', 'households.economy.imports.retry', 'households.economy.imports.confirm', 'households.economy.imports.discard',
            'economy.me.transactions.index', 'economy.me.transactions.store', 'economy.me.transactions.show', 'economy.me.transactions.update', 'economy.me.transactions.destroy',
            'economy.me.documents.index', 'economy.me.documents.store', 'economy.me.documents.show', 'economy.me.documents.update', 'economy.me.documents.destroy',
            'economy.me.imports.index', 'economy.me.imports.store', 'economy.me.imports.show', 'economy.me.imports.update', 'economy.me.imports.destroy', 'economy.me.imports.retry', 'economy.me.imports.confirm', 'economy.me.imports.discard',
            'economy.me.totals',
            'households.index', 'households.show', 'households.switch',
            // Sin households.create ni households.store
        ])->get());

        // Economía — cuentas y métodos de pago (web y API) para los roles con economía privada
        $accountPermissions = Permission::whereIn('route_name', [
            'economy.me.accounts.index', 'economy.me.accounts.create', 'economy.me.accounts.store',
            'economy.me.accounts.show', 'economy.me.accounts.edit', 'economy.me.accounts.update',
            'economy.me.accounts.archive', 'economy.me.accounts.destroy',
            'economy.me.payment-methods.index', 'economy.me.payment-methods.store',
            'economy.me.payment-methods.update', 'economy.me.payment-methods.destroy',
            'economy.me.imports.index', 'households.economy.imports.index',
            'economy.me.imports.reprocess', 'households.economy.imports.reprocess',
            'economy.me.attachments.store', 'economy.me.attachments.destroy',
            'economy.me.attachments.file',
            'households.economy.attachments.store', 'households.economy.attachments.destroy',
            'households.economy.attachments.file',
        ])->get();
        $user->givePermissionTo($accountPermissions);
        $userInvitation->givePermissionTo($accountPermissions);
        $readonly->givePermissionTo($accountPermissions->filter(
            fn (Permission $permission): bool => in_array($permission->route_name, [
                'economy.me.accounts.index', 'economy.me.accounts.show',
                'economy.me.payment-methods.index',
            ], true),
        ));

        $contactPermissions = Permission::where('route_name', 'like', 'contacts.%')->get();
        $admin->givePermissionTo($contactPermissions);
        $user->givePermissionTo($contactPermissions);
        $userInvitation->givePermissionTo($contactPermissions);
        $readonly->givePermissionTo($contactPermissions->filter(fn (Permission $permission): bool => in_array($permission->route_name, ['contacts.view', 'contacts.home.view', 'contacts.sources.view'], true)));

    }
}
