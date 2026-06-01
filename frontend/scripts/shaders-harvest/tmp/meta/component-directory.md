# Shaders Component Directory

This directory lists all available Shaders components, grouped by category.

**Role legend:**
- **Generator** — draws pixels directly to the canvas (no child required)
- **Effect** — transforms other components (`requiresChild: true`). Two modes:
  - **With explicit children** → applies only to those nested components
  - **Without children** → falls back to preceding siblings in the stack (e.g. add `FilmGrain` at the end to style the whole composition)

Placing an Effect with no children and no preceding siblings will render nothing.

## Textures

### Aurora
**Role:** Generator
Mesmerizing aurora borealis with layered curtains, vertical rays, and flowing light.

### Beam
**Role:** Generator
A beam of light from one point to another.

### Blob
**Role:** Generator
Organic animated blob with 3D lighting and gradients

### BrickPattern
**Role:** Generator
Classic brick wall pattern with alternating rows and mortar gaps

### Checkerboard
**Role:** Generator
Classic checkerboard pattern with two alternating colors

### Chevron
**Role:** Generator
Animated chevron / zigzag stripe pattern

### ColorWheel
**Role:** Generator
A directional gradient that smoothly cycles through rainbow colors or a custom set of three colors

### ConicGradient
**Role:** Generator
Colors sweep in a full circle around a center point, like a color wheel

### DiamondGradient
**Role:** Generator
Diamond-shaped gradient radiating from a center point using Manhattan distance

### DOMTexture
**Role:** Generator
Render live HTML/DOM content as a WebGPU texture layer via the html-in-canvas API. Requires Chrome Canary with chrome://flags/#canvas-draw-element enabled.

### DotGrid
**Role:** Generator
Grid of dots with optional twinkling animation

### FallingLines
**Role:** Generator
Directional falling lines with a leading-to-trailing color fade

### FloatingParticles
**Role:** Generator
Animated floating particles with twinkle effects

