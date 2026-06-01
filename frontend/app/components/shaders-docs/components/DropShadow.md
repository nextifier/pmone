---
title: DropShadow
description: Adds a soft shadow behind the child content based on its alpha silhouette
category: Stylize
componentType: Filter/Effect
requiresChild: true
---

# DropShadow

Adds a soft shadow behind the child content based on its alpha silhouette


::shader-preview{component="DropShadow"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#000000"
    description: "Shadow color"
  - name: "distance"
    type: "number"
    default: "0.1"
    description: "How far the shadow is offset from the content"
  - name: "angle"
    type: "number"
    default: "135"
    description: "Direction the shadow is cast (compass degrees: 0=up, 90=right, 135=lower-right, 180=down)"
  - name: "blur"
    type: "number"
    default: "5"
    description: "Shadow softness (blur radius in pixels)"
  - name: "intensity"
    type: "number"
    default: "0.5"
    description: "Shadow intensity - how strong/visible the shadow is"
  - name: "cutout"
    type: "boolean"
    default: "false"
    description: "Hide the original layer and show only the shadow"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <DropShadow
    color="#000000"
    :intensity="0.5"
  >
    <Circle />
  </DropShadow>
</Shader>
```

```jsx
<Shader>
  <DropShadow
    color="#000000"
    intensity={0.5}
  >
    <Circle />
  </DropShadow>
</Shader>
```

```svelte
<Shader>
  <DropShadow
    color="#000000"
    intensity={0.5}
  >
    <Circle />
  </DropShadow>
</Shader>
```

```tsx
<Shader>
  <DropShadow
    color="#000000"
    intensity={0.5}
  >
    <Circle />
  </DropShadow>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'DropShadow', props: { color: '#000000', intensity: 0.5 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
