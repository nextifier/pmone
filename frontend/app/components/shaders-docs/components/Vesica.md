---
title: Vesica
description: Vesica piscis (lens shape) formed by the intersection of two overlapping circles
category: Shapes
componentType: Generator
requiresChild: false
---

# Vesica

Vesica piscis (lens shape) formed by the intersection of two overlapping circles


::shader-preview{component="Vesica"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ffffff"
    description: "Fill color of the vesica"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the vesica"
  - name: "radius"
    type: "number"
    default: "0.35"
    description: "Radius of the two overlapping circles"
  - name: "spread"
    type: "number"
    default: "0.5"
    description: "Circle separation - 0 = full circle overlap, 1 = infinitely thin lens"
  - name: "rotation"
    type: "number"
    default: "0"
    description: "Rotation in degrees"
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
  <Vesica
    color="#ffffff"
    :radius="0.35"
  />
</Shader>
```

```jsx
<Shader>
  <Vesica
    color="#ffffff"
    radius={0.35}
  />
</Shader>
```

```svelte
<Shader>
  <Vesica
    color="#ffffff"
    radius={0.35}
  />
</Shader>
```

```tsx
<Shader>
  <Vesica
    color="#ffffff"
    radius={0.35}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Vesica', props: { color: '#ffffff', radius: 0.35 } }
  ]
})
```
::
