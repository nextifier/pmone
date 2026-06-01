---
title: SolidColor
description: Fill the canvas with a single solid color
category: Textures
componentType: Generator
requiresChild: false
---

# SolidColor

Fill the canvas with a single solid color


::shader-preview{component="SolidColor"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#5b18ca"
    description: "The solid color to display"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <SolidColor
    color="#5b18ca"
  />
</Shader>
```

```jsx
<Shader>
  <SolidColor
    color="#5b18ca"
  />
</Shader>
```

```svelte
<Shader>
  <SolidColor
    color="#5b18ca"
  />
</Shader>
```

```tsx
<Shader>
  <SolidColor
    color="#5b18ca"
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'SolidColor', props: { color: '#5b18ca' } }
  ]
})
```
::
