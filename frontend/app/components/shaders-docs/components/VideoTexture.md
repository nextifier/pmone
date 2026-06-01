---
title: VideoTexture
description: Display a video with customizable playback and object-fit modes
category: Textures
componentType: Generator
requiresChild: false
---

# VideoTexture

Display a video with customizable playback and object-fit modes


::shader-preview{component="VideoTexture"}
::

## Props

::props-table
---
props:
  - name: "url"
    type: "string"
    default: "https://shaders.com/sample.mp4"
    description: "Upload a video or provide a URL"
  - name: "objectFit"
    type: "\"cover\" | \"contain\" | \"fill\" | \"scale-down\" | \"none\""
    default: "cover"
    description: "How the video should be sized within the viewport"
  - name: "loop"
    type: "boolean"
    default: "true"
    description: "Loop the video playback"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <VideoTexture />
</Shader>
```

```jsx
<Shader>
  <VideoTexture />
</Shader>
```

```svelte
<Shader>
  <VideoTexture />
</Shader>
```

```tsx
<Shader>
  <VideoTexture />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'VideoTexture', props: {} }
  ]
})
```
::
