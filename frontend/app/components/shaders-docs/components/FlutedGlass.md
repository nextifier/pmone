---
title: FlutedGlass
description: Full-screen fluted glass effect - refracts content through repeating cylindrical bars
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# FlutedGlass

Full-screen fluted glass effect - refracts content through repeating cylindrical bars


::shader-preview{component="FlutedGlass"}
::

## Props

::props-table
---
props:
  - name: "shape"
    type: "\"bars\" | \"rounded\" | \"waves\""
    default: "bars"
    description: "Cross-section shape of each flute"
  - name: "angle"
    type: "number"
    default: "0"
    description: "Direction of the flutes in degrees (0 = vertical bars)"
  - name: "frequency"
    type: "number"
    default: "10"
    description: "Number of flutes across the longest viewport axis"
  - name: "softness"
    type: "number"
    default: "0.5"
    description: "How smoothly distortion fades from each flute centre to its edge (0 = flat middle / sharp seams, 1 = gentle curve)"
  - name: "waveAmplitude"
    type: "number"
    default: "0.06"
    description: "How far each flute sways horizontally as it travels (Waves shape only)"
  - name: "waveFrequency"
    type: "number"
    default: "1.5"
    description: "How many sways fit along each flute (Waves shape only)"
  - name: "speed"
    type: "number"
    default: "0"
    description: "Animation speed - drifts the flute pattern over time and flows wave perturbations"
  - name: "refraction"
    type: "number"
    default: "1.5"
    description: "How aggressively each flute bends content beneath it"
  - name: "aberration"
    type: "number"
    default: "0.2"
    description: "Chromatic aberration - splits RGB along the refraction direction at flute seams"
  - name: "lightAngle"
    type: "number"
    default: "30"
    description: "Direction the light source is coming from (0 = head-on, 90 = grazing)"
  - name: "highlight"
    type: "number"
    default: "0.2"
    description: "Strength of the specular reflection on each flute"
  - name: "highlightSoftness"
    type: "number"
    default: "0.3"
    description: "Spread of the specular peak (0 = pin-tight, 1 = broad sheen)"
  - name: "highlightColor"
    type: "string"
    default: "#ffffff"
    description: "Color of the specular highlight"
  - name: "edges"
    type: "\"stretch\" | \"transparent\" | \"mirror\" | \"wrap\""
    default: "mirror"
    description: "How to handle edges when distortion samples beyond the canvas"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <FlutedGlass>
    <Circle />
  </FlutedGlass>
</Shader>
```

```jsx
<Shader>
  <FlutedGlass>
    <Circle />
  </FlutedGlass>
</Shader>
```

```svelte
<Shader>
  <FlutedGlass>
    <Circle />
  </FlutedGlass>
</Shader>
```

```tsx
<Shader>
  <FlutedGlass>
    <Circle />
  </FlutedGlass>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'FlutedGlass', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
