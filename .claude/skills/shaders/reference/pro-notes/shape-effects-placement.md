---
title: Shape Effects - Placement, Sizing, and Stacking
description: How to place and size Glass, Neon, Emboss, and Crystal effects relative to the UI, and why you should avoid stacking them
components: [Glass, Neon, Emboss, Crystal, Circle, Ellipse, Polygon, Ring, RoundedRect, Star]
---

# Shape Effects - Placement, Sizing, and Stacking

## What Shape Effects are

The Shape Effects category (Glass, Neon, Emboss, etc.) applies surface simulation - lighting, refraction, embossing - to a defined shape. They are high-impact, localized effects: a glass logo, a neon-lit icon, an embossed badge. They look best when they sit beside UI content, not buried behind it.

## Placement: near UI, not behind it

By default, shape effects render at a moderate size over a specific area of the canvas. This is intentional - they are meant to live next to interface elements or be standalone front-and-center, where they catch the eye without being obscured by content.

**Do:** position a Glass or Neon component where it's clearly visible.
**Avoid:** placing a small shape effect behind a content block where it will be invisible.

The exception is `Glass`, which works well as a very large background effect that extends slightly beyond the screen edges, and can serve as a full-screen logo or shape behind content sections.

## Do not stack multiple shape effects

Each shape effect performs expensive GPU work (lighting simulation, normal-map calculation, refraction). Stacking two or more on the same canvas compounds both the GPU cost and the visual noise. You'll likely never need two or more of these effects on the same canvas.

## The Shapes category is a different thing

The components in the Shapes category (Circle, Ellipse, Polygon, Ring, RoundedRect, Star, etc.) are simple SDF generators - they draw a flat, colored shape to the canvas. They are rarely interesting on their own as a final visual element, but they are excellent for two purposes:

1. **Masks** - set `visible={false}` and reference the shape's `id` as a `maskSource` on other layers to create shaped reveals, vignettes, and soft edges. See `shaders://pro-notes/hero-section-masking`.
2. **Dynamic prop inputs** - animate or bind a shape's position, radius, or center to the mouse cursor or a timeline. The shape becomes a driver for other behavior rather than a visual in its own right.

When you find yourself reaching for a plain Circle or Ellipse as the primary visual, reconsider - there is likely a richer Texture or Shape Effect component that better serves the goal.
