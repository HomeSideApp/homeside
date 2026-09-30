<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    Bot,
    BookUser,
    ClipboardList,
    ContactRound,
    CookingPot,
    CreditCard,
    FileText,
    FolderTree,
    Landmark,
    KeyRound,
    Languages,
    LayoutGrid,
    MessageSquare,
    Package,
    PiggyBank,
    ShieldCheck,
    Server,
    ShoppingCart,
    Tags,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/components/AppLogo.vue';
import HouseholdSwitcher from '@/components/households/HouseholdSwitcher.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useHousehold } from '@/composables/useHousehold';
import { useShoppingListRoutes } from '@/composables/useShoppingListRoutes';
import { dashboard as dashboardRoute } from '@/routes';
import {
    users as adminUsersIndex,
    roles as adminRolesIndex,
} from '@/routes/admin';
import { translations as adminTranslationsIndex } from '@/routes/admin';
import { index as contactsIndex } from '@/routes/contacts';
import { index as contactSourcesIndex } from '@/routes/contacts/sources';
import { index as personalEconomyIndex } from '@/routes/economy/me';
import { index as personalAccountsIndex } from '@/routes/economy/me/accounts';
import { index as paymentMethodsIndex } from '@/routes/economy/me/payment-methods';
import { index as householdEconomyIndex } from '@/routes/households/economy';
import { index as jobsMonitorIndex } from '@/routes/jobs-monitor';
import { index as recipeCollectionsIndex } from '@/routes/recipes/collections';
import type { NavItem } from '@/types';

const { t } = useI18n();
const { householdUrl, activeHousehold } = useHousehold();
const listRoutes = useShoppingListRoutes(
    computed(() => activeHousehold.value?.id ?? null),
);

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: t('sidebar.dashboard'),
        href: dashboardRoute(),
        icon: LayoutGrid,
    },
    {
        title: t('sidebar.shoppingLists'),
        href: listRoutes.index(),
        icon: ClipboardList,
        permission: 'households.lists.index',
    },
    {
        title: 'Recetas',
        href: '#',
        icon: CookingPot,
        children: [
            {
                title: 'Mi Cookbook',
                href: '/recipes',
                icon: BookUser,
            },
            {
                title: 'Colecciones',
                href: recipeCollectionsIndex(),
                icon: FolderTree,
            },
            {
                title: t('sidebar.householdRecipes'),
                href: householdUrl('/recipes'),
                icon: CookingPot,
                permission: 'households.recipes.index',
                module: 'recipes',
            },
        ],
    },
    {
        title: t('sidebar.contacts'),
        href: '#',
        icon: ContactRound,
        permission: ['contacts.view', 'contacts.sources.view'],
        children: [
            {
                title: t('sidebar.contactBook'),
                href: contactsIndex(),
                icon: BookUser,
                permission: 'contacts.view',
            },
            {
                title: t('sidebar.contactSources'),
                href: contactSourcesIndex(),
                icon: Server,
                permission: 'contacts.sources.view',
            },
        ],
    },
    {
        title: t('sidebar.economy'),
        href: '#',
        icon: Wallet,
        children: [
            {
                title: t('sidebar.myEconomy'),
                href: personalEconomyIndex(),
                icon: PiggyBank,
                permission: ['economy.me.index', 'economy.me.show'],
            },
            {
                title: t('sidebar.myAccounts'),
                href: personalAccountsIndex(),
                icon: Landmark,
                permission: ['economy.me.accounts.index'],
            },
            {
                title: t('sidebar.paymentMethods'),
                href: paymentMethodsIndex(),
                icon: CreditCard,
                permission: ['economy.me.payment-methods.index'],
            },
            ...(activeHousehold.value
                ? [
                      {
                          title: t('sidebar.householdEconomy'),
                          href: householdEconomyIndex(activeHousehold.value.id),
                          icon: Wallet,
                          permission: [
                              'households.economy.index',
                              'households.economy.show',
                          ],
                          module: 'economy',
                      },
                  ]
                : []),
        ],
    },
    ...(activeHousehold.value
        ? [
              {
                  title: t('sidebar.assistant'),
                  href: '/assistant',
                  icon: MessageSquare,
              },
              {
                  title: t('sidebar.aiConfig'),
                  href: householdUrl('/settings/ai-providers'),
                  icon: Bot,
                  permission: 'households.ai-providers.index',
              },
          ]
        : []),
    {
        title: t('sidebar.admin'),
        href: '#',
        icon: ShieldCheck,
        permission: [
            'admin.users',
            'admin.roles',
            'admin.categories',
            'admin.products',
            'admin.ai-providers',
            'admin.translations',
            'jobs-monitor.index',
        ],
        children: [
            {
                title: t('sidebar.translations'),
                href: adminTranslationsIndex(),
                icon: Languages,
                permission: 'admin.translations',
            },
            {
                title: 'Job Monitoring',
                href: jobsMonitorIndex(),
                icon: Activity,
                permission: 'jobs-monitor.index',
            },
            {
                title: t('sidebar.permissions'),
                href: '#',
                icon: KeyRound,
                permission: ['admin.users', 'admin.roles'],
                children: [
                    {
                        title: t('sidebar.users'),
                        href: adminUsersIndex(),
                        icon: Users,
                        permission: 'admin.users',
                    },
                    {
                        title: t('sidebar.roles'),
                        href: adminRolesIndex(),
                        icon: ShieldCheck,
                        permission: 'admin.roles',
                    },
                ],
            },
            {
                title: t('sidebar.products'),
                href: '#',
                icon: Package,
                permission: ['admin.categories', 'admin.products'],
                children: [
                    {
                        title: t('sidebar.categories'),
                        href: '/admin/categories',
                        icon: Tags,
                        permission: 'admin.categories',
                    },
                    {
                        title: t('sidebar.products'),
                        href: '/admin/products',
                        icon: ShoppingCart,
                        permission: 'admin.products',
                    },
                    {
                        title: t('sidebar.personalProducts'),
                        href: '/admin/personal-products',
                        icon: Users,
                        permission: 'admin.products',
                    },
                ],
            },
            {
                title: t('sidebar.globalAi'),
                href: '/admin/ai-providers',
                icon: Bot,
                permission: 'admin.ai-providers',
            },
            {
                title: t('sidebar.aiUsage'),
                href: '/admin/ai-usage',
                icon: FileText,
                permission: 'admin.ai-usage',
            },
        ],
    },
]);

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardRoute()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <HouseholdSwitcher />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
