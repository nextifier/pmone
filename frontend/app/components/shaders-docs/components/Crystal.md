---
title: Crystal
description: Diamond-like crystal lens with faceted refraction.
category: Shape Effects
componentType: Filter/Effect
requiresChild: true
---

# Crystal

Diamond-like crystal lens with faceted refraction.


::shader-preview{component="Crystal"}
::

## Props

::props-table
---
props:
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the crystal shape"
  - name: "scale"
    type: "number"
    default: "1"
    description: "Scale of the crystal shape (1 = default size)"
  - name: "cutout"
    type: "boolean"
    default: "false"
    description: "Cut out alpha outside the crystal shape"
  - name: "refraction"
    type: "number"
    default: "0.5"
    description: "How strongly the crystal refracts content beneath"
  - name: "dispersion"
    type: "number"
    default: "0.5"
    description: "Prismatic rainbow dispersion - splits light into spectral colors"
  - name: "facets"
    type: "number"
    default: "5"
    description: "Symmetry order - how many times the facet pattern repeats around the center"
  - name: "fresnel"
    type: "number"
    default: "0.05"
    description: "Fresnel rim glow intensity around the crystal boundary"
  - name: "fresnelSoftness"
    type: "number"
    default: "1"
    description: "Fresnel rim width - higher values spread the glow further inward"
  - name: "fresnelColor"
    type: "string"
    default: "#ffffff"
    description: "Color of the fresnel rim glow"
  - name: "edgeSoftness"
    type: "number"
    default: "0"
    description: "Softness of the crystal boundary edge"
  - name: "innerZoom"
    type: "number"
    default: "1.5"
    description: "Magnification of content seen through the crystal"
  - name: "lightAngle"
    type: "number"
    default: "270"
    description: "Light direction angle in degrees"
  - name: "highlights"
    type: "number"
    default: "0.5"
    description: "Additive brightness on light-facing facets - never darkens"
  - name: "shadows"
    type: "number"
    default: "0.3"
    description: "Darkening on shadow-facing facets - never brightens"
  - name: "brightness"
    type: "number"
    default: "1.2"
    description: "Overall crystal brightness - higher values push facets toward brilliant white"
  - name: "tintColor"
    type: "string"
    default: "#e8e0ff"
    description: "Crystal body tint color"
  - name: "tintIntensity"
    type: "number"
    default: "0"
    description: "How much tint color is applied to the crystal interior"
  - name: "tintPreserveLuminosity"
    type: "boolean"
    default: "true"
    description: "Preserve original brightness when tinting"
  - name: "shape"
    type: "ShapeConfig"
    default: "polygonSDF"
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
  <Crystal>
    <Circle />
  </Crystal>
</Shader>
```

```jsx
<Shader>
  <Crystal>
    <Circle />
  </Crystal>
</Shader>
```

```svelte
<Shader>
  <Crystal>
    <Circle />
  </Crystal>
</Shader>
```

```tsx
<Shader>
  <Crystal>
    <Circle />
  </Crystal>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Crystal', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
