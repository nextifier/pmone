---
title: Polygon
description: Regular polygon with adjustable sides and corner rounding
category: Shapes
componentType: Generator
requiresChild: false
---

# Polygon

Regular polygon with adjustable sides and corner rounding


::shader-preview{component="Polygon"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ffffff"
    description: "Fill color of the polygon"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the polygon"
  - name: "radius"
    type: "number"
    default: "0.4"
    description: "Circumradius - distance from center to vertices in UV space"
  - name: "sides"
    type: "number"
    default: "6"
    description: "Number of sides (3 = triangle, 4 = square, 6 = hexagon, etc.)"
  - name: "rounding"
    type: "number"
    default: "0"
    description: "Corner rounding - 0 is sharp vertices, 1 morphs into a circle"
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
  <Polygon
    color="#ffffff"
    :radius="0.4"
  />
</Shader>
```

```jsx
<Shader>
  <Polygon
    color="#ffffff"
    radius={0.4}
  />
</Shader>
```

```svelte
<Shader>
  <Polygon
    color="#ffffff"
    radius={0.4}
  />
</Shader>
```

```tsx
<Shader>
  <Polygon
    color="#ffffff"
    radius={0.4}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Polygon', props: { color: '#ffffff', radius: 0.4 } }
  ]
})
```
::
