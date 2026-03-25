---
title: "Activity logs"
description: "How to view and filter system activity logs."
section: "administration"
order: 5
locale: "en"
audience: "staff"
video_url: ""
video_poster: ""
---

<!-- VIDEO -->

Activity logs track who did what and when across PM One. Use them to audit changes, investigate issues, or understand how people use the system.

## What you will learn

- How to view activity logs
- How to filter by event type and log name

## Viewing logs

1. Go to **Activity Logs** in the sidebar (requires `admin.logs` permission)
2. You will see a chronological list of events

Each log entry shows:
- **Timestamp** - when the action happened
- **User** - who performed it
- **Event** - what happened (created, updated, deleted, etc.)
- **Subject** - what was affected (which post, user, brand, etc.)

## Filtering

Use the filter controls to narrow down logs:

- **Log name** - filter by module (e.g., posts, users, brands)
- **Event type** - filter by action (created, updated, deleted)

## Common questions

**Q: How far back do logs go?**

Logs are kept indefinitely unless manually cleared.

**Q: Can I export logs?**

The log viewer is for on-screen review. For data exports, you would need to query the database directly.

**Q: Who can clear logs?**

Log clearing requires `admin.logs` permission. Be cautious: cleared logs cannot be recovered.

## Up next

- [Web analytics](./06-web-analytics.md) - track link and page performance
