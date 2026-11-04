# Changelog

## 2026-11-04

### Improvements

- Added an in-page charge slip preview with print and PDF options for individual and batch slips in Dispensing.
- Opened dispensing dialogs immediately, with loading feedback for forms that need to fetch or prepare data.

### Errors and fixes

- Kept the Add Item dialog open when stock details finish loading in Dispensing.
- Fixed light and dark appearance resetting when navigating between pages.

- Fixed low-contrast pharmacy table headings in light mode by pairing the header background and text with the active theme.

## 2026-11-03

### Improvements

- Applied the EMR hospital theme throughout pharmacy, portal, dispensing, queue, and account pages, with consistent typography, navigation, forms, tables, and dialogs. Light and dark appearance now follows one remembered preference across pages and browser tabs; printed documents stay light.
- Redesigned pharmacy sign-in using the EMR hospital theme, with responsive layouts, remembered light and dark appearance, password visibility, Caps Lock guidance, and sign-in progress feedback.

## 2026-10-29

### Improvements

- Added pharmacy reports for medicine issuance, returns, stock reconciliation, deliveries, and expiry, with location and fund filters, complete printing, and Excel downloads.
- Stock reconciliation identifies missing historical balances and unexplained differences without changing inventory.
- Reports can optionally summarize equivalent medicines within each fund source, with the same grouping applied to print and Excel downloads.

## 2026-10-21

### Improvements

- Issued UDDDS orders can now be printed by ward and service date, showing patient names, medicines and issued quantities, with each ward starting on a separate page.

### Errors and fixes

- Replacement stock search now runs when leaving the search input instead of while typing.

- UDDDS replacement selection stays hidden until matching stock is found and displays "No record found" for searches without matches.

- Corrected replacement stock labels that caused a malformed UTF-8 error when updating the UDDDS queue.

- UDDDS shortages without an alternate fund now allow selecting replacement medicine stock, with explicit confirmation of original and replacement items, quantities and prices before charging and issuing.

## 2026-10-15

### Errors and fixes

- Satellite pharmacy encounter dispensing now opens when a drug description has no secondary details. Main PDIMS already displays complete descriptions without requiring a separator.

## 2026-10-12

### Improvements

- Delivery reversal opens in a warning modal with a required reason and explicit confirmation before inventory changes.

- Locked deliveries can be reverted when all original batches have sufficient balance, with a required reason and a visible audit trail. Reversal corrects purchase receipts without recording consumption.
