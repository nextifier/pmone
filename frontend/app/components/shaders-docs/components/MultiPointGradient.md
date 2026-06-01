---
title: MultiPointGradient
description: Five individually placed color points blended together by proximity - drag each point to shape the gradient
category: Textures
componentType: Generator
requiresChild: false
---

# MultiPointGradient

Five individually placed color points blended together by proximity - drag each point to shape the gradient


::shader-preview{component="MultiPointGradient"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#4776E6"
    description: "Color of control point A"
  - name: "positionA"
    type: "{x: number, y: number}"
    default: "{\"x\":0.2,\"y\":0.2}"
    description: "Position of control point A"
  - name: "colorB"
    type: "string"
    default: "#C44DFF"
    description: "Color of control point B"
  - name: "positionB"
    type: "{x: number, y: number}"
    default: "{\"x\":0.8,\"y\":0.2}"
    description: "Position of control point B"
  - name: "colorC"
    type: "string"
    default: "#1ABC9C"
    description: "Color of control point C"
  - name: "positionC"
    type: "{x: number, y: number}"
    default: "{\"x\":0.2,\"y\":0.8}"
    description: "Position of control point C"
  - name: "colorD"
    type: "string"
    default: "#F8BBD9"
    description: "Color of control point D"
  - name: "positionD"
    type: "{x: number, y: number}"
    default: "{\"x\":0.8,\"y\":0.8}"
    description: "Position of control point D"
  - name: "colorE"
    type: "string"
    default: "#FF8C42"
    description: "Color of control point E"
  - name: "positionE"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Position of control point E"
  - name: "smoothness"
    type: "number"
    default: "2"
    description: "Controls how smoothly colors blend."
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <MultiPointGradient />
</Shader>
```

```jsx
<Shader>
  <MultiPointGradient />
</Shader>
```

```svelte
<Shader>
  <MultiPointGradient />
</Shader>
```

```tsx
<Shader>
  <MultiPointGradient />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'MultiPointGradient', props: {} }
  ]
})
```
::
