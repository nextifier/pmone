---
title: Emboss
description: Embossed / debossed relief shading on top of child content, driven by a custom shape
category: Shape Effects
componentType: Filter/Effect
requiresChild: true
---

# Emboss

Embossed / debossed relief shading on top of child content, driven by a custom shape


::shader-preview{component="Emboss"}
::

## Props

::props-table
---
props:
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the embossed shape"
  - name: "scale"
    type: "number"
    default: "1"
    description: "Scale of the embossed shape (1 = default size)"
  - name: "depth"
    type: "number"
    default: "-0.5"
    description: "Relief depth - negative = inset (debossed), positive = raised (embossed)"
  - name: "lightAngle"
    type: "number"
    default: "260"
    description: "Directional light angle in degrees - controls highlight and shadow direction"
  - name: "lightIntensity"
    type: "number"
    default: "0.6"
    description: "Strength of the directional edge highlights and shadows"
  - name: "shadowIntensity"
    type: "number"
    default: "0.3"
    description: "Darkness of the relief shadow"
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
  <Emboss>
    <Circle />
  </Emboss>
</Shader>
```

```jsx
<Shader>
  <Emboss>
    <Circle />
  </Emboss>
</Shader>
```

```svelte
<Shader>
  <Emboss>
    <Circle />
  </Emboss>
</Shader>
```

```tsx
<Shader>
  <Emboss>
    <Circle />
  </Emboss>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Emboss', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