### FlowingGradient
**Role:** Generator
Liquid silk gradient with organic flowing color bands
**Pro Notes:**
- [Finishing Touches — Texture, Grain, and Ambient Motion](shaders://pro-notes/finishing-touches)

### FractalNoise
**Role:** Generator
Multi-octave fractal Brownian motion noise texture with true noise evolution

### Godrays
**Role:** Generator
Volumetric light rays emanating from a point

### Grid
**Role:** Generator
Simple grid lines pattern with adjustable thickness and rotation

### HexGrid
**Role:** Generator
Honeycomb hexagonal grid pattern

### ImageTexture
**Role:** Generator
Display an image with customizable object-fit modes
**Pro Notes:**
- [Media Effects — Images, Video, and Webcam](shaders://pro-notes/media-effects)

### LinearGradient
**Role:** Generator
Create smooth linear color gradients
**Pro Notes:**
- [Dynamic Prop Mapping — Driving Props from Mouse, Animation, and Layers](shaders://pro-notes/dynamic-prop-mapping)

### Marble
**Role:** Generator
Classic marble swirl and vein texture using noise-warped sine waves

### MultiPointGradient
**Role:** Generator
Five individually placed color points blended together by proximity — drag each point to shape the gradient

### Plasma
**Role:** Generator
Animated effect of glowing plasma

### RadialGradient
**Role:** Generator
Radial gradient radiating from a center point
**Pro Notes:**
- [Dynamic Prop Mapping — Driving Props from Mouse, Animation, and Layers](shaders://pro-notes/dynamic-prop-mapping)

### Ripples
**Role:** Generator
Concentric animated ripples emanating from a point

### SimplexNoise
**Role:** Generator
Organic noise with animated movement
**Pro Notes:**
- [Dynamic Prop Mapping — Driving Props from Mouse, Animation, and Layers](shaders://pro-notes/dynamic-prop-mapping)

### SineWave
**Role:** Generator
Animated wave with thickness and softness

### SolidColor
**Role:** Generator
Fill the canvas with a single solid color

### Spiral
**Role:** Generator
Rotating spiral pattern with animated movement

### Strands
**Role:** Generator
Procedural wavy strands with layered animation

### Stripes
**Role:** Generator
Alternating colored stripes with animation

### StudioBackground
**Role:** Generator
Multi-light studio background with ambient motion.
**Pro Notes:**
- [Finishing Touches — Texture, Grain, and Ambient Motion](shaders://pro-notes/finishing-touches)

### SunBurst
**Role:** Generator
Radial sunburst rays emanating from a center point

### Swirl
**Role:** Generator
Flowing swirl pattern with multi-layered noise
**Pro Notes:**
- [Finishing Touches — Texture, Grain, and Ambient Motion](shaders://pro-notes/finishing-touches)

### Truchet
**Role:** Generator
Quarter-circle arc tiles that connect to form organic, maze-like flowing curves

### VideoTexture
**Role:** Generator
Display a video with customizable playback and object-fit modes
**Pro Notes:**
- [Media Effects — Images, Video, and Webcam](shaders://pro-notes/media-effects)

### Voronoi
**Role:** Generator
Cellular pattern where each pixel is colored by its distance to the nearest of many scattered points

### Weave
**Role:** Generator
Interlaced textile weave pattern with two thread colors going over and under each other

### WebcamTexture
**Role:** Generator
Display a live webcam feed with customizable object-fit modes
**Pro Notes:**
- [Media Effects — Images, Video, and Webcam](shaders://pro-notes/media-effects)

### WorleyNoise
**Role:** Generator
Cellular noise field — distance-based, with selectable feature combinations and fractal octaves

## Shapes

### Circle
**Role:** Generator
Generate a circle with adjustable size and softness
**Pro Notes:**
- [Dynamic Prop Mapping — Driving Props from Mouse, Animation, and Layers](shaders://pro-notes/dynamic-prop-mapping)
- [Hero Section Masking Techniques](shaders://pro-notes/hero-section-masking)
- [Shape Effects — Placement, Sizing, and Stacking](shaders://pro-notes/shape-effects-placement)

### Crescent
**Role:** Generator
Crescent moon shape — an outer circle with an inner circle subtracted

### Cross
**Role:** Generator
Plus / cross shape with adjustable arm length, width, and rounding

### Ellipse
**Role:** Generator
Ellipse with independently adjustable horizontal and vertical radii
**Pro Notes:**
- [Dynamic Prop Mapping — Driving Props from Mouse, Animation, and Layers](shaders://pro-notes/dynamic-prop-mapping)
- [Hero Section Masking Techniques](shaders://pro-notes/hero-section-masking)
- [Shape Effects — Placement, Sizing, and Stacking](shaders://pro-notes/shape-effects-placement)

### Flower
**Role:** Generator
Petal shape with N lobes and adjustable inner-to-outer radius ratio

### Polygon
**Role:** Generator
Regular polygon with adjustable sides and corner rounding
**Pro Notes:**
- [Shape Effects — Placement, Sizing, and Stacking](shaders://pro-notes/shape-effects-placement)

### Ring
**Role:** Generator
Annular ring (donut) with adjustable radius and band thickness
**Pro Notes:**
- [Shape Effects — Placement, Sizing, and Stacking](shaders://pro-notes/shape-effects-placement)

### RoundedRect
**Role:** Generator
Rounded rectangle with adjustable width, height, and corner rounding
**Pro Notes:**
- [Shape Effects — Placement, Sizing, and Stacking](shaders://pro-notes/shape-effects-placement)

### Star
**Role:** Generator
Classic star polygon with straight sides and sharp pointed tips
**Pro Notes:**
- [Shape Effects — Placement, Sizing, and Stacking](shaders://pro-notes/shape-effects-placement)

### Trapezoid
**Role:** Generator
Trapezoid with adjustable top and bottom widths and height

### Vesica
**Role:** Generator
Vesica piscis (lens shape) formed by the intersection of two overlapping circles

## Shape Effects

### Crystal
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Diamond-like crystal lens with faceted refraction.
**Pro Notes:**
- [Shape Effects — Placement, Sizing, and Stacking](shaders://pro-notes/shape-effects-placement)

### Emboss
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Embossed / debossed relief shading on top of child content, driven by a custom shape
**Pro Notes:**
- [Shape Effects — Placement, Sizing, and Stacking](shaders://pro-notes/shape-effects-placement)

### Glass
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Optically realistic glass lens driven in a custom shape
**Pro Notes:**
- [Shape Effects — Placement, Sizing, and Stacking](shaders://pro-notes/shape-effects-placement)

### Neon
**Role:** Generator
Photorealistic neon tube / 3D pipe effect driven by a custom shape
**Pro Notes:**
- [Shape Effects — Placement, Sizing, and Stacking](shaders://pro-notes/shape-effects-placement)

### SmokeFill
**Role:** Generator
Fill a shape with swirling fluid smoke that interacts with the shape boundary

## Stylize

### Ascii
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Convert imagery to ASCII character art

### ChromaticAberration
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Separate RGB channels for a prismatic distortion effect
**Pro Notes:**
- [Dynamic Prop Mapping — Driving Props from Mouse, Animation, and Layers](shaders://pro-notes/dynamic-prop-mapping)

### ContourLines
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Draw topographical contour lines based on luminance or alpha

### CRTScreen
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Retro CRT monitor simulation with scanlines

### Dither
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Dithering effect with multiple pattern options

### DropShadow
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Adds a soft shadow behind the child content based on its alpha silhouette

### FilmGrain
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Analog film grain texture overlay, weighted toward darker areas
**Pro Notes:**
- [Finishing Touches — Texture, Grain, and Ambient Motion](shaders://pro-notes/finishing-touches)

### Glitch
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Digital glitch that melts pixels and distorts colors

### Glow
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Soft glow effect with adjustable intensity

### Halftone
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Halftone dot pattern effect for printing aesthetics

### LensFlare
**Role:** Generator
Realistic camera lens flare with artifacts.

### Paper
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Applies realistic paper grain and surface roughness to child content
**Pro Notes:**
- [Finishing Touches — Texture, Grain, and Ambient Motion](shaders://pro-notes/finishing-touches)

### Pixelate
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Pixelation effect with adjustable cell size

### ReflectivePlane
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Reflective floor that mirrors the content above it

### VHS
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Analog VHS tape with intermittent tape damage, chroma bleed, and per-scanline noise

### Vignette
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Darkens or tints the edges of the frame, drawing attention toward the center
**Pro Notes:**
- [Dynamic Prop Mapping — Driving Props from Mouse, Animation, and Layers](shaders://pro-notes/dynamic-prop-mapping)

## Interactive

### ChromaFlow
**Role:** Generator
Interactive liquid flow effect that follows your cursor
**Pro Notes:**
- [Adding Interactions — Interactive Components and Dynamic Prop Mapping](shaders://pro-notes/interactions)

### CursorRipples
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Fluid-like ripple distortion
**Pro Notes:**
- [Adding Interactions — Interactive Components and Dynamic Prop Mapping](shaders://pro-notes/interactions)

### CursorTrail
**Role:** Generator
Animated trail effect that tracks cursor movement
**Pro Notes:**
- [Adding Interactions — Interactive Components and Dynamic Prop Mapping](shaders://pro-notes/interactions)

### Fog
**Role:** Generator
Fog that fills the screen and interacts with the mouse

### GridDistortion
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Interactive grid distortion controlled by mouse position
**Pro Notes:**
- [Adding Interactions — Interactive Components and Dynamic Prop Mapping](shaders://pro-notes/interactions)

### Liquify
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Liquid-like interactive deformation effect
**Pro Notes:**
- [Adding Interactions — Interactive Components and Dynamic Prop Mapping](shaders://pro-notes/interactions)

### Shatter
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Broken glass effect with tectonic plate displacement
**Pro Notes:**
- [Adding Interactions — Interactive Components and Dynamic Prop Mapping](shaders://pro-notes/interactions)

### Smoke
**Role:** Generator
Realistic fluid smoke simulation with vorticity dynamics

## Distortions

### BarShift
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Slices content into parallel bars, each offset independently for a fractured or glitch-like effect

### Bulge
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Magnify or pinch content around a center point

### ConcentricSpin
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Concentric rings that each rotate the underlying image by different amounts

### FlowField
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Fluid-like distortion with constant smooth motion

### FlutedGlass
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Full-screen fluted glass effect — refracts content through repeating cylindrical bars

### Form3D
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Wraps child content onto a 3D raymarched shape with lighting.

### GlassTiles
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Refraction-like distortion in a tile grid pattern

### Kaleidoscope
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Create a kaleidoscope effect with radial mirrored segments

### Mirror
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Mirror content across a line defined by center point and angle

### Perspective
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Rotate the plane in 3D space with pan and tilt

### PolarCoordinates
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Convert rectangular coordinates to polar space

### RectangularCoordinates
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Convert polar coordinates back to rectangular space

### Spherize
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Map content onto a 3D sphere surface with depth distortion

### Stretch
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Stretch content towards a direction from a center point

### Twirl
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Rotate and twist content around a center point

### WaveDistortion
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Wave-based distortion with multiple waveform types

## Blurs

### AngularBlur
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Radial motion blur rotating around a center point
**Pro Notes:**
- [Dynamic Prop Mapping — Driving Props from Mouse, Animation, and Layers](shaders://pro-notes/dynamic-prop-mapping)

### Blur
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
A simple Gaussian blur effect
**Pro Notes:**
- [Dynamic Prop Mapping — Driving Props from Mouse, Animation, and Layers](shaders://pro-notes/dynamic-prop-mapping)

### ChannelBlur
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Independent blur for red, green, and blue channels

### DiffuseBlur
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Grain-like pixel displacement at random

### LinearBlur
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Directional motion blur in a specific angle

### ProgressiveBlur
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Blur that increases progressively in one direction

### TiltShift
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Selective focus blur mimicking tilt-shift photography

### ZoomBlur
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Radial zoom blur expanding from a center point
**Pro Notes:**
- [Dynamic Prop Mapping — Driving Props from Mouse, Animation, and Layers](shaders://pro-notes/dynamic-prop-mapping)

## Adjustments

### BrightnessContrast
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Adjust brightness and contrast of the image

### Duotone
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Map colors to two tones based on luminance

### Grayscale
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Convert colors to black and white

### HueShift
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Rotate hue around the color wheel

### Invert
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Invert RGB colors while preserving alpha

### Posterize
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Reduce color depth to create a poster effect

### Saturation
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Adjust color saturation intensity

### Sharpness
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Adjust image sharpness using a convolution kernel

### Solarize
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Inverts tones above a luminance threshold — a classic darkroom and photo effect

### Tint
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Apply a color tint to the image

### Tritone
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Map colors to three tones: shadows, midtones, highlights

### Vibrance
**Role:** Effect — applies to explicit children, or falls back to preceding siblings
Selective saturation adjustment protecting skin tones

## Utilities
