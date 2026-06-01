---
title: Shaders MCP (Pro)
description: Connect your AI coding agent to Shaders and build stunning effects without leaving your workflow
icon: robot
category: advanced
---

# Shaders MCP (Pro)

::shader-demo
---
preset:
  components:
    - type: ImageTexture
      props:
        url: "https://data.shaders.com/storage/v1/object/public/user-uploaded-images/user_33nh0FG48zZa0rIUZuK7vgwPfZe/0oExGojy9aDN.png"
    - type: Glass
      props:
        aberration: 0.63
        center:
          type: mouse-position
          originX: 0.5
          originY: 0.5
          reach: 0.6
          smoothing: 0.3
          momentum: 0.45
        edgeSoftness: 0.15
        fresnel: 0.25
        fresnelColor: "#96abf2"
        fresnelSoftness: 0.15
        innerZoom: 1.5
        refraction: 1.38
        shape:
          type: circleSDF
          radius: 0.5
        thickness: 1
    - type: Sharpness
      props:
        sharpness: 0.5
---
::

::pro-callout{message="Shaders MCP requires an active Pro account before you can connect."}
::

The Shaders MCP (Model Context Protocol) server lets your AI agent create stunning effects directly. It can browse your saved shaders, search across the full library of Pro presets, install and modify/hook up props, all without switching context.

## Connect your agent to shaders

Most MCP clients support OAuth - just add the server URL, and you'll be redirected to sign in with your Shaders account. No API key needed.

**Cursor**

::cursor-install-button
::

**Other tools**

::code-group{:tabs='["Claude Code", "Windsurf", "Lovable", "Other"]'}
```bash
claude mcp add --transport http shaders https://shaders.com/mcp
```

```json
// .windsurf/mcp_config.json
{
  "mcpServers": {
    "shaders": {
      "serverUrl": "https://shaders.com/mcp"
    }
  }
}
```

```json
// Lovable → Settings → Integrations → MCP Servers
{
  "name": "shaders",
  "url": "https://shaders.com/mcp"
}
```

```json
{
  "mcpServers": {
    "shaders": {
      "type": "http",
      "url": "https://shaders.com/mcp"
    }
  }
}
```
::

---

## Using shaders with Bolt.new

![Bolt connector](/images/docs/bolt-connector-active.png)

Shaders has an official connector on [bolt.new](https://bolt.new) - just open the "Connectors" panel, find Shaders, and log in with your Pro account. No configuration needed. Once enabled, any request to add or edit a shader will use Shaders MCP.

---

## Using an API key instead

If your tool doesn't support OAuth, generate a personal API key below and add it to your configuration.

::api-keys
::

::code-group{:tabs='["Claude Code", "Cursor", "Windsurf", "Lovable", "Codex", "Other"]'}
```bash
claude mcp add --transport http shaders https://shaders.com/mcp --header "Authorization: Bearer YOUR_API_KEY"
```

```json
// .cursor/mcp.json
{
  "mcpServers": {
    "shaders": {
      "url": "https://shaders.com/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_API_KEY"
      }
    }
  }
}
```

```json
// .windsurf/mcp_config.json
{
  "mcpServers": {
    "shaders": {
      "serverUrl": "https://shaders.com/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_API_KEY"
      }
    }
  }
}
```

```json
// Lovable → Settings → Integrations → MCP Servers
{
  "name": "shaders",
  "url": "https://shaders.com/mcp",
  "headers": {
    "Authorization": "Bearer YOUR_API_KEY"
  }
}
```

```toml
[mcp_servers.shaders]
enabled = true
url = "https://shaders.com/mcp"

[mcp_servers.shaders.http_headers]
Authorization = "Bearer YOUR_API_KEY"
```

```json
{
  "mcpServers": {
    "shaders": {
      "type": "http",
      "url": "https://shaders.com/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_API_KEY"
      }
    }
  }
}
```
::

## Start prompting

Once connected, your agent gains access to the following capabilities:

**Browse and retrieve your shaders** - list all effects saved to your Shaders account, then pull ready-to-use component code for any of them in your framework of choice (Vue, React, Svelte, Solid, or vanilla JS). Great for picking up a design you built in the editor and dropping it straight into your codebase.

**Explore the Pro preset library** - search and retrieve Pro collections and presets, complete with code exports, thumbnail previews, and additional context. Watch your agent find, install and customize from a high-quality starting point.

**Modify and connect props to state** - make changes to your shaders and presets in-code, then wire up props to reactive state in your framework of choice for compelling interactive effects.

**Generate SDF from your SVG files** - certain shape effects (like Glass, Emboss, or Neon) require what's called an "SDF" (Signed Distance Field) to work. You can just tell your agent to generate one for you from any SVG file in your codebase or from a public URL. It will generate and store the resulting SDF back in your codebase. In most cases, you won't have to worry about this, the agent knows it's needed and will just ask you which SVG you want to use.

Example prompts to get started:

``` bash
Install my "Landing Hero" shader in the hero section as a background.
```

``` bash
Add a shader to the background of this card, something blue with a subtle flowing animation.
```

``` bash
Make the circle in this shader get bigger as we scroll past the section.
```

``` bash
Add my logo.svg as a liquid glass effect on top of this shader.
```

## Pro knowledge base

Beyond account access, Shaders Pro MCP includes an exclusive knowledge base of expert notes added into your agent's context - detailed guidance on advanced composition patterns that aren't part of the public documentation. Your agent reads these automatically when relevant, so you get higher-quality implementations out of the box rather than having to work through trial and error.

Certain collections and presets also provide additional contextual information, which makes it easier for your agent to understand how to best implement the specific look of that effect.

This is the difference between an agent that knows Shaders exists and one that knows how to use it well.

::pro-callout{message="Make your agent a shader expert..."}
::

---

# MCP for business

![Wonder UI](/images/docs/wonder-ui.jpg)

We work with companies building design tools, AI site builders, visual editors, and code generators. If your product acts as an agent, Shaders MCP can plug in as a creative layer - giving your users access to production-ready WebGPU effects without them ever leaving your platform.

We're open to partnerships and custom integrations. Reach out at [support@shaders.com](mailto:support@shaders.com) and let's figure out a way to connect your users to shader magic.
