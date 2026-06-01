---
title: Blending & Masking
description: Control how components composite together and selectively reveal content
icon: circles-overlap
category: features
---

# Blending & Masking

Beyond stacking and nesting, you have precise control over how components combine and interact. Blend modes determine how layers mix together, while masks let you selectively show or hide portions of your effects.

## Blend Modes

Every component has a `blendMode` prop that controls how it composites with the layers below it. By default, components use `normal` blending (standard alpha compositing), but you have 20 different blend modes to choose from.

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <LinearGradient />

  <!-- Multiply darkens by multiplying colors -->
  <Circle color="#ff0088" radius="0.5" blendMode="multiply" />
</Shader>
```

```jsx
<Shader>
  <LinearGradient />

  {/* Multiply darkens by multiplying colors */}
  <Circle color="#ff0088" radius={0.5} blendMode="multiply" />
</Shader>
```

```tsx
<Shader>
  <LinearGradient />

  {/* Multiply darkens by multiplying colors */}
  <Circle color="#ff0088" radius={0.5} blendMode="multiply" />
</Shader>
```

```tsx
<Shader>
  <LinearGradient />

  {/* Multiply darkens by multiplying colors */}
  <Circle color="#ff0088" radius={0.5} blendMode="multiply" />
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  components: [
    { type: 'LinearGradient', props: {} },
    { type: 'Circle', props: { color: '#ff0088', radius: 0.5, blendMode: 'multiply' } }
  ]
})
```
::

::shader-demo
---
preset:
  components:
    - type: LinearGradient
    - type: Circle
      props:
        color: "#ff0088"
        radius: 0.5
        blendMode: "multiply"
---
::

**Available blend modes:**

- **Basic**: `normal`
- **Darkening**: `multiply`, `darken`, `colorBurn`, `linearBurn`
- **Lightening**: `screen`, `lighten`, `colorDodge`, `linearDodge`
- **Contrast**: `overlay`, `softLight`, `hardLight`
- **Difference**: `difference`, `exclusion`
- **Color**: `hue`, `saturation`, `color`, `luminosity`
- **Color Space Blending**: `normal-oklab`, `normal-oklch`

### Combining Blend Modes

Use different blend modes on multiple layers to create complex interactions:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <LinearGradient />

  <Circle color="#00ffff" radius="0.6" :center="{ x: 0.4, y: 0.5 }" blendMode="screen" />
  <Circle color="#ff00ff" radius="0.6" :center="{ x: 0.6, y: 0.5 }" blendMode="difference" />
</Shader>
```

```jsx
<Shader>
  <LinearGradient />

  <Circle color="#00ffff" radius={0.6} center={{ x: 0.4, y: 0.5 }} blendMode="screen" />
  <Circle color="#ff00ff" radius={0.6} center={{ x: 0.6, y: 0.5 }} blendMode="difference" />
</Shader>
```

```tsx
<Shader>
  <LinearGradient />

  <Circle color="#00ffff" radius={0.6} center={{ x: 0.4, y: 0.5 }} blendMode="screen" />
  <Circle color="#ff00ff" radius={0.6} center={{ x: 0.6, y: 0.5 }} blendMode="difference" />
</Shader>
```

```tsx
<Shader>
  <LinearGradient />

  <Circle color="#00ffff" radius={0.6} center={{ x: 0.4, y: 0.5 }} blendMode="screen" />
  <Circle color="#ff00ff" radius={0.6} center={{ x: 0.6, y: 0.5 }} blendMode="difference" />
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  components: [
    { type: 'LinearGradient', props: {} },
    { type: 'Circle', props: { color: '#00ffff', radius: 0.6, center: { x: 0.4, y: 0.5 }, blendMode: 'screen' } },
    { type: 'Circle', props: { color: '#ff00ff', radius: 0.6, center: { x: 0.6, y: 0.5 }, blendMode: 'difference' } }
  ]
})
```
::

::shader-demo
---
preset:
  components:
    - type: LinearGradient
    - type: Circle
      props:
        color: "#00ffff"
        radius: 0.6
        center:
          x: 0.4
          y: 0.5
        blendMode: "screen"
    - type: Circle
      props:
        color: "#ff00ff"
        radius: 0.6
        center:
          x: 0.6
          y: 0.5
        blendMode: "difference"
---
::

## Opacity

Control layer transparency with the `opacity` prop. Values range from `0` (fully transparent) to `1` (fully opaque):

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <LinearGradient />

  <Circle color="#ff0088" radius="0.8" opacity="0.5" />
</Shader>
```

```jsx
<Shader>
  <LinearGradient />

  <Circle color="#ff0088" radius={0.8} opacity={0.5} />
</Shader>
```

```tsx
<Shader>
  <LinearGradient />

  <Circle color="#ff0088" radius={0.8} opacity={0.5} />
