---
title: "Project settings and members"
description: "How to configure project settings and manage who has access."
section: "project-management"
order: 2
locale: "en"
audience: "staff"
video_url: ""
video_poster: ""
---

<!-- VIDEO -->

Each project has its own settings page where you control general info, team membership, brand field configuration, and contact form integration.

## What you will learn

- How to access project settings
- How to add and remove project members
- What each settings tab does

## Accessing project settings

1. Open your project from the **Projects** list
2. Click **Settings** in the project sidebar (or the gear icon)

## Settings tabs

### General

Change the project name, slug, description, and logo. These show up in the sidebar, dashboard, and API responses for event websites.

### Members

This is where you control who can access the project.

1. Click **Members** tab
2. Click **Add member** to see a list of eligible users
3. Toggle users on or off to add or remove them

Project members can see the project in their sidebar and access its events, brands, and other content.

### Brand fields

Configure custom fields that appear on brand profiles within this project. These fields are project-specific, so different exhibitions can collect different exhibitor information.

### Contact form

Set up the contact form integration for this project's event website. This controls where form submissions go when visitors fill out the contact page.

## Common questions

**Q: I added a user but they can't see the project.**

Make sure the user's role has the `projects.read` permission. Membership alone is not enough; their role also needs the right permissions.

**Q: Can I add someone to a project without giving them access to other projects?**

Yes. Project membership is per-project. A user only sees projects they have been added to.

## Up next

- [Custom fields and business categories](./03-custom-fields-business-categories.md) - set up project-specific data fields
