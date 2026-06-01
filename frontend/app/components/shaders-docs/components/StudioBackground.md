---
title: StudioBackground
description: Multi-light studio background with ambient motion.
category: Textures
componentType: Generator
requiresChild: false
---

# StudioBackground

Multi-light studio background with ambient motion.


::shader-preview{component="StudioBackground"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#d8dbec"
    description: "Base studio surface color"
  - name: "keyColor"
    type: "string"
    default: "#d5e4ea"
    description: "Color of the overhead key light"
  - name: "keyIntensity"
    type: "number"
    default: "40"
    description: "Intensity of the key light"
  - name: "keySoftness"
    type: "number"
    default: "50"
    description: "How diffuse the key light is"
  - name: "fillColor"
    type: "string"
    default: "#d5e4ea"
    description: "Color of the side fill lights"
  - name: "fillIntensity"
    type: "number"
    default: "10"
    description: "Intensity of the fill lights"
  - name: "fillSoftness"
    type: "number"
    default: "70"
    description: "How diffuse the fill lights are"
  - name: "fillAngle"
    type: "number"
    default: "70"
    description: "How far apart the fill lights are from center"
  - name: "backColor"
    type: "string"
    default: "#c8d4e8"
    description: "Color of the upward back wash"
  - name: "backIntensity"
    type: "number"
    default: "20"
    description: "Intensity of the back wash"
  - name: "backSoftness"
    type: "number"
    default: "80"
    description: "How diffuse the back wash is"
  - name: "brightness"
    type: "number"
    default: "20"
    description: "Overall ambient light level"
  - name: "vignette"
    type: "number"
    default: "0"
    description: "Edge darkening"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.8}"
    description: "Where the spotlight meets the floor"
  - name: "lightTarget"
    type: "number"
    default: "100"
    description: "How far toward the floor vs wall the spotlights aim"
  - name: "wallCurvature"
    type: "number"
    default: "10"
    description: "How rounded the cove is"
  - name: "ambientIntensity"
    type: "number"
    default: "50"
    description: "Intensity of drifting ambient lights"
  - name: "ambientSpeed"
    type: "number"
    default: "2"
    description: "Drift speed"
  - name: "seed"
    type: "number"
    default: "0"
    description: "Seed for ambient pattern"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <StudioBackground
    color="#d8dbec"
  />
</Shader>
```

```jsx
<Shader>
  <StudioBackground
    color="#d8dbec"
  />
</Shader>
```

```svelte
<Shader>
  <StudioBackground
    color="#d8dbec"
  />
</Shader>
```

```tsx
<Shader>
  <StudioBackground
    color="#d8dbec"
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'StudioBackground', props: { color: '#d8dbec' } }
  ]
})
```
::
