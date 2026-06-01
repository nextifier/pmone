---
title: SimplexNoise
description: Organic noise with animated movement
category: Textures
componentType: Generator
requiresChild: false
---

# SimplexNoise

Organic noise with animated movement


::shader-preview{component="SimplexNoise"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#ffffff"
    description: "First color"
  - name: "colorB"
    type: "string"
    default: "#000000"
    description: "Second color"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "linear"
    description: "Color space for color interpolation"
  - name: "scale"
    type: "number"
    default: "2"
    description: "Pattern scale (higher = larger patterns)"
  - name: "balance"
    type: "number"
    default: "0"
    description: "Balance between colors (negative = more colorB, positive = more colorA)"
  - name: "contrast"
    type: "number"
    default: "0"
    description: "Pattern contrast (higher = sharper transitions)"
  - name: "seed"
    type: "number"
    default: "0"
    description: "Random seed for pattern variation"
  - name: "speed"
    type: "number"
    default: "1"
    description: "Animation speed"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <SimplexNoise />
</Shader>
```

```jsx
<Shader>
  <SimplexNoise />
</Shader>
```

```svelte
<Shader>
  <SimplexNoise />
</Shader>
```

```tsx
<Shader>
  <SimplexNoise />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'SimplexNoise', props: {} }
  ]
})
```
::
