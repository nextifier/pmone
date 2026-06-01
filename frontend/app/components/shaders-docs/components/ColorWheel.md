---
title: ColorWheel
description: A directional gradient that smoothly cycles through rainbow colors or a custom set of three colors
category: Textures
componentType: Generator
requiresChild: false
---

# ColorWheel

A directional gradient that smoothly cycles through rainbow colors or a custom set of three colors


::shader-preview{component="ColorWheel"}
::

## Props

::props-table
---
props:
  - name: "mode"
    type: "\"rainbow\" | \"custom\""
    default: "rainbow"
    description: "Rainbow cycles through the full spectrum; Custom loops through your three chosen colors"
  - name: "colorA"
    type: "string"
    default: "#ff0000"
    description: "First color in the cycle"
  - name: "colorB"
    type: "string"
    default: "#00ff88"
    description: "Second color in the cycle"
  - name: "colorC"
    type: "string"
    default: "#0066ff"
    description: "Third color in the cycle"
  - name: "scale"
    type: "number"
    default: "1"
    description: "Number of color cycles across the viewport"
  - name: "angle"
    type: "number"
    default: "0"
    description: "Direction the gradient flows"
  - name: "speed"
    type: "number"
    default: "0.05"
    description: "Speed at which the gradient cycles"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "oklch"
    description: "Color space for blending between custom colors"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <ColorWheel />
</Shader>
```

```jsx
<Shader>
  <ColorWheel />
</Shader>
```

```svelte
<Shader>
  <ColorWheel />
</Shader>
```

```tsx
<Shader>
  <ColorWheel />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'ColorWheel', props: {} }
  ]
})
```
::
