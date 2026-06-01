---
title: Cross
description: Plus / cross shape with adjustable arm length, width, and rounding
category: Shapes
componentType: Generator
requiresChild: false
---

# Cross

Plus / cross shape with adjustable arm length, width, and rounding


::shader-preview{component="Cross"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ffffff"
    description: "Fill color of the cross"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the cross"
  - name: "radius"
    type: "number"
    default: "0.35"
    description: "Arm half-length - distance from center to the end of each arm"
  - name: "thickness"
    type: "number"
    default: "0.08"
    description: "Arm half-width - controls how wide each arm is"
  - name: "rounding"
    type: "number"
    default: "0"
    description: "Corner rounding - rounds the arm ends and concave corners"
  - name: "rotation"
    type: "number"
    default: "0"
    description: "Rotation in degrees (45° turns a plus into an ×)"
  - name: "softness"
    type: "number"
    default: "0"
    description: "Edge softness for antialiasing"
  - name: "strokeThickness"
    type: "number"
    default: "0"
    description: "Stroke thickness. Zero means no stroke."
  - name: "strokeColor"
    type: "string"
    default: "#000000"
    description: "Color of the stroke outline"
  - name: "strokePosition"
    type: "\"outside\" | \"center\" | \"inside\""
    default: "center"
    description: "Position of the stroke relative to the shape edge"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "linear"
    description: "Color space for blending fill and stroke colors"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Cross
    color="#ffffff"
    :radius="0.35"
  />
</Shader>
```

```jsx
<Shader>
  <Cross
    color="#ffffff"
    radius={0.35}
  />
</Shader>
```

```svelte
<Shader>
  <Cross
    color="#ffffff"
    radius={0.35}
  />
</Shader>
```

```tsx
<Shader>
  <Cross
    color="#ffffff"
    radius={0.35}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Cross', props: { color: '#ffffff', radius: 0.35 } }
  ]
})
```
::
