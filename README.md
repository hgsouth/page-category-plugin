# Page Categories (Internal)

A WordPress plugin for **internally** categorizing Pages in the admin. Tag pages with color-coded labels so you can see at a glance what each page is for, then sort and filter the Pages list by those labels.

These categories are purely an admin organization tool: they are **not** taxonomies, they never appear on the front end, and they have no effect on URLs, templates, or site structure. Deleting the plugin removes all of its data.

## Features

- **Color-coded badges** in a new "Category" column on the Pages list (`Pages → All Pages`).
- **Sort** the Pages list by category — click the "Category" column header. Sorting follows the order you define on the management screen, and uncategorized pages stay in the list.
- **Filter** the Pages list with a category dropdown (including an "Uncategorized" option).
- **Management screen** at `Pages → Page Categories`:
  - Add categories with a name and color (a sensible palette is suggested automatically).
  - Rename and recolor inline with a live badge preview.
  - Drag rows to reorder — this order drives the column sort.
  - See how many pages use each category (linked to the filtered list).
  - Delete a category (it's automatically cleared from any pages using it).
- **Assign categories** three ways:
  - A "Page Category (Internal)" box in the page editor sidebar (block and classic editors).
  - Quick Edit on the Pages list.
  - Bulk Edit to categorize many pages at once.

## Installation

1. Copy this plugin folder into `wp-content/plugins/` (or zip it and upload via `Plugins → Add New → Upload Plugin`).
2. Activate **Page Categories (Internal)** on the Plugins screen.
3. Go to `Pages → Page Categories` to create your first categories.

## Requirements

- WordPress 6.3+
- PHP 7.4+

## How it stores data

- Categories (name, color, order) are stored in a single option: `pcp_categories`.
- A page's assignment is stored in hidden post meta: `_pcp_page_category`.
- Managing categories requires the `manage_options` capability (administrators); assigning a category to a page requires permission to edit that page.
- `uninstall.php` deletes the options and all assignment meta when the plugin is deleted.
