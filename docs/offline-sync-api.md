# Offline sync API contract

The contract a device uses to stay usable without a network: a delta feed to pull from, an
idempotent write surface to push into, client-owned identities so an offline row exists before the
server has ever heard of it, and version checks so a stale edit cannot silently overwrite newer data.

Everything here is additive. No web route, webhook or existing mobile client behaviour was changed
to make room for it.

## Status

| Item                                       | State                                                                       |
| ------------------------------------------ | --------------------------------------------------------------------------- |
| Delta feed `GET /v1/sync/changes`          | implemented                                                                 |
| Tombstones (`deleted_at`)                  | implemented for lists, list items, economy transactions                     |
| Mandatory idempotency on the write surface | implemented                                                                 |
| Client-provided ids                        | implemented on the six create routes                                        |
| Stale-write detection                      | implemented on the item and transaction write routes                        |
| `POST /v1/sync/push` (batch)               | **not implemented**; a client can drain its outbox with individual requests |

Tombstones for `households` and `recipes` are intentionally absent: neither is in the offline write
set.

## Authentication

Sanctum bearer token, same as every other `/v1` route. Tokens live 30 days
(`SANCTUM_TOKEN_EXPIRATION_MINUTES=43200`).

A device that has been offline longer than that must keep **reading from its local mirror** and stop
writing until it can re-authenticate. The API makes no exception: every write below requires a valid
token.

The delta route needs a permission of its own (`sync.changes`), because it spans lists, economy and
households and no single existing capability covers all three. It is granted to the plain `user`
role.

## Delta pull

```
GET /api/v1/sync/changes?since=<cursor>&limit=500
```

| Parameter | Rules                                                                                                                      |
| --------- | -------------------------------------------------------------------------------------------------------------------------- |
| `since`   | Opaque cursor from a previous response. Absent means "give me the initial state". A malformed value is `422`, never `500`. |
| `limit`   | `1..500`, default `500`.                                                                                                   |

```json
{
    "data": {
        "cursor": "eyJ0IjoiMjAyNi0xMS0xNFQwNzoyMDowMFoiLCJpIjoiMDFhMS4uLiJ9",
        "has_more": true,
        "changes": {
            "households": [],
            "lists": [],
            "list_items": [],
            "economy_transactions": []
        },
        "deleted": {
            "lists": [],
            "list_items": [],
            "economy_transactions": []
        }
    }
}
```

- One page, globally ordered by `(updated_at, id)` ascending across all four streams. Not per-entity
  blocks: a client that pages through the feed sees a single timeline.
- `changes` holds live rows as the same resources the normal endpoints return.
- `deleted` holds **ids only**, for rows whose `deleted_at` is set. A deleted row never appears in
  `changes`.
- `has_more` is true when anything remains past the emitted page.
- `cursor` is **opaque**. Its internal shape is not part of the contract and may change. A client
  only stores it and sends it back. When a page is empty the incoming cursor is echoed unchanged, so
  an idle poll does not lose its position.
- Replaying the same `since` returns the same page, which makes a retry after a lost response safe.

Scope is exactly what the caller can already read: their personal lists, the lists of households
they belong to, the items of those lists, their personal economy transactions plus the household
ones they may see, and their visible households.

### Paging recipe

```
cursor = null
loop:
    page = GET /v1/sync/changes?since=cursor
    apply page.changes to the mirror
    mark page.deleted ids as deleted locally
    cursor = page.cursor
until not page.has_more
```

Store `cursor` with the mirror. Losing it costs a full re-sync, not correctness.

### Why `updated_at` moves on delete

A cursor ordered by `updated_at` can only report a deletion if the tombstone advances that column.
A soft delete that left `updated_at` stale would make the deletion invisible to every client whose
cursor had already passed it, and the row would live forever on their device. Both the model delete
and the cascade that tombstones a list's items therefore set `deleted_at` **and** advance
`updated_at`.

`updated_at` has second precision. Rows written in the same second are ordered by id, which is
lexicographic UUIDv7, so ordering is stable and nothing is skipped or repeated.

## Idempotency

Every write in the offline scope requires a `Idempotency-Key` header: a UUID generated on the device
per logical operation and persisted in the outbox, not per retry.

| Result                   | Meaning                                                             |
| ------------------------ | ------------------------------------------------------------------- |
| First request            | Processed normally. The response carries `Idempotency-TTL`.         |
| Same key, same body      | The stored response is replayed verbatim. No second row is created. |
| Same key, different body | `409` with code `idempotency_key_conflict`.                         |
| Missing or blank key     | `422` with code `idempotency_key_required`.                         |

TTL is 24 hours. A key is scoped to the caller and the route, so two clients cannot collide.

Covered routes: creating and updating personal and household lists, their items, and the economy
transactions of both scopes, including `PATCH`. The two economy attachment routes are not covered.

## Client-provided ids

The six create routes accept an optional `id` (UUID):

```
POST /v1/lists
POST /v1/households/{household}/lists
POST /v1/lists/{list}/items
POST /v1/households/{household}/lists/{list}/items
POST /v1/economy/me/transactions
POST /v1/households/{household}/economy/transactions
```

- Present and new: the row is created with exactly that id, so the device can render it immediately
  and never has to rewrite local references after sync.
- Present and taken by another row, without a matching idempotency replay: `422` with code
  `id_taken`. It is never a silent overwrite and never a duplicate.
- Absent: the server assigns a UUIDv7, as before.

Combined with idempotency this closes the outbox's worst case: an operation retried after the TTL
window still cannot duplicate, because its identity is client-owned.

## Stale writes

A device that edited offline may push a version the row has already left.

Send the version you edited from, either as `If-Unmodified-Since` (HTTP date) or as `updated_at` in
the body or query. When the server's row is newer the answer is:

```json
{"message": "...", "code": "stale_write", "errors": {}, "data": { ...current resource... }}
```

`data` is the endpoint's own resource, with the same relations the read endpoint loads, so the
client can merge or discard without a second request. A malformed version is `422`.

Omit the version and the request behaves exactly as it did before, which keeps existing clients
working.

Enforced on the item and transaction write routes: `PUT`/`DELETE` for items in both scopes, and
`PUT`/`PATCH`/`DELETE` for transactions in both scopes. It runs **after** idempotency, so a replay
short-circuits before any comparison.

## Field notes

- `list_items.checked_at` exists so a shared toggle can be resolved by the most recent check rather
  than by whichever device synced last. It is stamped when an item becomes checked, cleared when it
  is unchecked, never moved by an unrelated edit, and preserved on a re-check. Exposed additively in
  the item resource.
- `deleted_at` is exposed nowhere except through the delta's `deleted` buckets. Normal reads keep
  hiding tombstoned rows through the model's global scope.

## Client behaviour the API cannot enforce

- Reads must never block on the network.
- A write that fails validation is not retried automatically; it is marked and surfaced.
- The local mirror is pruned by fetch time, and old tombstones are dropped eventually, or SQLite
  grows without bound.
- Two people checking the same item offline is normal. Compare `checked_at`; if the contract's field
  is absent from a row, fall back to `updated_at`.

## Known limits

- Transaction children (participants, taxes, attachments) are no longer removed by the foreign-key
  cascade when the parent is tombstoned: the parent still has a row. Nothing is exposed, because
  reads resolve through the parent, but a future purge job must handle them.
- A soft-deleted occurrence still occupies `unique(recurrence_parent_id, occurred_at)`, so any
  future occurrence generator must reconcile tombstones first.
- Tombstones are never purged today. There is no retention job yet.
