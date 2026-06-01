---
title: Crescent
description: Crescent moon shape - an outer circle with an inner circle subtracted
category: Shapes
componentType: Generator
requiresChild: false
---

# Crescent

Crescent moon shape - an outer circle with an inner circle subtracted


::shader-preview{component="Crescent"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ffffff"
    description: "Fill color of the crescent"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the crescent"
  - name: "radius"
    type: "number"
    default: "0.3"
    description: "Outer circle radius"
  - name: "innerRatio"
    type: "number"
    default: "0.8"
    description: "Inner (bite) circle radius as a fraction of outer radius"
  - name: "offset"
    type: "number"
    default: "0.2"
    description: "Horizontal distance the bite circle is shifted from center"
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
  <Crescent
    color="#ffffff"
    :radius="0.3"
  />
</Shader>
```

```jsx
<Shader>
  <Crescent
    color="#ffffff"
    radius={0.3}
  />
</Shader>
```

```svelte
<Shader>
  <Crescent
    color="#ffffff"
    radius={0.3}
  />
</Shader>
```

```tsx
<Shader>
  <Crescent
    color="#ffffff"
    radius={0.3}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Crescent', props: { color: '#ffffff', radius: 0.3 } }
  ]
})
```
::
