---
title: Plasma
description: Animated effect of glowing plasma
category: Textures
componentType: Generator
requiresChild: false
---

# Plasma

Animated effect of glowing plasma


::shader-preview{component="Plasma"}
::

## Props

::props-table
---
props:
  - name: "density"
    type: "number"
    default: "2"
    description: "Density of the plasma pattern"
  - name: "speed"
    type: "number"
    default: "2"
    description: "Animation speed"
  - name: "intensity"
    type: "number"
    default: "1.5"
    description: "Brightness and spread of the plasma glow"
  - name: "warp"
    type: "number"
    default: "0.4"
    description: "How much the flow distorts and swirls"
  - name: "contrast"
    type: "number"
    default: "1"
    description: "Push darks darker and lights lighter"
  - name: "balance"
    type: "number"
    default: "50"
    description: "Skew color balance toward A (higher) or B (lower)"
  - name: "colorA"
    type: "string"
    default: "#7018be"
    description: "Primary color"
  - name: "colorB"
    type: "string"
    default: "#000000"
    description: "Secondary color"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "linear"
    description: "Color space for color interpolation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Plasma
    :intensity="1.5"
  />
</Shader>
```

```jsx
<Shader>
  <Plasma
    intensity={1.5}
  />
</Shader>
```

```svelte
<Shader>
  <Plasma
    intensity={1.5}
  />
</Shader>
```

```tsx
<Shader>
  <Plasma
    intensity={1.5}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Plasma', props: { intensity: 1.5 } }
  ]
})
```
::
