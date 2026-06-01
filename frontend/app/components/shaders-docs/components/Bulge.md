---
title: Bulge
description: Magnify or pinch content around a center point
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# Bulge

Magnify or pinch content around a center point


::shader-preview{component="Bulge"}
::

## Props

::props-table
---
props:
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "The center point of the bulge effect"
  - name: "strength"
    type: "number"
    default: "1"
    description: "The intensity of the bulge effect (positive = bulge out, negative = pinch in)"
  - name: "radius"
    type: "number"
    default: "1"
    description: "The radius of the bulge effect area"
  - name: "falloff"
    type: "number"
    default: "0.5"
    description: "Controls the smoothness of the transition (0 = hard edge, 1 = very smooth)"
  - name: "edges"
    type: "\"stretch\" | \"transparent\" | \"mirror\" | \"wrap\""
    default: "stretch"
    description: "How to handle edges when distortion pushes content out of bounds"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Bulge
    :radius="1"
  >
    <Circle />
  </Bulge>
</Shader>
```

```jsx
<Shader>
  <Bulge
    radius={1}
  >
    <Circle />
  </Bulge>
</Shader>
```

```svelte
<Shader>
  <Bulge
    radius={1}
  >
    <Circle />
  </Bulge>
</Shader>
```

```tsx
<Shader>
  <Bulge
    radius={1}
  >
    <Circle />
  </Bulge>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Bulge', props: { radius: 1 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
