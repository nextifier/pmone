---
title: CRTScreen
description: Retro CRT monitor simulation with scanlines
category: Stylize
componentType: Filter/Effect
requiresChild: true
---

# CRTScreen

Retro CRT monitor simulation with scanlines


::shader-preview{component="CRTScreen"}
::

## Props

::props-table
---
props:
  - name: "pixelSize"
    type: "number"
    default: "128"
    description: "Size of individual TV pixels (lower = more pixels)"
  - name: "colorShift"
    type: "number"
    default: "1"
    description: "Chromatic aberration amount"
  - name: "scanlineIntensity"
    type: "number"
    default: "0.3"
    description: "Strength of horizontal scanlines"
  - name: "scanlineFrequency"
    type: "number"
    default: "200"
    description: "Number of scanlines across screen"
  - name: "brightness"
    type: "number"
    default: "1"
    description: "Screen brightness boost"
  - name: "contrast"
    type: "number"
    default: "1"
    description: "Screen contrast enhancement"
  - name: "vignetteIntensity"
    type: "number"
    default: "1"
    description: "Strength of corner darkening effect (0 = off)"
  - name: "vignetteRadius"
    type: "number"
    default: "0.5"
    description: "How far the vignette extends inward (0 = edges only, 1 = reaches center)"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <CRTScreen>
    <Circle />
  </CRTScreen>
</Shader>
```

```jsx
<Shader>
  <CRTScreen>
    <Circle />
  </CRTScreen>
</Shader>
```

```svelte
<Shader>
  <CRTScreen>
    <Circle />
  </CRTScreen>
</Shader>
```

```tsx
<Shader>
  <CRTScreen>
    <Circle />
  </CRTScreen>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'CRTScreen', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
