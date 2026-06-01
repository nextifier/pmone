---
title: ChromaticAberration
description: Separate RGB channels for a prismatic distortion effect
category: Stylize
componentType: Filter/Effect
requiresChild: true
---

# ChromaticAberration

Separate RGB channels for a prismatic distortion effect


::shader-preview{component="ChromaticAberration"}
::

## Props

::props-table
---
props:
  - name: "strength"
    type: "number"
    default: "0.2"
    description: "Overall strength of the chromatic aberration effect"
  - name: "angle"
    type: "number"
    default: "0"
    description: "Direction of the chromatic aberration in degrees"
  - name: "redOffset"
    type: "number"
    default: "-1"
    description: "Red channel offset multiplier"
  - name: "greenOffset"
    type: "number"
    default: "0"
    description: "Green channel offset multiplier"
  - name: "blueOffset"
    type: "number"
    default: "1"
    description: "Blue channel offset multiplier"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <ChromaticAberration>
    <Circle />
  </ChromaticAberration>
</Shader>
```

```jsx
<Shader>
  <ChromaticAberration>
    <Circle />
  </ChromaticAberration>
</Shader>
```

```svelte
<Shader>
  <ChromaticAberration>
    <Circle />
  </ChromaticAberration>
</Shader>
```

```tsx
<Shader>
  <ChromaticAberration>
    <Circle />
  </ChromaticAberration>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'ChromaticAberration', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
