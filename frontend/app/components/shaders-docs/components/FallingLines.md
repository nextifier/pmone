---
title: FallingLines
description: Directional falling lines with a leading-to-trailing color fade
category: Textures
componentType: Generator
requiresChild: false
---

# FallingLines

Directional falling lines with a leading-to-trailing color fade


::shader-preview{component="FallingLines"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#ffffff"
    description: "Color at the leading edge of each line"
  - name: "colorB"
    type: "string"
    default: "#ffffff00"
    description: "Color at the trailing edge (transparent by default)"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "linear"
    description: "Color space for interpolation between lead and trail colors"
  - name: "angle"
    type: "number"
    default: "90"
    description: "Direction of movement in degrees (90=down, 270=up, 0=right, 180=left)"
  - name: "speed"
    type: "number"
    default: "0.5"
    description: "Movement speed"
  - name: "speedVariance"
    type: "number"
    default: "0.3"
    description: "Per-line speed variance (0=uniform, 1=high variance)"
  - name: "density"
    type: "number"
    default: "15"
    description: "Number of line columns across the canvas"
  - name: "trailLength"
    type: "number"
    default: "0.35"
    description: "Streak length relative to spacing (0=point, 1=continuous)"
  - name: "balance"
    type: "number"
    default: "0.5"
    description: "Color mix midpoint (0.5=linear, 0=all trailing/colorB, 1=all leading/colorA)"
  - name: "strokeWidth"
    type: "number"
    default: "0.15"
    description: "Line thickness as fraction of column width (0=hairline, 1=full width)"
  - name: "rounding"
    type: "number"
    default: "1"
    description: "Rounds the leading edge (0=flat/square, 1=fully rounded cap)"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <FallingLines />
</Shader>
```

```jsx
<Shader>
  <FallingLines />
</Shader>
```

```svelte
<Shader>
  <FallingLines />
</Shader>
```

```tsx
<Shader>
  <FallingLines />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'FallingLines', props: {} }
  ]
})
```
::
