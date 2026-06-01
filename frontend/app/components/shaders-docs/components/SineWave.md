---
title: SineWave
description: Animated wave with thickness and softness
category: Textures
componentType: Generator
requiresChild: false
---

# SineWave

Animated wave with thickness and softness


::shader-preview{component="SineWave"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ffffff"
    description: "The color of the sine wave"
  - name: "amplitude"
    type: "number"
    default: "0.15"
    description: "The height/amplitude of the sine wave"
  - name: "frequency"
    type: "number"
    default: "1"
    description: "The frequency/number of wave cycles"
  - name: "speed"
    type: "number"
    default: "1"
    description: "The animation speed of the wave"
  - name: "angle"
    type: "number"
    default: "0"
    description: "The rotation angle of the wave (in degrees)"
  - name: "position"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "The center position of the wave"
  - name: "thickness"
    type: "number"
    default: "0.2"
    description: "The thickness of the wave line"
  - name: "softness"
    type: "number"
    default: "0.4"
    description: "Edge softness of the wave line"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <SineWave
    color="#ffffff"
  />
</Shader>
```

```jsx
<Shader>
  <SineWave
    color="#ffffff"
  />
</Shader>
```

```svelte
<Shader>
  <SineWave
    color="#ffffff"
  />
</Shader>
```

```tsx
<Shader>
  <SineWave
    color="#ffffff"
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'SineWave', props: { color: '#ffffff' } }
  ]
})
```
::
