---
title: DiamondGradient
description: Diamond-shaped gradient radiating from a center point using Manhattan distance
category: Textures
componentType: Generator
requiresChild: false
---

# DiamondGradient

Diamond-shaped gradient radiating from a center point using Manhattan distance


::shader-preview{component="DiamondGradient"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#4ffb4a"
    description: "Color at the center of the diamond"
  - name: "colorB"
    type: "string"
    default: "#4f1238"
    description: "Color at the outer edges of the diamond"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center point of the diamond"
  - name: "size"
    type: "number"
    default: "0.7"
    description: "Extent of the gradient - controls how far Color A reaches before transitioning to Color B"
  - name: "rotation"
    type: "number"
    default: "0"
    description: "Rotation in degrees - tilts the diamond into a rhombus"
  - name: "repeat"
    type: "number"
    default: "1"
    description: "Number of times the gradient repeats outward. Values above 1 create concentric diamond or square bands."
  - name: "roundness"
    type: "number"
    default: "0"
    description: "Morphs from a sharp diamond (0) to a square (1)"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "oklch"
    description: "Color space for color interpolation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <DiamondGradient />
</Shader>
```

```jsx
<Shader>
  <DiamondGradient />
</Shader>
```

```svelte
<Shader>
  <DiamondGradient />
</Shader>
```

```tsx
<Shader>
  <DiamondGradient />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'DiamondGradient', props: {} }
  ]
})
```
::
