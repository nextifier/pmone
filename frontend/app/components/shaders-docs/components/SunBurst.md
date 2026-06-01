---
title: SunBurst
description: Radial sunburst rays emanating from a center point
category: Textures
componentType: Generator
requiresChild: false
---

# SunBurst

Radial sunburst rays emanating from a center point


::shader-preview{component="SunBurst"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ffdd88"
    description: "Ray color"
  - name: "background"
    type: "string"
    default: "#000000"
    description: "Background color"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center point of the sunburst"
  - name: "rayCount"
    type: "number"
    default: "12"
    description: "Number of rays"
  - name: "softness"
    type: "number"
    default: "0.3"
    description: "Softness of ray edges"
  - name: "radius"
    type: "number"
    default: "0.8"
    description: "How far the rays extend from the center"
  - name: "feather"
    type: "number"
    default: "0.5"
    description: "How gradually the rays fade at their outer edge"
  - name: "speed"
    type: "number"
    default: "0.2"
    description: "Rotation speed - positive values rotate clockwise"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <SunBurst
    color="#ffdd88"
    :radius="0.8"
  />
</Shader>
```

```jsx
<Shader>
  <SunBurst
    color="#ffdd88"
    radius={0.8}
  />
</Shader>
```

```svelte
<Shader>
  <SunBurst
    color="#ffdd88"
    radius={0.8}
  />
</Shader>
```

```tsx
<Shader>
  <SunBurst
    color="#ffdd88"
    radius={0.8}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'SunBurst', props: { color: '#ffdd88', radius: 0.8 } }
  ]
})
```
::
