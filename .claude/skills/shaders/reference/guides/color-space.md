---
title: Color Space
description: Configure output color space for accurate color matching with design tools
icon: palette
category: advanced
---

# Color Space

By default, Shaders uses **Display P3 linear** color space for output. This delivers vibrant, wide-gamut colors on modern displays. However, if you're copying hex colors from Figma, Sketch, or Adobe XD, those tools work in sRGB - so the same hex value may render slightly differently in Shaders.

To match your design tool exactly, set `colorSpace` to `'srgb'`:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader color-space="srgb">
  <SolidColor color="#5b18ca" />
</Shader>
```

```jsx
<Shader colorSpace="srgb">
  <SolidColor color="#5b18ca" />
</Shader>
```

```tsx
<Shader colorSpace="srgb">
  <SolidColor color="#5b18ca" />
</Shader>
```

```tsx
<Shader colorSpace="srgb">
  <SolidColor color="#5b18ca" />
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  colorSpace: 'srgb',
  components: [
    { type: 'SolidColor', props: { color: '#5b18ca' } }
  ]
})
```
::

| Value | Description |
|-------|-------------|
| `p3-linear` | Display P3 linear (default). Wide gamut, physically accurate blending. |
| `srgb` | sRGB. Matches hex colors from Figma, Sketch, and Adobe XD exactly. |

## Output space vs. gradient interpolation

The `colorSpace` prop on `<Shader>` controls the *output rendering space* - how final pixel values are interpreted by your display.

Several gradient and color components also have their own `colorSpace` prop that controls how colors *interpolate between stops* (for example, between `colorA` and `colorB` in a `LinearGradient`). These are two separate concepts:

- **Output color space** - set on the root `<Shader>`, affects the entire canvas output
- **Interpolation color space** - set per-component, controls color blending within that effect

Available interpolation modes (where supported): `linear`, `oklch`, `oklab`, `hsl`, `hsv`, `lch`. See individual component docs for options.

## Tone mapping

When you layer additive blend modes like `linearDodge` or build up bright glows, color channels can exceed 1.0 (HDR values). In the default `linear` mode these are hard-clipped. Tone mapping compresses that range smoothly before display - the same way a camera's film curve handles overexposure.

Set `toneMapping` on the root `<Shader>` component:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader tone-mapping="aces">
  <!-- your components -->
</Shader>
```

```jsx
<Shader toneMapping="aces">
  {/* your components */}
</Shader>
```

```tsx
<Shader toneMapping="aces">
  {/* your components */}
</Shader>
```

```tsx
<Shader toneMapping="aces">
  {/* your components */}
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  toneMapping: 'aces',
  components: [/* ... */]
})
```
::

| Value | Character |
|-------|-----------|
| `linear` | No compression (default). Values pass through as-is. |
| `reinhard` | Classic `x / (1 + x)` curve. Gentle, natural roll-off. |
| `cineon` | Optimized filmic curve. Warm, contrasty highlights. |
| `aces` | ACES Filmic. Industry-standard cinematic look - punchy colors, slightly darker overall. |
| `agx` | AgX. Modern operator with the best hue accuracy at high saturation. |
| `neutral` | Khronos Neutral. Accurate, minimal stylization. |
| `hable` | Uncharted 2 / Hable. Warm, smooth whites with a filmic shoulder. |
| `unreal` | Unreal Engine curve. Bright and smooth. |

`toneMapping` and `colorSpace` are independent - they can be combined freely. `toneMapping` compresses the brightness range of the final image; `colorSpace` controls the color gamut used for output.
