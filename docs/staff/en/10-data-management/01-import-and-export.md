---
title: "Import and export"
description: "A reference guide to all import and export features across PM One."
section: "data-management"
order: 1
locale: "en"
audience: "staff"
video_url: ""
video_poster: ""
---

<!-- VIDEO -->

Many sections of PM One support importing data from Excel spreadsheets and exporting your data back out. This page collects all import/export capabilities in one place.

## What you will learn

- Which modules support import/export
- How to use templates
- Common troubleshooting tips

## Modules with import support

| Module | Import from | Notes |
|--------|------------|-------|
| Users | Excel (.xlsx) | Creates accounts, sends invitation emails |
| Projects | Excel (.xlsx) | Creates or updates projects |
| Brands | Excel (.xlsx) | Creates or updates global brand records |
| Brand-events | Excel (.xlsx) | Assigns brands to events with booth details |
| Event products | Excel (.xlsx) | Creates products within an event |
| Contacts | Excel (.xlsx) | Creates or updates contacts matched by email |
| Contact business categories | Excel (.xlsx) | Creates categories |
| Short links | Excel (.xlsx) | Creates short links with custom slugs |

## Modules with export support

| Module | Export format | Notes |
|--------|-------------|-------|
| Users | Excel (.xlsx) | All user data |
| Projects | Excel (.xlsx) | Project list |
| Brands | Excel (.xlsx) | Global brand data |
| Brand-events | Excel (.xlsx) | Event-specific brand assignments |
| Event products | Excel (.xlsx) | Product catalog for an event |
| Contacts | Excel (.xlsx) | Full contact list (respects active filters) |
| Contact business categories | Excel (.xlsx) | Category list |
| Short links | Excel (.xlsx) | Links with click counts |
| Orders | Excel (.xlsx) | Order data for an event or globally |
| Contact form submissions | Excel (.xlsx) | Inbox data |
| Posts | Excel (.xlsx) | Post metadata (optionally with images) |
| Form responses | Excel (.xlsx) | Form submission data |
| GA properties | Excel (.xlsx) | Analytics configuration |

## How to use templates

For imports, always download the template first:

1. Go to the module's list page
2. Click **Import**
3. Click **Download template**
4. Fill in your data in the downloaded file, keeping the column headers intact
5. Upload the completed file

Templates show you the exact columns the importer expects. Adding extra columns or changing header names will cause errors.

## Troubleshooting

**Import reports errors with row numbers** - Open the original file, check the flagged rows for missing required fields or invalid data, fix them, and re-upload.

**Export downloads an empty file** - Check your filters. If you have filters applied, the export only includes matching records.

**File format not accepted** - Make sure you are uploading `.xlsx` format. CSV and older `.xls` files are not supported.

## Common questions

**Q: Can I import data with relationships (e.g., brands with their team members)?**

Most importers handle one type of data at a time. Import brands first, then manage team members through the brand profile.

**Q: Is there an API for bulk operations?**

The public API is read-only. Bulk writes go through the import feature or the admin panel.
