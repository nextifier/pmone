---
title: FlowingGradient
description: Liquid silk gradient with organic flowing color bands
category: Textures
componentType: Generator
requiresChild: false
---

# FlowingGradient

Liquid silk gradient with organic flowing color bands


::shader-preview{component="FlowingGradient"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#0a0015"
    description: "Deep background color"
  - name: "colorB"
    type: "string"
    default: "#6b17e6"
    description: "Primary accent color"
  - name: "colorC"
    type: "string"
    default: "#ff4d6a"
    description: "Secondary accent color"
  - name: "colorD"
    type: "string"
    default: "#ff6b35"
    description: "Tertiary accent color"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "oklch"
    description: "Color space for color interpolation"
  - name: "speed"
    type: "number"
    default: "1"
    description: "Animation speed"
  - name: "distortion"
    type: "number"
    default: "0.5"
    description: "Organic distortion intensity"
  - name: "seed"
    type: "number"
    default: "0"
    description: "Random seed for variation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <FlowingGradient />
</Shader>
```

```jsx
<Shader>
  <FlowingGradient />
</Shader>
```

```svelte
<Shader>
  <FlowingGradient />
</Shader>
```

```tsx
<Shader>
  <FlowingGradient />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'FlowingGradient', props: {} }
  ]
})
```
::
