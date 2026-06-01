---
title: Glass
description: Optically realistic glass lens driven in a custom shape
category: Shape Effects
componentType: Filter/Effect
requiresChild: true
---

# Glass

Optically realistic glass lens driven in a custom shape


::shader-preview{component="Glass"}
::

## Props

::props-table
---
props:
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the glass shape"
  - name: "scale"
    type: "number"
    default: "1"
    description: "Scale of the glass shape (1 = default size)"
  - name: "cutout"
    type: "boolean"
    default: "false"
    description: "Cut out the alpha outside the glass shape"
  - name: "refraction"
    type: "number"
    default: "1"
    description: "Lens refraction - how aggressively the edges warp content beneath (0 = none, 1 = max)"
  - name: "edgeSoftness"
    type: "number"
    default: "0.1"
    description: "Edge softness - higher values give a wider, softer fade at the glass boundary"
  - name: "blur"
    type: "number"
    default: "0"
    description: "Frosted blur amount - 0 = clear glass, higher = frosted/diffuse"
  - name: "thickness"
    type: "number"
    default: "0.2"
    description: "Glass depth - how far inward from the edge the refraction extends"
  - name: "aberration"
    type: "number"
    default: "0.5"
    description: "Chromatic aberration - splits RGB channels along the refraction vector"
  - name: "innerZoom"
    type: "number"
    default: "1"
    description: "Inner zoom level - magnifies content seen through the glass"
  - name: "lightAngle"
    type: "number"
    default: "300"
    description: "Light angle in degrees"
  - name: "highlight"
    type: "number"
    default: "0.05"
    description: "Directional edge highlight - bright rim on the light-facing boundary"
  - name: "highlightColor"
    type: "string"
    default: "#ffffff"
    description: "Color of the directional edge highlight and specular glint"
  - name: "highlightSoftness"
    type: "number"
    default: "0.5"
    description: "Specular highlight softness"
  - name: "fresnel"
    type: "number"
    default: "0.1"
    description: "Fresnel rim glow - a soft luminous halo around the glass boundary"
  - name: "fresnelSoftness"
    type: "number"
    default: "0.1"
    description: "Fresnel rim width - higher values spread the glow further inward"
  - name: "fresnelColor"
    type: "string"
    default: "#ffffff"
    description: "Color of the fresnel rim glow"
  - name: "tintColor"
    type: "string"
    default: "#ffffff"
    description: "Color tint applied to the internal directional gradient"
  - name: "tintIntensity"
    type: "number"
    default: "0"
    description: "Intensity of the color tint applied to the glass interior"
  - name: "tintPreserveLuminosity"
    type: "boolean"
    default: "true"
    description: "Preserve original brightness when tinting"
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
  <Glass>
    <Circle />
  </Glass>
</Shader>
```

```jsx
<Shader>
  <Glass>
    <Circle />
  </Glass>
</Shader>
```

```svelte
<Shader>
  <Glass>
    <Circle />
  </Glass>
</Shader>
```

```tsx
<Shader>
  <Glass>
    <Circle />
  </Glass>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Glass', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
