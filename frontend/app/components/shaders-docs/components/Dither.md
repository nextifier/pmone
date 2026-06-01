---
title: Dither
description: Dithering effect with multiple pattern options
category: Stylize
componentType: Filter/Effect
requiresChild: true
---

# Dither

Dithering effect with multiple pattern options


::shader-preview{component="Dither"}
::

## Props

::props-table
---
props:
  - name: "pattern"
    type: "\"bayer2\" | \"bayer4\" | \"bayer8\" | \"clusteredDot\" | \"blueNoise\" | \"whiteNoise\""
    default: "bayer4"
    description: "Dithering pattern algorithm"
  - name: "pixelSize"
    type: "number"
    default: "4"
    description: "Size of dithering pixels"
  - name: "threshold"
    type: "number"
    default: "0.5"
    description: "Luminance threshold for dithering"
  - name: "spread"
    type: "number"
    default: "1"
    description: "How much of the luminance range participates in dithering (lower = more solid areas)"
  - name: "colorMode"
    type: "\"custom\" | \"source\""
    default: "custom"
    description: "How colors are determined"
  - name: "colorA"
    type: "string"
    default: "transparent"
    description: "Dark color for dithering"
  - name: "colorB"
    type: "string"
    default: "#ffffff"
    description: "Light color for dithering"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Dither>
    <Circle />
  </Dither>
</Shader>
```

```jsx
<Shader>
  <Dither>
    <Circle />
  </Dither>
</Shader>
```

```svelte
<Shader>
  <Dither>
    <Circle />
  </Dither>
</Shader>
```

```tsx
<Shader>
  <Dither>
    <Circle />
  </Dither>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Dither', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
