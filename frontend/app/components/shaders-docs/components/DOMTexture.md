---
title: DOMTexture
description: "Render live HTML/DOM content as a WebGPU texture layer via the html-in-canvas API. Requires Chrome Canary with chrome://flags/#canvas-draw-element enabled."
category: Textures
componentType: Generator
requiresChild: false
---

# DOMTexture

Render live HTML/DOM content as a WebGPU texture layer via the html-in-canvas API. Requires Chrome Canary with chrome://flags/#canvas-draw-element enabled.

::experimental-warning{message="This component is powered by the WICG html-in-canvas proposal, which is currently only available in Chrome Canary behind a feature flag. It is not suitable for production use and may change as the specification evolves." link-url="https://github.com/WICG/html-in-canvas" link-label="View the spec"}
::


::shader-preview{component="DOMTexture"}
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <DOMTexture />
</Shader>
```

```jsx
<Shader>
  <DOMTexture />
</Shader>
```

```svelte
<Shader>
  <DOMTexture />
</Shader>
```

```tsx
<Shader>
  <DOMTexture />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'DOMTexture', props: {} }
  ]
})
```
::
