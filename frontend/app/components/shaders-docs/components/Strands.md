---
title: Strands
description: Procedural wavy strands with layered animation
category: Textures
componentType: Generator
requiresChild: false
---

# Strands

Procedural wavy strands with layered animation


::shader-preview{component="Strands"}
::

## Props

::props-table
---
props:
  - name: "speed"
    type: "number"
    default: "0.5"
    description: "Overall animation speed"
  - name: "amplitude"
    type: "number"
    default: "1"
    description: "Wave height amplitude"
  - name: "frequency"
    type: "number"
    default: "1"
    description: "Wave frequency"
  - name: "lineCount"
    type: "number"
    default: "12"
    description: "Number of wave lines"
  - name: "lineWidth"
    type: "number"
    default: "0.1"
    description: "Width of wave lines"
  - name: "waveColor"
    type: "string"
    default: "#f1c907"
    description: "Color of the waves"
  - name: "pinEdges"
    type: "boolean"
    default: "true"
    description: "Pin waves at edges (fade effect)"
  - name: "start"
    type: "{x: number, y: number}"
    default: "{\"x\":0,\"y\":0.5}"
    description: "Starting point of the waves"
  - name: "end"
    type: "{x: number, y: number}"
    default: "{\"x\":1,\"y\":0.5}"
    description: "Ending point of the waves"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Strands />
</Shader>
```

```jsx
<Shader>
  <Strands />
</Shader>
```

```svelte
<Shader>
  <Strands />
</Shader>
```

```tsx
<Shader>
  <Strands />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Strands', props: {} }
  ]
})
```
::
