---
title: Hero Section Masking Techniques
description: Common masking patterns for building hero sections
components: [Circle, Ellipse, Group]
---

# Hero Section Masking Techniques

When using a shader as a hero background, you'll often want to mask parts of the effect so that content remains readable. For example, if text is left-aligned, the shader can fade out on the left and stay vibrant on the right. Masking is the right approach here - it's more performant than CSS `mask-image` or `clip-path` because it runs entirely on the GPU within the shader pipeline, and it allows you to only partially mask some content, but not all.

## How masking works

Masking uses two components: a **mask layer** and a **target layer**.

1. Give the mask layer an `id` prop (e.g., `id="heroMask"`)
2. Set `visible` to `false` on the mask so it doesn't render directly
3. On the target layer(s), set `maskSource` to the mask's ID (e.g., `maskSource="heroMask"`)

The mask's alpha channel determines what's visible - white areas show through, transparent areas are hidden.

## Masking multiple layers

If your shader has several visual layers that all need masking, wrap them in a `Group` and mask the group instead of each layer individually. Be aware that any custom `blendMode` on a child won't transfer through the group - the group gets its own blend mode. If individual blend modes matter, mask each layer separately. You can use the same mask source over multiple target layers.

## Ready-to-use mask presets

Below are pre-made mask configurations you can drop into any shader. All examples use React syntax - adapt the prop format for other frameworks. Apply the mask to any layer by adding `maskSource="heroMask"`.

### Right-side reveal

Content on the left, shader fades in from the right. The most common hero layout.

```jsx
<Circle id="heroMask" visible={false} color="#ffffff" radius={2} softness={1} center={{ x: 1, y: 0.5 }} />
```

### Left-side reveal

Shader visible on the left, fading out toward the right.

```jsx
<Circle id="heroMask" visible={false} color="#ffffff" radius={2} softness={1} center={{ x: 0, y: 0.5 }} />
```

### Center spotlight

Radial vignette that keeps the center visible and fades toward all edges.

```jsx
<Circle id="heroMask" visible={false} color="#ffffff" radius={1.2} softness={0.8} center={{ x: 0.5, y: 0.5 }} />
```

You could potentially invert this with `alphaInverted` as the mask type to fade out the center.

### Top fade

Shader visible at the top, fading out toward the bottom. Good for header/nav backgrounds.

```jsx
<Circle id="heroMask" visible={false} color="#ffffff" radius={2} softness={1} center={{ x: 0.5, y: 0 }} />
```

### Bottom fade

Shader visible at the bottom, fading upward. Good for footer backgrounds or above-the-fold transitions.

```jsx
<Circle id="heroMask" visible={false} color="#ffffff" radius={2} softness={1} center={{ x: 0.5, y: 1 }} />
```

You can also use `Ellipse` instead of `Circle` for more stretched shapes in one particular axis.

## Mask type options

By default, masking uses the alpha channel (`maskType="alpha"`). Other options:

- `alphaInverted` - flips the mask (hide what would be shown, show what would be hidden)
- `luminance` - uses brightness instead of alpha, useful for gradient-based masks
- `luminanceInverted` - inverted luminance

For hero sections, `alphaInverted` is useful when you want to *carve out* a readable zone - position the mask over the text area and invert it so the shader fades away where the text sits.

## When to mask and not mask?

You don't typically want to mask the entire shader, just the layers that are intricate or detailed. Leave at least a background layer unmasked - or add a `SolidColor` layer behind everything with an appropriate color. While you could let the background be transparent, a solid color is usually better for catching blend modes, subtle stylization effects, and keeping the composition grounded.

Similarly, avoid masking stylization effects that sit on top of the whole composition, such as `FilmGrain`, `Ascii`, or `Dither`. These apply to the already-masked content beneath them - masking them too would defeat the purpose.
