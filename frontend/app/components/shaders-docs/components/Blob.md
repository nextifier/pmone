---
title: Blob
description: Organic animated blob with 3D lighting and gradients
category: Textures
componentType: Generator
requiresChild: false
---

# Blob

Organic animated blob with 3D lighting and gradients


::shader-preview{component="Blob"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#ff6b35"
    description: "Primary color of the blob"
  - name: "colorB"
    type: "string"
    default: "#e91e63"
    description: "Secondary color of the blob"
  - name: "size"
    type: "number"
    default: "0.5"
    description: "Size of the blob"
  - name: "deformation"
    type: "number"
    default: "0.5"
    description: "How organic and blobby the shape is (0 = circle, 1 = very blobby)"
  - name: "softness"
    type: "number"
    default: "0.5"
    description: "Softness of the blob edges (combines edge width and transition curve)"
  - name: "highlightIntensity"
    type: "number"
    default: "0.5"
    description: "Intensity of specular highlight effect"
  - name: "highlightX"
    type: "number"
    default: "0.3"
    description: "Light direction X component"
  - name: "highlightY"
    type: "number"
    default: "-0.3"
    description: "Light direction Y component"
  - name: "highlightZ"
    type: "number"
    default: "0.4"
    description: "Light direction Z component"
  - name: "highlightColor"
    type: "string"
    default: "#ffe11a"
    description: "Color of the specular highlight"
  - name: "speed"
    type: "number"
    default: "0.5"
    description: "Animation speed"
  - name: "seed"
    type: "number"
    default: "1"
    description: "Adjusts the starting state, useful for variation"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "The center point of the blob"
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
  <Blob />
</Shader>
```

```jsx
<Shader>
  <Blob />
</Shader>
```

```svelte
<Shader>
  <Blob />
</Shader>
```

```tsx
<Shader>
  <Blob />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Blob', props: {} }
  ]
})
```
::
