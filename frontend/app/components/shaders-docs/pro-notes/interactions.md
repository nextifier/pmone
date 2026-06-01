---
title: Adding Interactions - Interactive Components and Dynamic Prop Mapping
description: How to make shaders respond to the cursor using interactive components or dynamic prop mapping, with recommended defaults
components: [ChromaFlow, CursorRipples, CursorTrail, GridDistortion, Liquify, Shatter]
---

# Adding Interactions - Interactive Components and Dynamic Prop Mapping

Interactive shaders are one of the most compelling things you can build with Shaders. There are three distinct ways to add reactivity, and choosing the right one depends on what you want to achieve.

## Option 1: Interactive category components

The Interactive category contains components purpose-built for cursor response:

- **CursorRipples** - ripple waves emanating from the cursor position
- **CursorTrail** - a glowing trail that follows the cursor path
- **ChromaFlow** - color-shifting fluid that reacts to cursor movement
- **GridDistortion** - a grid mesh that warps under the cursor
- **Liquify** - paint-like displacement that follows cursor drags
- **Shatter** - fractures the composition as the cursor passes through

These require no additional setup - drop one into a shader and it works immediately. They are the fastest path to a polished interactive effect. `CursorRipples` is by far the most popular, but don't overuse it if it doesn't fit your design.

## Option 2: Dynamic prop mapping

Any prop marked as supporting dynamic mapping can be bound to the mouse cursor position, a scroll offset, or an auto-animation (ping-pong or continuous loop). This lets you drive existing components - a gradient's center, a blur's focal point, a radial highlight's position - with the cursor rather than hardcoding a static value.

For a complete reference covering all four driver modes and the powerful `map` mode, see the [dynamic prop mapping pro note](shaders://pro-notes/dynamic-prop-mapping). The regular framework docs also cover syntax and code examples.

Dynamic prop mapping is the right choice when:
- You want a subtle, ambient cursor response rather than an explicit interactive effect
- You want to animate a non-interactive component (e.g. slowly drifting the center of a `RadialGradient`)
- You need to synchronize multiple props across components
- Fun trick: you can use a `Circle` with very high softness that follows the cursor as a mask for other components, to reveal only the part of the composition that's under the cursor.

### Recommended defaults for mouse-based position

Start with **Smoothing: `0.2`** and **Momentum: `0.2`**. This produces a gentle lag with a slight overshoot - organic rather than mechanical. Zero `Smoothing` feels instant and robotic; above `0.5` it becomes noticeably sluggish. Zero `Momentum` removes the overshoot entirely.

To keep the element following the cursor within a limited area, reduce the `reach` threshold; very small values make the element pull toward the cursor instead of tracking it.

You can also invert or lock the axis, which creates an effect where the positioned element avoids the cursor or only moves left/right or up/down.

## Reactive props

If these options don't fit your needs, you can go way deeper by simply passing reactive state to props. This is a powerful way to build interactive effects that are not covered by the above components, and you can use external libraries like motion or gsap to drive animations that are way more complex or structured into timelines.