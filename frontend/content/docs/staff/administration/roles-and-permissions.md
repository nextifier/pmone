---
title: "Roles and permissions"
description: "How to create roles and assign permissions to control access."
section: "administration"
order: 2
locale: "en"
audience: "staff"
video_url: ""
video_poster: ""
---

<!-- VIDEO -->

Roles group permissions together. Instead of assigning individual permissions to each user, you assign a role that includes the permissions they need.

## What you will learn

- How the permission system works
- How to create and edit roles
- Built-in roles and their access levels

## How permissions work

Permissions follow a resource-action pattern: `resource.action`. For example:

- `posts.read` - can view posts
- `posts.write` - can create and edit posts
- `posts.delete` - can delete posts

Each resource (users, posts, projects, brands, events, etc.) has its own set of actions.

There are also special permissions:
- `admin.view` - can access the admin dashboard
- `admin.logs` - can view activity logs
- `analytics.view` - can access analytics pages

## Built-in roles

PM One comes with default roles:

- **Master** - full access to everything, including system-level features
- **Admin** - full CRUD on resources they have permissions for
- **Staff** - operational roles with specific permission subsets
- **Writer** - blog post management and analytics only
- **Exhibitor** - brand management and event participation
- **User** - basic account access

## Creating a role

1. Go to **Roles** in the sidebar
2. Click **Create**
3. Enter a role name
4. Select the permissions to include
5. Click **Save**

## Editing a role

Click any role to change its name or permission set. Changes apply to all users with that role immediately.

## The permissions page

Go to **Permissions** to see the full list of available permissions, grouped by resource. You can create custom permissions here if the defaults do not cover your needs.

## Common questions

**Q: Can I give a user extra permissions beyond their role?**

The system works through roles. If you need a custom combination, create a new role with the specific permissions you want.

**Q: What happens if I delete a role that has users?**

You will need to reassign those users to a different role first. The system prevents deleting a role that is in use.

