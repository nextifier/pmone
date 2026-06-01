---
title: Beam
description: A beam of light from one point to another.
category: Textures
componentType: Generator
requiresChild: false
---

# Beam

A beam of light from one point to another.


::shader-preview{component="Beam"}
::

## Props

::props-table
---
props:
  - name: "startPosition"
    type: "{x: number, y: number}"
    default: "{\"x\":0.2,\"y\":0.5}"
    description: "Starting point of the beam"
  - name: "endPosition"
    type: "{x: number, y: number}"
    default: "{\"x\":0.8,\"y\":0.5}"
    description: "Ending point of the beam"
  - name: "startThickness"
    type: "number"
    default: "0.2"
    description: "Thickness at the start of the beam"
  - name: "endThickness"
    type: "number"
    default: "0.2"
    description: "Thickness at the end of the beam"
  - name: "startSoftness"
    type: "number"
    default: "0.5"
    description: "Edge softness at the start of the beam"
  - name: "endSoftness"
    type: "number"
    default: "0.5"
    description: "Edge softness at the end of the beam"
  - name: "insideColor"
    type: "string"
    default: "#FF0000"
    description: "Color at the center of the beam"
  - name: "outsideColor"
    type: "string"
    default: "#0000FF"
    description: "Color at the edges of the beam"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "linear"
    description: "Color space for color interpolation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Beam />
</Shader>
```

```jsx
<Shader>
  <Beam />
</Shader>
```

```svelte
<Shader>
  <Beam />
</Shader>
```

```tsx
<Shader>
  <Beam />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Beam', props: {} }
  ]
})
```
::
