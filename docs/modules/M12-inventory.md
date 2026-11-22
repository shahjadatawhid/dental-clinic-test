# M12 — Inventory & Purchasing

**Phase:** 4 · **Depends on:** M01, M08 · **Tables:** inventory_items, stock_batches, stock_movements, purchases, purchase_items (suppliers via payees)

## Purpose
Know what supplies are on hand, what's expiring, and what to reorder.

## Features
- Items: SKU, name, category, unit, reorder level, track expiry.
- Purchases from suppliers → receive stock into batches (batch no, expiry) → PV for payment.
- Stock movements: receive, use, adjust, expire, return — each with reason and user.
- Optional: record supplies used on a visit.
- Alerts: below reorder level; expiring within N days; expired.
- Stock count / adjustment screen.

## Business rules
- Stock never goes negative (block or require adjust permission).
- On-hand = Σ movements; cached value updated in same transaction.
- Use oldest-expiry batch first (FEFO) by default.

## Screens
Items list · Item detail (batches, movements) · Purchase form · Stock adjust · Alerts

## Acceptance criteria
- Receiving 10 units with expiry next month shows in "expiring soon" when within threshold.
