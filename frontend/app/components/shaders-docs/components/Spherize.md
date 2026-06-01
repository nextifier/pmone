---
title: Spherize
description: Map content onto a 3D sphere surface with depth distortion
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# Spherize

Map content onto a 3D sphere surface with depth distortion


::shader-preview{component="Spherize"}
::

## Props

::props-table
---
props:
  - name: "radius"
    type: "number"
    default: "1"
    description: "Radius of the sphere (1 = half viewport height)"
  - name: "depth"
    type: "number"
    default: "1"
    description: "How much the sphere bulges toward viewer (0 = flat, higher = more bulge)"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "The center point of the sphere"
  - name: "lightPosition"
    type: "{x: number, y: number}"
    default: "{\"x\":0.3,\"y\":0.3}"
    description: "Position of the specular light source"
  - name: "lightIntensity"
    type: "number"
    default: "0.5"
    description: "Intensity of the rim light (0 = off)"
  - name: "lightSoftness"
    type: "number"
    default: "0.5"
    description: "Softness of the rim light falloff (0 = hard edge, 1 = soft glow)"
  - name: "lightColor"
    type: "string"
    default: "#ffffff"
    description: "Color of the specular highlight"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Spherize
    :radius="1"
  >
    <Circle />
  </Spherize>
</Shader>
```

```jsx
<Shader>
  <Spherize
    radius={1}
  >
    <Circle />
  </Spherize>
</Shader>
```

```svelte
<Shader>
  <Spherize
    radius={1}
  >
    <Circle />
  </Spherize>
</Shader>
```

```tsx
<Shader>
  <Spherize
    radius={1}
  >
    <Circle />
  </Spherize>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Spherize', props: { radius: 1 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
