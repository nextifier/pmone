---
title: Circle
description: Generate a circle with adjustable size and softness
category: Shapes
componentType: Generator
requiresChild: false
---

# Circle

Generate a circle with adjustable size and softness


::shader-preview{component="Circle"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ffffff"
    description: "The color of the circle"
  - name: "radius"
    type: "number"
    default: "1"
    description: "The radius of the circle. A value of one (1) is sets the circle to fit the canvas."
  - name: "softness"
    type: "number"
    default: "0"
    description: "Edge softness. Lower values like zero (0) are sharp, higher values like one (1) are softer."
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "The center point of the circle"
  - name: "strokeThickness"
    type: "number"
    default: "0"
    description: "The thickness of the stroke outline. Zero (0) means no stroke."
  - name: "strokeColor"
    type: "string"
    default: "#000000"
    description: "The color of the stroke outline"
  - name: "strokePosition"
    type: "\"outside\" | \"center\" | \"inside\""
    default: "center"
    description: "Position of the stroke relative to the circle edge"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "linear"
    description: "Color space for blending fill and stroke colors in soft edges"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Circle
    color="#ffffff"
    :radius="1"
  />
</Shader>
```

```jsx
<Shader>
  <Circle
    color="#ffffff"
    radius={1}
  />
</Shader>
```

```svelte
<Shader>
  <Circle
    color="#ffffff"
    radius={1}
  />
</Shader>
```

```tsx
<Shader>
  <Circle
    color="#ffffff"
    radius={1}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Circle', props: { color: '#ffffff', radius: 1 } }
  ]
})
```
::
