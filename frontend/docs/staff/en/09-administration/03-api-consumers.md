---
title: "API consumers"
description: "How to create and manage API keys for external services."
section: "administration"
order: 3
locale: "en"
audience: "staff"
video_url: ""
video_poster: ""
---

<!-- VIDEO -->

API consumers are external applications that access PM One's public API. Each consumer gets its own API key for authentication and has usage tracking.

## What you will learn

- How to create an API consumer
- How to manage API keys
- How to monitor API usage

## Creating an API consumer

1. Go to **API Consumers** in the sidebar
2. Click **Create**
3. Enter a name and description (e.g., "Megabuild Event Website")
4. Click **Save**

An API key is generated automatically. Copy it and store it securely. You will not be able to see the full key again after leaving the page.

## Managing API keys

### Regenerating a key

If a key is compromised, open the consumer and click **Regenerate key**. The old key stops working immediately.

### Toggling active/inactive

Deactivate a consumer to temporarily block its API access without deleting it. Reactivate it when ready.

## Monitoring usage

Open an API consumer and click the **Analytics** tab to see:

- Request counts over time
- Most-used endpoints
- Error rates

The overall API consumer analytics page shows aggregate stats across all consumers.

## Deleting consumers

Delete a consumer to permanently revoke its API access. Deleted consumers go to the trash for recovery.

## Common questions

**Q: How many API consumers can I create?**

There is no limit.

**Q: What endpoints does the public API expose?**

The public API provides event data (blog posts, brands, tickets, rundown, gallery, partners, FAQ, contact form, short links, and sitemap URLs). It is read-only for external consumers.

## Up next

- [Google Analytics properties](./04-google-analytics-properties.md) - connect your GA4 accounts