</Shader>
```

```tsx
<Shader>
  <LinearGradient />

  <Circle color="#ff0088" radius={0.8} opacity={0.5} />
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  components: [
    { type: 'LinearGradient', props: {} },
    { type: 'Circle', props: { color: '#ff0088', radius: 0.8, opacity: 0.5 } }
  ]
})
```
::

::shader-demo
---
preset:
  components:
    - type: LinearGradient
    - type: Circle
      props:
        color: "#ff0088"
        radius: 0.8
        opacity: 0.5
---
::

Opacity works with all blend modes. It multiplies the component's alpha channel before blending, giving you fine-grained control over layer strength.

## Visibility

Every component has a `visible` prop that defaults to `true`. Setting it to `false` completely removes the component from the composition, as if it wasn't there at all:

```vue
<Circle visible={false} />
```

This is different from `opacity={0}`, which still processes the component but makes it transparent. Use `visible={false}` when you want a component to exist in the tree (perhaps as a mask source) but not appear in the final output.

## Masking

Masks let you selectively reveal portions of a component based on another component's pixels. Give one component an `id` property (`string`) and then set `maskSource` to that ID on another component to use it as a mask.

Masks typically have `visible={false}` so they don't appear in the final output-they only control visibility of other components:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <!-- This circle acts as the mask -->
  <Circle id="myMask" radius="0.8" :visible="false" />

  <!-- This gradient is masked by the circle -->
  <LinearGradient maskSource="myMask" />
</Shader>
```

```jsx
<Shader>
  {/* This circle acts as the mask */}
  <Circle id="myMask" radius={0.8} visible={false} />

  {/* This gradient is masked by the circle */}
  <LinearGradient maskSource="myMask" />
</Shader>
```

```tsx
<Shader>
  {/* This circle acts as the mask */}
  <Circle id="myMask" radius={0.8} visible={false} />

  {/* This gradient is masked by the circle */}
  <LinearGradient maskSource="myMask" />
</Shader>
```

```tsx
<Shader>
  {/* This circle acts as the mask */}
  <Circle id="myMask" radius={0.8} visible={false} />

  {/* This gradient is masked by the circle */}
  <LinearGradient maskSource="myMask" />
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  components: [
    { type: 'Circle', id: 'myMask', props: { radius: 0.8, visible: false } },
    { type: 'LinearGradient', props: { maskSource: 'myMask' } }
  ]
})
```
::

::shader-demo
---
preset:
  components:
    - type: Circle
      id: "myMask"
      props:
        radius: 0.8
        visible: false
    - type: LinearGradient
      props:
        maskSource: "myMask"
---
::

Note that layer order doesn't matter for masking, you can render the mask on top or below of the layer that's using it. Most of the time this won't matter anyway, as you'll likely have the mask layer set to `visible={false}`.

### Mask Types

Control how the mask is interpreted with the `maskType` prop:

- **`alpha`**: Uses the mask's alpha channel (default)
- **`alphaInverted`**: Uses the inverted alpha channel
- **`luminance`**: Uses the mask's brightness
- **`luminanceInverted`**: Uses the inverted brightness

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <LinearGradient />

  <!-- Hidden checkerboard mask controls visibility -->
  <Checkerboard
    id="mask"
    colorA="#ffffff"
    colorB="#111111"
    :visible="false"
  />

  <!-- Visible where mask is bright -->
  <Circle
    color="#ff0088"
    radius="0.8"
    maskSource="mask"
    maskType="luminance"
  />
</Shader>
```

```jsx
<Shader>
  <LinearGradient />

  {/* Hidden checkerboard mask controls visibility */}
  <Checkerboard
    id="mask"
    colorA="#ffffff"
    colorB="#111111"
    visible={false}
  />

  {/* Visible where mask is bright */}
  <Circle
    color="#ff0088"
    radius={0.8}
    maskSource="mask"
    maskType="luminance"
  />
</Shader>
```

```tsx
<Shader>
  <LinearGradient />

  {/* Hidden checkerboard mask controls visibility */}
  <Checkerboard
    id="mask"
    colorA="#ffffff"
    colorB="#111111"
    visible={false}
  />

  {/* Visible where mask is bright */}
  <Circle
    color="#ff0088"
    radius={0.8}
    maskSource="mask"
    maskType="luminance"
  />
</Shader>
```

```tsx
<Shader>
  <LinearGradient />

  {/* Hidden checkerboard mask controls visibility */}
  <Checkerboard
    id="mask"
    colorA="#ffffff"
    colorB="#111111"
    visible={false}
  />

  {/* Visible where mask is bright */}
  <Circle
    color="#ff0088"
    radius={0.8}
    maskSource="mask"
    maskType="luminance"
  />
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  components: [
    { type: 'LinearGradient', props: {} },
    { type: 'Checkerboard', id: 'mask', props: { colorA: '#ffffff', colorB: '#111111', visible: false } },
    { type: 'Circle', props: { color: '#ff0088', radius: 0.8, maskSource: 'mask', maskType: 'luminance' } }
  ]
})
```
::

::shader-demo
---
preset:
  components:
    - type: LinearGradient
    - type: Checkerboard
      id: "mask"
      props:
        colorA: "#ffffff"
        colorB: "#111111"
        visible: false
    - type: Circle
      props:
        color: "#ff0088"
        radius: 0.8
        maskSource: "mask"
        maskType: "luminance"
---
::