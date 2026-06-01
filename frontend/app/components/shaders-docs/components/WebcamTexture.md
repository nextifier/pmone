---
title: WebcamTexture
description: Display a live webcam feed with customizable object-fit modes
category: Textures
componentType: Generator
requiresChild: false
---

# WebcamTexture

Display a live webcam feed with customizable object-fit modes


::shader-preview{component="WebcamTexture"}
::

## Props

::props-table
---
props:
  - name: "objectFit"
    type: "\"cover\" | \"contain\" | \"fill\" | \"scale-down\" | \"none\""
    default: "cover"
    description: "How the webcam feed should be sized within the viewport"
  - name: "mirror"
    type: "boolean"
    default: "true"
    description: "Mirror the webcam feed horizontally (selfie mode)"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <WebcamTexture />
</Shader>
```

```jsx
<Shader>
  <WebcamTexture />
</Shader>
```

```svelte
<Shader>
  <WebcamTexture />
</Shader>
```

```tsx
<Shader>
  <WebcamTexture />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'WebcamTexture', props: {} }
  ]
})
```
::
