---
title: Swirl
description: Flowing swirl pattern with multi-layered noise
category: Textures
componentType: Generator
requiresChild: false
---

# Swirl

Flowing swirl pattern with multi-layered noise


::shader-preview{component="Swirl"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#1275d8"
    description: "Primary gradient color"
  - name: "colorB"
    type: "string"
    default: "#e19136"
    description: "Secondary gradient color"
  - name: "speed"
    type: "number"
    default: "1"
    description: "Flow animation speed"
  - name: "detail"
    type: "number"
    default: "1"
    description: "Level of detail and intricacy in the swirl patterns"
  - name: "blend"
    type: "number"
    default: "50"
    description: "Skew color balance toward A (lower values) or B (higher values)"
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
  <Swirl />
</Shader>
```

```jsx
<Shader>
  <Swirl />
</Shader>
```

```svelte
<Shader>
  <Swirl />
</Shader>
```

```tsx
<Shader>
  <Swirl />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Swirl', props: {} }
  ]
})
```
::
