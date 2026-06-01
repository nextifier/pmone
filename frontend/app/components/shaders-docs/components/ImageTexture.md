---
title: ImageTexture
description: Display an image with customizable object-fit modes
category: Textures
componentType: Generator
requiresChild: false
---

# ImageTexture

Display an image with customizable object-fit modes


::shader-preview{component="ImageTexture"}
::

## Props

::props-table
---
props:
  - name: "url"
    type: "string"
    default: "https://shaders.com/sample.jpg"
    description: "Upload an image or provide a URL"
  - name: "objectFit"
    type: "\"cover\" | \"contain\" | \"fill\" | \"scale-down\" | \"none\""
    default: "cover"
    description: "How the image should be sized within the viewport"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <ImageTexture />
</Shader>
```

```jsx
<Shader>
  <ImageTexture />
</Shader>
```

```svelte
<Shader>
  <ImageTexture />
</Shader>
```

```tsx
<Shader>
  <ImageTexture />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'ImageTexture', props: {} }
  ]
})
```
::
