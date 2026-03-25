# PM One Documentation

User-facing documentation for PM One, organized by audience.

## Structure

```
docs/
  staff/en/       → Staff & admin guides (English)
  exhibitor/en/   → Exhibitor guides (English)
  exhibitor/zh/   → Exhibitor guides (Simplified Chinese)
```

## Authoring

- Each article is a standalone markdown file with YAML frontmatter
- Articles are numbered (`01-`, `02-`) for ordering within sections
- Use `_template.md` as the starting point for new articles
- Video placeholders will be filled after Screen Studio recordings are uploaded to Cloudflare R2

## Frontmatter

| Field | Type | Description |
|-------|------|-------------|
| `title` | string | Article title |
| `description` | string | One-line summary for sidebar/search |
| `section` | string | Section slug (e.g. `getting-started`) |
| `order` | number | Sort position within section |
| `locale` | string | `en` or `zh` |
| `audience` | string | `staff` or `exhibitor` |
| `video_url` | string | Cloudflare R2 video URL (filled later) |
| `video_poster` | string | Video thumbnail URL (filled later) |

## Video Embeds

Videos use [vidstack.io](https://vidstack.io/) player. After recording and uploading:

1. Set `video_url` to the R2 URL
2. Set `video_poster` to the thumbnail URL
3. The `<!-- VIDEO -->` comment in each article marks where the player renders

## Article Index

### Staff (42 articles)

| Section | Articles |
|---------|----------|
| Getting Started | Dashboard Overview, Navigation and Layout, Profile and Settings |
| Project Management | Creating and Managing Projects, Project Settings and Members, Custom Fields and Business Categories |
| Event Management | Creating Events, Event Details and Settings, Managing Tickets, Programs and Rundown, Managing Partners, Event Gallery, Event FAQ, Guests and Speakers |
| Brand/Exhibitor Management | Adding Brands to Events, Managing Brand Profiles, Brand Event Settings, Importing and Exporting Brands, Sending Exhibitor Invitations |
| Content Management | Creating Blog Posts, Using the Editor, Managing Tags, Post Analytics and Revisions |
| Operational | Order Management, Event Products and Categories, Event Documents and Agreements, Order Form Settings |
| Communication | Inbox, Contact List Management, Contact Business Categories |
| Tools | Short Links and Dynamic QR Codes, Link Pages, Static QR Code Generator, Form Builder and Responses, Tasks |
| Administration | User Management, Roles and Permissions, API Consumers, Google Analytics Properties, Activity Logs, Web Analytics |
| Data Management | Import and Export |

### Exhibitor (16 articles x 2 languages)

| Section | Articles |
|---------|----------|
| Getting Started | Account Setup and Login, Dashboard Overview, Completing Your Profile |
| Brand Management | Viewing Your Assigned Brands, Editing Your Brand Profile, Managing Brand Team Members |
| Event Participation | Viewing Events and Booth Details |
| Promotion Posts | Creating Promotion Posts, Managing Promotion Images, Reordering Posts |
| Orders | Browsing Event Products, Creating Orders, Tracking Order Status |
| Event Documents | Viewing Required Documents, Submitting Agreements and Files, Tracking Document Completion |
