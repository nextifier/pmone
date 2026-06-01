---
title: Spiral
description: Rotating spiral pattern with animated movement
category: Textures
componentType: Generator
requiresChild: false
---

# Spiral

Rotating spiral pattern with animated movement


::shader-preview{component="Spiral"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#000000"
    description: "Background color"
  - name: "colorB"
    type: "string"
    default: "#ffffff"
    description: "Spiral stroke color"
  - name: "strokeWidth"
    type: "number"
    default: "0.5"
    description: "Thickness of spiral stroke"
  - name: "strokeFalloff"
    type: "number"
    default: "0"
    description: "Stroke losing width further from center"
  - name: "softness"
    type: "number"
    default: "0"
    description: "Color transition sharpness (0 = hard edge, 1 = smooth fade)"
  - name: "speed"
    type: "number"
    default: "1"
    description: "Animation speed (negative values reverse direction)"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "The center point of the spiral"
  - name: "scale"
    type: "number"
    default: "1"
    description: "Scale factor for spiral bands (higher = more bands, lower = fewer bands)"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "linear"
    description: "Color space for color interpolation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Spiral />
</Shader>
```

```jsx
<Shader>
  <Spiral />
</Shader>
```

```svelte
<Shader>
  <Spiral />
</Shader>
```

```tsx
<Shader>
  <Spiral />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Spiral', props: {} }
  ]
})
```
::
