---
title: Layout & Positioning
description: How to size, position, and integrate shaders into your application layout
icon: file-code
category: concepts
---

# Layout & Positioning

The `<Shader>` component renders a `<canvas>` element - a standard HTML block element with no intrinsic size. You control its dimensions and position entirely through CSS, the same way you would any other element. Understanding this model unlocks the full range of creative possibilities.

## The canvas model

Every `<Shader>` renders exactly one `<canvas>`. It doesn't matter how many components you include in the shader, it will be one canvas, with one shader running.

## Sizing the canvas

Apply classes or styles directly to the `<Shader>` component:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<!-- Tailwind -->
<Shader class="w-full h-64">
  <LinearGradient />
</Shader>

<!-- CSS class -->
<Shader class="hero-shader">
  <LinearGradient />
</Shader>

<!-- Inline -->
<Shader style="width: 600px; height: 400px;">
  <LinearGradient />
</Shader>
```

```jsx
<Shader className="w-full h-64">
  <LinearGradient />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <LinearGradient />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <LinearGradient />
</Shader>
```

```javascript
// Apply sizing to the canvas element before calling createShader
const canvas = document.getElementById('my-shader')
canvas.style.width = '100%'
canvas.style.height = '400px'

const shader = await createShader(canvas, { components: [...] })
```
::

## Common layout patterns

### Full-page background

A shader that stays fixed behind all page content, covering the entire viewport:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<!-- In your layout or App.vue -->
<Shader class="fixed inset-0 -z-10">
  <Aurora />
</Shader>

<main class="relative z-10">
  <!-- Your page content scrolls over the shader -->
</main>
```

```jsx
<>
  <Shader className="fixed inset-0 -z-10">
    <Aurora />
  </Shader>
  <main className="relative z-10">
    {/* Your page content */}
  </main>
</>
```

```tsx
<Shader class="fixed inset-0 -z-10">
  <Aurora />
</Shader>

<main class="relative z-10">
  <!-- Your page content -->
</main>
```

```tsx
<>
  <Shader class="fixed inset-0 -z-10">
    <Aurora />
  </Shader>
  <main class="relative z-10">
    {/* Your page content */}
  </main>
</>
```

```html
<canvas id="bg" style="position:fixed;inset:0;z-index:-10;width:100%;height:100%;"></canvas>
<main style="position:relative;z-index:10;">Your content</main>

<script type="module">
  import { createShader } from 'shaders/js'
  const shader = await createShader(document.getElementById('bg'), {
    components: [{ type: 'Aurora', props: {} }]
  })
</script>
```
::

### Section background

A shader that fills a content section without affecting the rest of the page. The parent must have `position: relative` and `overflow: hidden` to contain the canvas:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid"]'}
```vue-html
<section class="relative overflow-hidden py-24 px-8">
  <Shader class="absolute inset-0 -z-10">
    <Plasma />
  </Shader>

  <h2 class="relative text-4xl font-bold text-white">Section Heading</h2>
  <p class="relative text-white/80 mt-4">Content above the shader.</p>
</section>
```

```jsx
<section className="relative overflow-hidden py-24 px-8">
  <Shader className="absolute inset-0 -z-10">
    <Plasma />
  </Shader>
  <h2 className="relative text-4xl font-bold text-white">Section Heading</h2>
  <p className="relative text-white/80 mt-4">Content above the shader.</p>
</section>
```

```tsx
<section class="relative overflow-hidden py-24 px-8">
  <Shader class="absolute inset-0 -z-10">
    <Plasma />
  </Shader>
  <h2 class="relative text-4xl font-bold text-white">Section Heading</h2>
</section>
```

```tsx
<section class="relative overflow-hidden py-24 px-8">
  <Shader class="absolute inset-0 -z-10">
    <Plasma />
  </Shader>
  <h2 class="relative text-4xl font-bold text-white">Section Heading</h2>
</section>
```
::

### Card with shader fill

A shader used as the visual background of a card or panel:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid"]'}
```vue-html
<div class="relative rounded-2xl overflow-hidden p-8 text-white">
  <Shader class="absolute inset-0">
    <SolidColor color="#1e1b4b" />
    <Glow :intensity="0.4" color="#6366f1" />
  </Shader>

  <!-- Content sits above the shader -->
  <div class="relative z-10">
    <h3 class="text-xl font-semibold">Card Title</h3>
    <p class="text-white/70 mt-2">Card description here.</p>
  </div>
</div>
```

```jsx
<div className="relative rounded-2xl overflow-hidden p-8 text-white">
  <Shader className="absolute inset-0">
    <SolidColor color="#1e1b4b" />
    <Glow intensity={0.4} color="#6366f1" />
  </Shader>
  <div className="relative z-10">
    <h3 className="text-xl font-semibold">Card Title</h3>
    <p className="text-white/70 mt-2">Card description here.</p>
  </div>
</div>
```

