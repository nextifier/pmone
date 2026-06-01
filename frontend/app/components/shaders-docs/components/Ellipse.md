---
title: Ellipse
description: Ellipse with independently adjustable horizontal and vertical radii
category: Shapes
componentType: Generator
requiresChild: false
---

# Ellipse

Ellipse with independently adjustable horizontal and vertical radii


::shader-preview{component="Ellipse"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ffffff"
    description: "Fill color of the ellipse"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the ellipse"
  - name: "radiusX"
    type: "number"
    default: "0.35"
    description: "Horizontal semi-axis radius"
  - name: "radiusY"
    type: "number"
    default: "0.2"
    description: "Vertical semi-axis radius"
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
  <Ellipse
    color="#ffffff"
  />
</Shader>
```

```jsx
<Shader>
  <Ellipse
    color="#ffffff"
  />
</Shader>
```

```svelte
<Shader>
  <Ellipse
    color="#ffffff"
  />
</Shader>
```

```tsx
<Shader>
  <Ellipse
    color="#ffffff"
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Ellipse', props: { color: '#ffffff' } }
  ]
})
```
::
