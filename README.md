# Easy Page Sorting

A WordPress plugin for **internally** categorizing Pages in the admin. Tag pages with color-coded labels so you can see at a glance what each page is for, then sort and filter the Pages list by those labels.

These categories are purely an admin organization tool: they are **not** taxonomies, they never appear on the front end, and they have no effect on URLs, templates, or site structure. Deleting the plugin removes all of its data.

## Features

- **Color-coded badges** in a new "Category" column on the Pages list (`Pages → All Pages`).
- **Sort** the Pages list by category — click the "Category" column header. Sorting follows the order you define on the management screen, and uncategorized pages stay in the list.
- **Filter** the Pages list with a category dropdown (including an "Uncategorized" option).
- **Management screen** at `Pages → Easy Page Sorting`, split into two tabs:
  - **Manage Categories** — add categories with a name and color (a sensible palette is suggested
    automatically), rename and recolor inline with a live badge preview, drag rows to reorder (this
    order drives the column sort), see how many pages use each category, and delete a category
    (it's automatically cleared from any pages using it). Click a category's page count to jump
    straight to those pages on the Sort tab.
  - **Sort Pages** — an audit view listing every page. Click a category chip to filter down to it
    (including an "Uncategorized" chip), search by title, and sort by title or last-modified date.
    Pages are sorted **oldest edit first** by default so stale pages surface at the top.

    Each row shows the page's category (as an editable dropdown), status, author, and how long ago
    it was last modified, plus Edit / View / Trash actions. Retag pages with the per-row dropdowns
    and hit **Save Assignments**, or tick several rows and use **Assign selected to…** to move them
    all at once. Rows with unsaved changes are highlighted, and you're warned before navigating away.
- **Assign categories** three ways:
  - A "Page Category (Internal)" box in the page editor sidebar (block and classic editors).
  - Quick Edit on the Pages list.
  - Bulk Edit to categorize many pages at once.

## Installation

1. Copy this plugin folder into `wp-content/plugins/` (or zip it and upload via `Plugins → Add New → Upload Plugin`).
2. Activate **Easy Page Sorting** on the Plugins screen.
3. Go to `Pages → Easy Page Sorting` to create your first categories.

## Updating

**Do not delete the old version first.** Deleting the plugin runs `uninstall.php`, which wipes every
category and every page assignment. Deactivating is safe; deleting is not.

To update, use either:

- **Upload the new zip** (WordPress 5.5+): `Plugins → Add New → Upload Plugin`, choose the zip, and
  when WordPress says the plugin already exists click **Replace current with uploaded**. It compares
  versions, swaps the files, and leaves your data alone.
- **Overwrite over SFTP/FTP**: replace the contents of `wp-content/plugins/page-categories/` with the
  new files. Delete any files that no longer exist in the new version.

The zip's top-level folder must keep the same name as the installed folder (`page-categories`).
If the folder name changes, WordPress treats it as a separate plugin and you'll end up with two
copies installed.

## Requirements

- WordPress 6.3+
- PHP 7.4+

## How it stores data

- Categories (name, color, order) are stored in a single option: `pcp_categories`.
- A page's assignment is stored in hidden post meta: `_pcp_page_category`.
- Managing categories requires the `manage_options` capability (administrators); assigning a category to a page requires permission to edit that page.
- `uninstall.php` deletes the options and all assignment meta when the plugin is deleted.
