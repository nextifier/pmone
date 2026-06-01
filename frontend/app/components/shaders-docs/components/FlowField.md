---
title: FlowField
description: Fluid-like distortion with constant smooth motion
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# FlowField

Fluid-like distortion with constant smooth motion


::shader-preview{component="FlowField"}
::

## Props

::props-table
---
props:
  - name: "strength"
    type: "number"
    default: "0.15"
    description: "Intensity of the flow distortion"
  - name: "detail"
    type: "number"
    default: "2"
    description: "Scale of the flow patterns"
  - name: "speed"
    type: "number"
    default: "0"
    description: "Speed of the flow"
  - name: "evolutionSpeed"
    type: "number"
    default: "0"
    description: "How fast the flow field pattern reshapes over time"
  - name: "edges"
    type: "\"stretch\" | \"transparent\" | \"mirror\" | \"wrap\""
    default: "mirror"
    description: "How to handle edges when distortion pushes content out of bounds"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <FlowField>
    <Circle />
  </FlowField>
</Shader>
```

```jsx
<Shader>
  <FlowField>
    <Circle />
  </FlowField>
</Shader>
```

```svelte
<Shader>
  <FlowField>
    <Circle />
  </FlowField>
</Shader>
```

```tsx
<Shader>
  <FlowField>
    <Circle />
  </FlowField>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'FlowField', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
