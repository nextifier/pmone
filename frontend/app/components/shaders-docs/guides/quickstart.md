---
title: Quickstart
description: Install Shaders and build your first GPU-accelerated effect
icon: rocket
category: concepts
exclude: [js]
---

# Quickstart

## Installation

```bash
npm install shaders
```

Shaders is a single package regardless of your framework. No framework-specific adapters needed.

## Import your components

Import from the entry point that matches your framework:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid"]'}
```javascript
import { Shader, LinearGradient, CursorTrail } from 'shaders/vue'
```

```javascript
import { Shader, LinearGradient, CursorTrail } from 'shaders/react'
```

```javascript
import { Shader, LinearGradient, CursorTrail } from 'shaders/svelte'
```

```javascript
import { Shader, LinearGradient, CursorTrail } from 'shaders/solid'
```
::

## Your first shader

Wrap your effects in a `<Shader>` component and add children inside:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid"]'}
```vue-html
<Shader class="w-full h-64">
  <LinearGradient colorA="#0f172a" colorB="#7c3aed" />
  <CursorTrail />
</Shader>
```

```jsx
<Shader className="w-full h-64">
  <LinearGradient colorA="#0f172a" colorB="#7c3aed" />
  <CursorTrail />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <LinearGradient colorA="#0f172a" colorB="#7c3aed" />
  <CursorTrail />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <LinearGradient colorA="#0f172a" colorB="#7c3aed" />
  <CursorTrail />
</Shader>
```
::

::shader-demo
---
preset:
  components:
    - type: LinearGradient
      props:
        colorA: "#0f172a"
        colorB: "#7c3aed"
    - type: CursorTrail
---
::

`<Shader>` renders a `<canvas>` element. The child components are visual layers - evaluated top to bottom, blended together on the GPU. The class or style you apply to `<Shader>` controls the canvas size and position.

## Sizing the canvas

The canvas has no intrinsic size, so you must give it dimensions. Any CSS sizing works - Tailwind classes, inline styles, or a custom class:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid"]'}
```vue-html
<!-- Fill the viewport -->
<Shader class="w-screen h-screen">
  <LinearGradient />
</Shader>

<!-- Fixed dimensions -->
<Shader style="width: 600px; height: 400px;">
  <LinearGradient />
</Shader>

<!-- Aspect ratio with fluid width -->
<Shader class="w-full aspect-video">
  <LinearGradient />
</Shader>
```

```jsx
{/* Fill the viewport */}
<Shader className="w-screen h-screen">
  <LinearGradient />
</Shader>

{/* Fixed dimensions */}
<Shader style={{ width: '600px', height: '400px' }}>
  <LinearGradient />
</Shader>

{/* Aspect ratio with fluid width */}
<Shader className="w-full aspect-video">
  <LinearGradient />
</Shader>
```

```tsx
<!-- Fill the viewport -->
<Shader class="w-screen h-screen">
  <LinearGradient />
</Shader>

<!-- Fixed dimensions -->
<Shader style="width: 600px; height: 400px;">
  <LinearGradient />
</Shader>

<!-- Aspect ratio with fluid width -->
<Shader class="w-full aspect-video">
  <LinearGradient />
</Shader>
```

```tsx
{/* Fill the viewport */}
<Shader class="w-screen h-screen">
  <LinearGradient />
</Shader>

{/* Fixed dimensions */}
<Shader style={{ width: '600px', height: '400px' }}>
  <LinearGradient />
</Shader>

{/* Aspect ratio with fluid width */}
<Shader class="w-full aspect-video">
  <LinearGradient />
</Shader>
```
::

The canvas automatically resizes when its CSS dimensions change - no manual resize calls needed.

## Configuring components

Each component accepts props that control its appearance. Browse the [Component Docs](/docs/components) for full prop references, or use the Design Editor to explore and export ready-to-paste configurations:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid"]'}
```vue-html
<Shader class="w-full h-64">
  <LinearGradient
    colorA="#ff6b6b"
    colorB="#4ecdc4"
    :angle="45"
    color-space="oklch"
  />
</Shader>
```

```jsx
<Shader className="w-full h-64">
  <LinearGradient
    colorA="#ff6b6b"
    colorB="#4ecdc4"
    angle={45}
    colorSpace="oklch"
  />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <LinearGradient
    colorA="#ff6b6b"
    colorB="#4ecdc4"
    angle={45}
    colorSpace="oklch"
  />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <LinearGradient
    colorA="#ff6b6b"
    colorB="#4ecdc4"
    angle={45}
    colorSpace="oklch"
  />
</Shader>
```
::

## Using with SSR frameworks

Shaders uses WebGPU, which requires a browser environment. If you're using Nuxt, Next.js, SvelteKit, or SolidStart, see the framework-specific SSR guide for your setup.

## Next steps

- [Composing Effects](/docs/guide/composing-effects) - stack and nest components for complex results
- [Layout & Positioning](/docs/guide/layout-positioning) - position the canvas in your UI
- [Props & Reactivity](/docs/guide/props-reactivity) - bind state and animate props
- [Component Docs](/docs/components) - browse all available shaders
