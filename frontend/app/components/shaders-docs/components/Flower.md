---
title: Flower
description: Petal shape with N lobes and adjustable inner-to-outer radius ratio
category: Shapes
componentType: Generator
requiresChild: false
---

# Flower

Petal shape with N lobes and adjustable inner-to-outer radius ratio


::shader-preview{component="Flower"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ffffff"
    description: "Fill color of the flower"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the flower"
  - name: "radius"
    type: "number"
    default: "0.4"
    description: "Outer petal tip radius in UV space"
  - name: "sides"
    type: "number"
    default: "5"
    description: "Number of petals"
  - name: "innerRatio"
    type: "number"
    default: "0.4"
    description: "Inner valley radius as a ratio of outer radius - lower values make deeper notches"
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
  <Flower
    color="#ffffff"
    :radius="0.4"
  />
</Shader>
```

```jsx
<Shader>
  <Flower
    color="#ffffff"
    radius={0.4}
  />
</Shader>
```

```svelte
<Shader>
  <Flower
    color="#ffffff"
    radius={0.4}
  />
</Shader>
```

```tsx
<Shader>
  <Flower
    color="#ffffff"
    radius={0.4}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Flower', props: { color: '#ffffff', radius: 0.4 } }
  ]
})
```
::
