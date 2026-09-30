import { toValue } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import {
    create as createHouseholdList,
    destroy as destroyHouseholdList,
    edit as editHouseholdList,
    searchProducts as searchHouseholdProducts,
    show as showHouseholdList,
    store as storeHouseholdList,
    update as updateHouseholdList,
} from '@/routes/households/lists';
import {
    destroy as destroyHouseholdListItem,
    image as householdListItemImage,
    store as storeHouseholdListItem,
    update as updateHouseholdListItem,
} from '@/routes/households/lists/items';
import { quickCreate as quickCreateHouseholdProduct } from '@/routes/households/lists/search-products';
import {
    create as createPersonalList,
    destroy as destroyPersonalList,
    edit as editPersonalList,
    index as personalListsIndex,
    searchProducts as searchPersonalProducts,
    show as showPersonalList,
    store as storePersonalList,
    update as updatePersonalList,
} from '@/routes/lists';
import {
    destroy as destroyPersonalListItem,
    image as personalListItemImage,
    store as storePersonalListItem,
    update as updatePersonalListItem,
} from '@/routes/lists/items';
import { quickCreate as quickCreatePersonalProduct } from '@/routes/lists/search-products';

export function useShoppingListRoutes(
    householdId: MaybeRefOrGetter<string | null>,
) {
    function currentHouseholdId(): string | null {
        return toValue(householdId);
    }

    function index(): string {
        return personalListsIndex.url();
    }

    function create(): string {
        const household = currentHouseholdId();

        return household
            ? createHouseholdList.url(household)
            : createPersonalList.url();
    }

    function store(): string {
        const household = currentHouseholdId();

        return household
            ? storeHouseholdList.url(household)
            : storePersonalList.url();
    }

    function show(list: string): string {
        const household = currentHouseholdId();

        return household
            ? showHouseholdList.url({ household, list })
            : showPersonalList.url(list);
    }

    function edit(list: string): string {
        const household = currentHouseholdId();

        return household
            ? editHouseholdList.url({ household, list })
            : editPersonalList.url(list);
    }

    function update(list: string): string {
        const household = currentHouseholdId();

        return household
            ? updateHouseholdList.url({ household, list })
            : updatePersonalList.url(list);
    }

    function destroy(list: string): string {
        const household = currentHouseholdId();

        return household
            ? destroyHouseholdList.url({ household, list })
            : destroyPersonalList.url(list);
    }

    function storeItem(list: string): string {
        const household = currentHouseholdId();

        return household
            ? storeHouseholdListItem.url({ household, list })
            : storePersonalListItem.url(list);
    }

    function updateItem(list: string, item: string): string {
        const household = currentHouseholdId();

        return household
            ? updateHouseholdListItem.url({ household, list, item })
            : updatePersonalListItem.url({ list, item });
    }

    function destroyItem(list: string, item: string): string {
        const household = currentHouseholdId();

        return household
            ? destroyHouseholdListItem.url({ household, list, item })
            : destroyPersonalListItem.url({ list, item });
    }

    function itemImage(list: string, item: string): string {
        const household = currentHouseholdId();

        return household
            ? householdListItemImage.url({ household, list, item })
            : personalListItemImage.url({ list, item });
    }

    function searchProducts(list: string, query: string): string {
        const household = currentHouseholdId();
        const options = { query: { q: query } };

        return household
            ? searchHouseholdProducts.url({ household, list }, options)
            : searchPersonalProducts.url(list, options);
    }

    function quickCreateProduct(list: string): string {
        const household = currentHouseholdId();

        return household
            ? quickCreateHouseholdProduct.url({ household, list })
            : quickCreatePersonalProduct.url(list);
    }

    return {
        create,
        destroy,
        destroyItem,
        edit,
        index,
        itemImage,
        quickCreateProduct,
        searchProducts,
        show,
        store,
        storeItem,
        update,
        updateItem,
    };
}
