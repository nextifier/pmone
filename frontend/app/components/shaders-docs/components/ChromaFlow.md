---
title: ChromaFlow
description: Interactive liquid flow effect that follows your cursor
category: Interactive
componentType: Generator
requiresChild: false
---

# ChromaFlow

Interactive liquid flow effect that follows your cursor


::shader-preview{component="ChromaFlow"}
::

## Props

::props-table
---
props:
  - name: "baseColor"
    type: "string"
    default: "#0066ff"
    description: "Base liquid color"
  - name: "upColor"
    type: "string"
    default: "#00ff00"
    description: "Color for upward movement"
  - name: "downColor"
    type: "string"
    default: "#ff0000"
    description: "Color for downward movement"
  - name: "leftColor"
    type: "string"
    default: "#0000ff"
    description: "Color for leftward movement"
  - name: "rightColor"
    type: "string"
    default: "#ffff00"
    description: "Color for rightward movement"
  - name: "intensity"
    type: "number"
    default: "1"
    description: "Strength of the liquid effect"
  - name: "radius"
    type: "number"
    default: "3"
    description: "Radius of the liquid effect"
  - name: "momentum"
    type: "number"
    default: "30"
    description: "How much momentum colors retain in their flow direction"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <ChromaFlow
    :intensity="1"
    :radius="3"
  />
</Shader>
```

```jsx
<Shader>
  <ChromaFlow
    intensity={1}
    radius={3}
  />
</Shader>
```

```svelte
<Shader>
  <ChromaFlow
    intensity={1}
    radius={3}
  />
</Shader>
```

```tsx
<Shader>
  <ChromaFlow
    intensity={1}
    radius={3}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'ChromaFlow', props: { intensity: 1, radius: 3 } }
  ]
})
```
::
