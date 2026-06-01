---
title: RoundedRect
description: Rounded rectangle with adjustable width, height, and corner rounding
category: Shapes
componentType: Generator
requiresChild: false
---

# RoundedRect

Rounded rectangle with adjustable width, height, and corner rounding


::shader-preview{component="RoundedRect"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ffffff"
    description: "Fill color of the rectangle"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the rectangle"
  - name: "width"
    type: "number"
    default: "0.35"
    description: "Half-width of the rectangle"
  - name: "height"
    type: "number"
    default: "0.25"
    description: "Half-height of the rectangle"
  - name: "rounding"
    type: "number"
    default: "0.05"
    description: "Corner rounding radius - set to min(width, height) for a pill shape"
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
  <RoundedRect
    color="#ffffff"
  />
</Shader>
```

```jsx
<Shader>
  <RoundedRect
    color="#ffffff"
  />
</Shader>
```

```svelte
<Shader>
  <RoundedRect
    color="#ffffff"
  />
</Shader>
```

```tsx
<Shader>
  <RoundedRect
    color="#ffffff"
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'RoundedRect', props: { color: '#ffffff' } }
  ]
})
```
::
