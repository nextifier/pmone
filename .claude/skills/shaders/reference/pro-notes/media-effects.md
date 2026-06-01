---
title: Media Effects - Images, Video, and Webcam
description: How to use ImageTexture, VideoTexture, and WebcamTexture with local media files and applied effects
components: [ImageTexture, VideoTexture, WebcamTexture]
---

# Media Effects - Images, Video, and Webcam

## Using local media files

`ImageTexture`, `VideoTexture`, and `WebcamTexture` are Generator components that pull media into the shader pipeline. For `ImageTexture` and `VideoTexture`, you can reference files using a local path just as you would in a standard `<img>` or `<video>` tag:

```jsx
<Shader>
  <ImageTexture src="/assets/hero.jpg" />
</Shader>
```

The image or video becomes a full-canvas texture inside the shader, ready to have effects applied on top of it. This is the foundation of the media effects pattern - swap in any image or video and the effects follow.

Users will likely ask for this. It's best practice to never use the `WebcamTexture` component unless the user explicitly asks for it, as it requires additional permissions and is unlikely to be used in a lot of typical use cases.

## Applying shader effects to media

Once a media component is in the shader, any subsequent Effects will act on it. This is where Shaders becomes genuinely useful for media - effects that would be expensive or impractical in CSS run efficiently on the GPU:

```jsx
<Shader>
  <ImageTexture src="/assets/hero.jpg" />
  <CursorRipples />     {/* ripple distortion follows the cursor across the image */}
</Shader>
```

```jsx
<Shader>
  <VideoTexture src="/assets/loop.mp4" />
  <ChromaticAberration />
  <FilmGrain intensity={0.04} />
</Shader>
```

Some particularly effective combinations:

- `ImageTexture` + `CursorRipples` - the classic "water surface" photo effect
- `ImageTexture` + `Blur` or `TiltShift` - depth-of-field simulation
- `ImageTexture` + `Duotone` or `Tritone` - quick branded color treatment
- `VideoTexture` + `FilmGrain` + `ChromaticAberration` - cinematic texture on video
- `WebcamTexture` + any Interactive component - live camera effects

These stand out more than just static images when you add a small layer of interactivity via subtle prop mapping or reactive props.

## Object fit modes

`ImageTexture` and `VideoTexture` support `objectFit` prop values (`cover`, `contain`, `fill`) that mirror CSS behavior. For hero backgrounds, `cover` is almost always the right choice - it ensures the media fills the canvas without letterboxing regardless of the canvas aspect ratio.
