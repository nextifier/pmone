---
title: Star
description: Classic star polygon with straight sides and sharp pointed tips
category: Shapes
componentType: Generator
requiresChild: false
---

# Star

Classic star polygon with straight sides and sharp pointed tips


::shader-preview{component="Star"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ffffff"
    description: "Fill color of the star"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the star"
  - name: "radius"
    type: "number"
    default: "0.4"
    description: "Outer tip radius - distance from center to the pointed tips"
  - name: "sides"
    type: "number"
    default: "5"
    description: "Number of points on the star"
  - name: "innerRatio"
    type: "number"
    default: "0.4"
    description: "Inner vertex radius as a ratio of outer radius (0.382 = golden-ratio 5-star)"
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
  <Star
    color="#ffffff"
    :radius="0.4"
  />
</Shader>
```

```jsx
<Shader>
  <Star
    color="#ffffff"
    radius={0.4}
  />
</Shader>
```

```svelte
<Shader>
  <Star
    color="#ffffff"
    radius={0.4}
  />
</Shader>
```

```tsx
<Shader>
  <Star
    color="#ffffff"
    radius={0.4}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Star', props: { color: '#ffffff', radius: 0.4 } }
  ]
})
```
::
