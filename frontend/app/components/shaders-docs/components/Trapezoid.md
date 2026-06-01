---
title: Trapezoid
description: Trapezoid with adjustable top and bottom widths and height
category: Shapes
componentType: Generator
requiresChild: false
---

# Trapezoid

Trapezoid with adjustable top and bottom widths and height


::shader-preview{component="Trapezoid"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ffffff"
    description: "Fill color of the trapezoid"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the trapezoid"
  - name: "bottomWidth"
    type: "number"
    default: "0.35"
    description: "Half-width of the bottom edge"
  - name: "topWidth"
    type: "number"
    default: "0.2"
    description: "Half-width of the top edge"
  - name: "height"
    type: "number"
    default: "0.25"
    description: "Half-height of the trapezoid"
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
  <Trapezoid
    color="#ffffff"
  />
</Shader>
```

```jsx
<Shader>
  <Trapezoid
    color="#ffffff"
  />
</Shader>
```

```svelte
<Shader>
  <Trapezoid
    color="#ffffff"
  />
</Shader>
```

```tsx
<Shader>
  <Trapezoid
    color="#ffffff"
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Trapezoid', props: { color: '#ffffff' } }
  ]
})
```
::
