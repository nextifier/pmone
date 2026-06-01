---
title: Glitch
description: Digital glitch that melts pixels and distorts colors
category: Stylize
componentType: Filter/Effect
requiresChild: true
---

# Glitch

Digital glitch that melts pixels and distorts colors


::shader-preview{component="Glitch"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "0.5"
    description: "Overall glitch strength and frequency of glitch bursts"
  - name: "speed"
    type: "number"
    default: "1"
    description: "How fast the glitch pattern evolves"
  - name: "rgbShift"
    type: "number"
    default: "5"
    description: "Amount of chromatic aberration (RGB channel splitting)"
  - name: "blockDensity"
    type: "number"
    default: "10"
    description: "Base number of horizontal glitch bands"
  - name: "colorBarIntensity"
    type: "number"
    default: "0.2"
    description: "Intensity of vivid neon color bar overlay in glitch regions"
  - name: "mirrorAmount"
    type: "number"
    default: "0.3"
    description: "Chance of glitch blocks showing mirrored/flipped content"
  - name: "scanlineIntensity"
    type: "number"
    default: "0.2"
    description: "Visibility of CRT-style horizontal scanlines in distorted areas"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Glitch
    :intensity="0.5"
  >
    <Circle />
  </Glitch>
</Shader>
```

```jsx
<Shader>
  <Glitch
    intensity={0.5}
  >
    <Circle />
  </Glitch>
</Shader>
```

```svelte
<Shader>
  <Glitch
    intensity={0.5}
  >
    <Circle />
  </Glitch>
</Shader>
```

```tsx
<Shader>
  <Glitch
    intensity={0.5}
  >
    <Circle />
  </Glitch>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Glitch', props: { intensity: 0.5 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
