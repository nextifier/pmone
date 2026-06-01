---
title: Neon
description: Photorealistic neon tube / 3D pipe effect driven by a custom shape
category: Shape Effects
componentType: Generator
requiresChild: false
---

# Neon

Photorealistic neon tube / 3D pipe effect driven by a custom shape


::shader-preview{component="Neon"}
::

## Props

::props-table
---
props:
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the neon shape"
  - name: "scale"
    type: "number"
    default: "1"
    description: "Scale of the neon shape (1 = default size)"
  - name: "color"
    type: "string"
    default: "#00ddff"
    description: "Primary neon tube color"
  - name: "secondaryColor"
    type: "string"
    default: "#ff00aa"
    description: "Shadow-side color for a two-tone / dual-lit pipe look"
  - name: "secondaryBlend"
    type: "number"
    default: "0.5"
    description: "Blend between mono (0) and two-tone (1) tube coloring"
  - name: "glowColor"
    type: "string"
    default: "#00ddff"
    description: "Color of the outer glow / bloom"
  - name: "tubeThickness"
    type: "number"
    default: "0.2"
    description: "How far inward from the boundary the tube extends. Low = thin neon outline, high = thick 3D pipe"
  - name: "intensity"
    type: "number"
    default: "1.5"
    description: "Overall brightness multiplier"
  - name: "hotCoreIntensity"
    type: "number"
    default: "0.6"
    description: "Bright white-hot center line - the gas discharge glow inside the tube"
  - name: "glowIntensity"
    type: "number"
    default: "0.6"
    description: "Outer glow / bloom strength"
  - name: "glowRadius"
    type: "number"
    default: "0.25"
    description: "How far the glow extends beyond the tube"
  - name: "lightAngle"
    type: "number"
    default: "300"
    description: "Directional light angle in degrees - controls 3D shading on the tube"
  - name: "specularIntensity"
    type: "number"
    default: "0.5"
    description: "Specular highlight brightness on the tube surface"
  - name: "specularSize"
    type: "number"
    default: "0.5"
    description: "Specular highlight size - 0 = tight pinpoint, 1 = broad sheen"
  - name: "cornerSmoothing"
    type: "number"
    default: "0.15"
    description: "Rounds sharp corners to mimic how real glass tubes curve at bends"
  - name: "flickerSpeed"
    type: "number"
    default: "0"
    description: "Flicker animation speed - 0 = off, higher = faster sporadic on/off"
  - name: "flickerAmount"
    type: "number"
    default: "0.2"
    description: "How often the neon flickers off - 0 = always on, 1 = frequent outages"
  - name: "flowSpeed"
    type: "number"
    default: "0"
    description: "Flow animation speed - 0 = off, light rotates through the tube"
  - name: "flowAmount"
    type: "number"
    default: "0.3"
    description: "Strength of the flowing brightness variation - 0 = uniform, 1 = dramatic"
  - name: "shape"
    type: "ShapeConfig"
    default: "circleSDF"
    description: "Shape to render - choose from 11 built-in analytical shapes or supply a custom SDF. See the [Shape Effects guide](/docs/guide/shape-effects) for all available shapes and their options."
  - name: "shapeSdfUrl"
    type: "string"
    default: "\"\""
    description: "URL to a pre-generated SDF `.bin` file - when non-empty, activates SVG mode and triggers a shader recompile. See the [Shape Effects guide](/docs/guide/shape-effects) for how to generate an SDF from an SVG."
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Neon
    color="#00ddff"
    :intensity="1.5"
  />
</Shader>
```

```jsx
<Shader>
  <Neon
    color="#00ddff"
    intensity={1.5}
  />
</Shader>
```

```svelte
<Shader>
  <Neon
    color="#00ddff"
    intensity={1.5}
  />
</Shader>
```

```tsx
<Shader>
  <Neon
    color="#00ddff"
    intensity={1.5}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Neon', props: { color: '#00ddff', intensity: 1.5 } }
  ]
})
```
::
