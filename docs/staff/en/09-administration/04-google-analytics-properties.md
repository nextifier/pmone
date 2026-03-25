---
title: "Google Analytics properties"
description: "How to connect GA4 properties and view website analytics within PM One."
section: "administration"
order: 4
locale: "en"
audience: "staff"
video_url: ""
video_poster: ""
---

<!-- VIDEO -->

PM One can pull data from your Google Analytics 4 properties so you can view website traffic alongside your event management data. This feature is available to users with the Master role.

## What you will learn

- How to connect a GA4 property
- How to view synced analytics
- How to manage sync schedules

## Connecting a GA4 property

1. Go to **Google Analytics Properties** in the sidebar (Master role required)
2. Click **Create**
3. Enter the GA4 property ID and display name
4. Configure authentication credentials
5. Click **Save**

## Viewing analytics

Once connected, PM One syncs data from your GA4 property on a schedule. Open a property to see:

- Page views and sessions over time
- Real-time active users
- Traffic sources
- Top pages

## Import and export

You can import multiple GA4 property configurations from a spreadsheet, or export your current list.

## Sync management

PM One syncs GA4 data periodically. Check the **Sync history** page to see when the last sync ran and whether it succeeded.

If a sync fails, the error details appear in the log. Common issues include expired credentials or rate limiting from Google's API.

### Cache management

Analytics data is cached locally. You can clear the cache from the property settings if you need to force a fresh data pull.

## Common questions

**Q: Why do I need Master role for this?**

GA4 credentials provide access to traffic data across all your websites. This is sensitive information restricted to the highest access level.

**Q: How often does data sync?**

The sync runs on a configurable schedule. Check the sync logs for the current frequency.

## Up next

- [Activity logs](./05-activity-logs.md) - monitor system activity