```tsx
<div class="relative rounded-2xl overflow-hidden p-8 text-white">
  <Shader class="absolute inset-0">
    <SolidColor color="#1e1b4b" />
    <Glow intensity={0.4} color="#6366f1" />
  </Shader>
  <div class="relative z-10">
    <h3 class="text-xl font-semibold">Card Title</h3>
  </div>
</div>
```

```tsx
<div class="relative rounded-2xl overflow-hidden p-8 text-white">
  <Shader class="absolute inset-0">
    <SolidColor color="#1e1b4b" />
    <Glow intensity={0.4} color="#6366f1" />
  </Shader>
  <div class="relative z-10">
    <h3 class="text-xl font-semibold">Card Title</h3>
  </div>
</div>
```
::

### Inline content block

A shader that flows naturally in document layout, like an image or video:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid"]'}
```vue-html
<article>
  <p>Text before the shader.</p>

  <Shader class="w-full aspect-video my-8 rounded-xl">
    <Swirl />
  </Shader>

  <p>Text after the shader continues here.</p>
</article>
```

```jsx
<article>
  <p>Text before the shader.</p>
  <Shader className="w-full aspect-video my-8 rounded-xl">
    <Swirl />
  </Shader>
  <p>Text after the shader continues here.</p>
</article>
```

```tsx
<article>
  <p>Text before the shader.</p>
  <Shader class="w-full aspect-video my-8 rounded-xl">
    <Swirl />
  </Shader>
  <p>Text after the shader continues here.</p>
</article>
```

```tsx
<article>
  <p>Text before the shader.</p>
  <Shader class="w-full aspect-video my-8 rounded-xl">
    <Swirl />
  </Shader>
  <p>Text after the shader continues here.</p>
</article>
```
::

## Layering content over shaders

### Pointer events

Canvases capture all pointer events by default. Add `pointer-events: none` when the shader is decorative and you want clicks and hovers to reach content underneath:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid"]'}
```vue-html
<Shader class="absolute inset-0 pointer-events-none">
  <Aurora />
</Shader>
```

```jsx
<Shader className="absolute inset-0 pointer-events-none">
  <Aurora />
</Shader>
```

```tsx
<Shader class="absolute inset-0 pointer-events-none">
  <Aurora />
</Shader>
```

```tsx
<Shader class="absolute inset-0 pointer-events-none">
  <Aurora />
</Shader>
```
::

Interactive effects like `CursorTrail` and `CursorRipples` still track global mouse position even with `pointer-events: none` - they listen on the window rather than the canvas element.

### Stacking context and z-index

`z-index` only works on positioned elements. Ensure any content you want above the canvas has `position: relative` (or similar) and a higher `z-index`:

```html
<div class="relative">
  <!-- canvas at z-0 -->
  <canvas class="absolute inset-0"></canvas>
  <!-- content above canvas -->
  <div class="relative z-10">I appear above the canvas</div>
</div>
```

## Responsive sizing

Shaders responds to any CSS-based resize:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid"]'}
```vue-html
<!-- Different heights at breakpoints -->
<Shader class="w-full h-48 md:h-72 lg:h-screen">
  <LinearGradient />
</Shader>

<!-- Aspect ratio preserving at any width -->
<Shader class="w-full aspect-square md:aspect-video">
  <Swirl />
</Shader>

<!-- Dynamic viewport height (mobile-safe) -->
<Shader class="w-full h-[100dvh]">
  <Aurora />
</Shader>
```

```jsx
<Shader className="w-full h-48 md:h-72 lg:h-screen">
  <LinearGradient />
</Shader>
```

```tsx
<Shader class="w-full h-48 md:h-72 lg:h-screen">
  <LinearGradient />
</Shader>
```

```tsx
<Shader class="w-full h-48 md:h-72 lg:h-screen">
  <LinearGradient />
</Shader>
```
::

## Other CSS properties

The canvas element is fully styleable with standard CSS:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid"]'}
```vue-html
<!-- Rounded with border -->
<Shader class="rounded-2xl border border-white/10 w-full h-48">
  <LinearGradient />
</Shader>

<!-- Drop shadow -->
<Shader class="shadow-2xl shadow-indigo-500/30 rounded-xl w-64 h-64">
  <Plasma />
</Shader>

<!-- CSS blur (applied after GPU render) -->
<Shader class="blur-sm w-full h-32">
  <LinearGradient />
</Shader>
```

```jsx
<Shader className="rounded-2xl border border-white/10 w-full h-48">
  <LinearGradient />
</Shader>
```

```tsx
<Shader class="rounded-2xl border border-white/10 w-full h-48">
  <LinearGradient />
</Shader>
```

```tsx
<Shader class="rounded-2xl border border-white/10 w-full h-48">
  <LinearGradient />
</Shader>
```
::
