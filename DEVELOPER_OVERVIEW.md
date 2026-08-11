# BRP YMM Search Developer Overview

This document is a fast map of the plugin for future maintenance and feature work.

## What This Plugin Does

- Adds Year/Make/Model (YMM) vehicle filtering for WooCommerce products.
- Stores fitment rows in a custom DB table (`{wp_prefix}ymm`).
- Provides frontend selector widgets and shortcode.
- Filters product/category queries by selected YMM values.
- Shows product fitment data on product pages.
- Supports admin CSV import/export and per-product fitment editing.

## Entry Points

- `brp-ymm-search.php`
  - Main plugin bootstrap (`Pektsekye_Ymm`).
  - Registers hooks, widgets, shortcode, admin menu, ajax controllers, scripts.
  - Adds product tab output and search-related filters.
  - Adds custom YMM sitemap integration for Yoast.
- `ymm-search.php`
  - Legacy shim that includes `brp-ymm-search.php`.

## Core Layers

### Configuration

- `etc/config.php`
  - Defines selector levels and URL params.
  - Defines CSV column order:
    - `product_id, product_sku, make, model, year_from, year_to, note`

### Data Access

- `Model/Db.php`
  - Main data layer for all YMM queries.
  - Key methods:
    - `fetchColumnValues()` for selector dropdown options.
    - `getProductIds()` to resolve products matching selected YMM.
    - `saveProductRestrictionText()` for product-level admin save.
    - `getVehicleData()` for CSV export rows.
    - `getProductIdsBySku()` used by CSV import lookup.
    - `addValues()`, `emptyTable()`, category/product helper methods.

### Import/Export

- `Model/Db/CsvImportHandler.php`
  - Validates uploaded CSV and strict header order.
  - Accepts new header and legacy header (without `product_id`) for backward compatibility.
  - Loads product lookup values via `getProductIdsById()` and `getProductIdsBySku()`.
  - Uses `product_id` first; falls back to `product_sku` only when `product_id` is missing.
  - Normalizes year range to 1950..2030.
  - Bulk inserts in batches.

- `Controller/Adminhtml/Ymm/Selector.php`
  - Handles admin actions `importData`, `exportData`, `updateConfig`.
  - Export writes header from config and data from `Model/Db.php`.

### Frontend Flow

- `Block/Selector.php`
  - View-model for selector state, option loading, garage cookie, template rendering.
- `Controller/Selector.php`
  - Ajax endpoints for next dropdown values and category tree.
- `Controller/Product.php`
  - Applies YMM filtering to WooCommerce main queries.
  - Appends selected YMM params to layered-nav links.
  - Adds compatibility text on product page.
- `view/frontend/templates/*.php`
  - Selector UI templates.

### Widgets and Blocks

- `Widget/Selector.php` and `Widget/HorizontalSelector.php`
  - Standard and horizontal selector widgets.
- `Block/Adminhtml/*`
  - Product edit/quick-edit and admin selector views.

### Install/Schema

- `Setup/Install.php`
  - Creates table `{wp_prefix}ymm` with columns:
    - `id, product_id, make, model, year_from, year_to, note`
  - Unique key on (`product_id, make, model, year_from, year_to`).

## Database Model (Practical)

- The YMM table stores product linkage by `product_id` (not SKU).
- CSV export now emits `product_id` as the first column, then `product_sku`.
- CSV import resolves to `product_id` and inserts only DB table columns.

## CSV Import Behavior: SKU vs Product ID

Current behavior is ProductID-first:

- Preferred matching: `product_id` when column/value is present.
- Fallback matching: `product_sku` only when `product_id` is missing.
- Backward compatibility: importer accepts both new and legacy headers.

## Hotspots For Future Work

- If moving fully to ProductID import/export:
  - `etc/config.php` (column name/order),
  - `Model/Db/CsvImportHandler.php` (lookup logic),
  - `Model/Db.php` (`getVehicleData()`, `getProductIdsBySku()` replacement),
  - admin import/export UI messaging in `Controller/Adminhtml/Ymm/Selector.php`,
  - docs (`README.md`, `readme.txt`).

- Query/runtime performance hotspots:
  - `fetchColumnValues`, `getProductIds`, and category tree assembly.
  - Large CSV imports (batch insert logic).

## Quick File Map

- Bootstrap: `brp-ymm-search.php`
- DB layer: `Model/Db.php`
- CSV import: `Model/Db/CsvImportHandler.php`
- Admin import/export controller: `Controller/Adminhtml/Ymm/Selector.php`
- Frontend filter controller: `Controller/Product.php`
- Ajax selector controller: `Controller/Selector.php`
- Selector block/view-model: `Block/Selector.php`
- Install/schema: `Setup/Install.php`