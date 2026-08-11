# YMM Search Plugin

A WordPress/WooCommerce plugin that adds year/make/model search functionality to automotive websites.

## Features

- Year/Make/Model search widget
- Product fitment display
- Category filtering
- CSV import/export for product restrictions
- Admin configuration panel

## Installation

1. Upload the plugin to your WordPress plugins directory
2. Activate the plugin through the WordPress admin panel
3. Configure the plugin settings under the YMM menu
4. Add the YMM Search widget to your sidebar

## Configuration

The plugin can be configured through the WordPress admin panel under the "BRP YMM Search" menu.

## Version History

- 1.0.12.6:
  - YoastSEO modification added
  - Category exclusions field added to admin interface
  - added product list backend page YMM Data column (X) Vehicles
  - added quick-edit box that allows viewing/editing of ymm data
  - added notes field to end of each ymm line
  - On product page (front end), Fitment Tab:
    - Model column will show "All Models" instead of just being blank
    - Year will display "All Years" for ",0,0", "2016 and Above" for ",2016,0", and "2010 and Below" for ",0,2010"
- 1.0.12.3-5 - ^ idk, read all changes in 1.0.12.6
- 1.0.12.2 - Fixed category dropdown memory and added alphabetical sorting
- 1.0.12.1 - Includes improvements for fitment display, category visibility, and configurable exclusions  
- 1.0.12 - Current version with year_first modifications
- Various bug fixes and improvements


## TO DO: TWEAKS / FIXES:
- Make Leading Category view 4 columns instead of 3 which is pushing Model dropdown to a new line (horizontal filter)
  - YEAR Dropdown can be much more narrow to save space
- Garage: hide leading category, only show ymm
- DB CSV Export and Import use ProductID instead of SKU (since SKUs can be duplicated)
- Product List page: Quick Edit section doesn't work with Notes field, and will delete all notes if quickedit is used at all.
- Product List page: YMM Data/(#) Vehicles Column is not sortable
- Product List page: Quick Edit section needs to be larger horizontally for sure, maybe also vertically
- Product List page: Quick Edit, get rid of Format/Example section to save space
- Show More/Less buttons on product fitment tab need improvement
- "2010 and Below" for ",0,2010" stopped working, just displays as "- 2010"


## New Ideas:
- New admin tab > Synonyms
  - KTM, 300 XC-W TPI, 2017, 2023 						= KTM, 300 XC-W, 2017, 2023
  - KTM, 300 XC-W TBI, 2024, 2025 						= KTM, 300 XC-W, 2024, 2025
  - KTM, 300 XC-W TBI HardEnduro, 2024, 2024 	= KTM, 300 XC-W, 2024, 2024
- FIND OUTLIERS:
	  example, if a model is misspelled like KTM XC-FW instead of XCF-W, all the universal products make it difficult to locate the product with the bad model.
- New admin tab > Add Excel/Spreadsheet DB Editor
	  there are a lot of advantages to being able to see the database directly to help locate mistakes
- Find DB lines with no product (deleted skus?)
	  In editing the db export, there are a lot of 'old' lines that don't apply to any valid SKU anymore for one reason or another.


## Development

This repository tracks modifications and improvements to the original YMM Search plugin.
