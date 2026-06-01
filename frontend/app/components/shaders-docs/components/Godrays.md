---
title: Godrays
description: Volumetric light rays emanating from a point
category: Textures
componentType: Generator
requiresChild: false
---

# Godrays

Volumetric light rays emanating from a point


::shader-preview{component="Godrays"}
::

## Props

::props-table
---
props:
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0,\"y\":0}"
    description: "The center point of the god rays"
  - name: "density"
    type: "number"
    default: "0.3"
    description: "Frequency of ray sectors"
  - name: "intensity"
    type: "number"
    default: "0.8"
    description: "Ray visibility within sectors"
  - name: "spotty"
    type: "number"
    default: "1"
    description: "Density of spots on rays (higher = more spots)"
  - name: "speed"
    type: "number"
    default: "0.5"
    description: "Animation speed of the rays"
  - name: "rayColor"
    type: "string"
    default: "#4283fb"
    description: "Color of the light rays"
  - name: "backgroundColor"
    type: "string"
    default: "transparent"
    description: "Background color"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Godrays
    :intensity="0.8"
  />
</Shader>
```

```jsx
<Shader>
  <Godrays
    intensity={0.8}
  />
</Shader>
```

```svelte
<Shader>
  <Godrays
    intensity={0.8}
  />
</Shader>
```

```tsx
<Shader>
  <Godrays
    intensity={0.8}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Godrays', props: { intensity: 0.8 } }
  ]
})
```
::
