# Shipping & Collection Architecture (ERP)

## Current state analysis

### What works (keep)
| Component | Role |
|-----------|------|
| `orders.order_status` | Operational lifecycle (Arabic labels) — users know it |
| `shipping_companies` + `type` (مندوب/شركة) | Delivery + COD courier in one table |
| `shipping_company_details` + `shipping_company_procedure` | Operational balance per party |
| `ShippingReceivableSplitService` | Split COD vs prepaid between ship/collect parties |
| `SalesOrderAccountingService` | GL recognition at order create (`ORD-*`) |
| `DeliveryConfirmationAccountingService` | Liability transfer at deliver (`DELIVERY-*`) |
| `collect_order` + vouchers | Cash collection + settlement via existing flows |

### Architectural problems (fix incrementally)
1. **Collection “companies” live in `shipping_companies`** — conflates delivery with payment collection.
2. **Single `order_status`** mixes delivery, collection, and business state.
3. **No formal settlement batches** — only ad-hoc collection and `settlement-summary` report.
4. **No audit trail for liability transfer** — GL has `DELIVERY-*` but no operational log.
5. **`paid/unpaid` implicit** — only `prepaid_amount` + `net_total`; partial collection on one order not first-class.
6. **UI mixes logistics and money** — ship screen handles collection company without clear sections.

## Target model (additive, backward compatible)

### Separation of concerns
```
Delivery responsibility  → shipping_provider (shipping_companies.id)
Collection responsibility → collection_provider (polymorphic)
Financial snapshot       → total / paid / remaining on order_details
Settlement batches       → settlements + settlement_items
Liability audit          → order_liability_transfers
```

### Polymorphic `collection_provider_type`
- `shipping_company` — legacy: same table as courier (courier collects)
- `courier` — alias of shipping_company where type=مندوب
- `collection_company` — new `collection_companies` table
- `employee` — `users.id` (future field collection)
- `none` — prepaid / no collection

### Legacy bridge
- `order_details.collection_company_id` → kept; synced from provider when type is `shipping_company`
- `collection_companies.linked_shipping_company_id` → optional link for `shipping_company_procedure` until unified receivable ledger

### Status layers
| Layer | Field | Notes |
|-------|-------|-------|
| Legacy | `orders.order_status` | Unchanged for existing screens |
| Delivery | `order_details.delivery_status` | pending → shipped → delivered |
| Collection | `order_details.collection_status` | not_required → pending → partial → collected |
| Settlement | `order_details.settlement_status` | open → partial → settled |

`OrderFulfillmentSyncService` maps `order_status` ↔ new fields for old data.

## Payment scenarios

| Scenario | collection_provider | collection_status | remaining |
|----------|---------------------|-------------------|-----------|
| Prepaid online | `none` | `not_required` | 0 |
| COD external agency | `collection_company` | `pending` → `collected` | net - paid |
| Courier collects | `shipping_company` / `courier` | `pending` | net - paid |
| Liability transferred | `liability_holder_*` set | `transferred` | per transfer |

## Settlement workflow
1. Collector has open `shipping_company_details` (or future receivable lines).
2. Finance creates `Settlement` (draft) for provider + date range.
3. System attaches `settlement_items` from open lines (idempotent per line).
4. On `post`: records settled amounts, updates `settlement_status`, optional voucher link later.

## Permissions (new slugs)
- `collection_companies.manage`
- `settlements.manage`
- `orders.fulfillment.view`
- `orders.liability.transfer`

## Edge cases
- Duplicate settlement item for same receivable line → blocked by unique index
- Transfer liability twice → blocked if already transferred
- Ship without collection provider when COD > 0 → validation warning
- `collection_company` without `linked_shipping_company_id` → operational line stored on order only until link configured

## Migration phases
1. **Phase 1 (this delivery):** tables, APIs, sync service, UI panel, legacy bridge
2. **Phase 2:** dual-write `order_receivable_lines`, deprecate procedure-only path
3. **Phase 3:** unify settlement with vouchers; retire `collection_company_id` on shipping_companies usage
