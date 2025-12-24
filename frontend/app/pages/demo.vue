<template>
  <div class="glass-demo-page bg-background">
    <!-- Main Content -->
    <div class="glass-demo-content">
      <!-- All Variants -->
      <section class="glass-variants-section">
        <h2 class="glass-section-title">All Variants</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="default">Default</GlassButton>
            <span class="glass-variant-label">default</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="secondary">Secondary</GlassButton>
            <span class="glass-variant-label">secondary</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive">Destructive</GlassButton>
            <span class="glass-variant-label">destructive</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline">Destructive</GlassButton>
            <span class="glass-variant-label">destructive-outline</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="outline">Outline</GlassButton>
            <span class="glass-variant-label">outline</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="ghost">Ghost</GlassButton>
            <span class="glass-variant-label">ghost</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="link">Link</GlassButton>
            <span class="glass-variant-label">link</span>
          </div>
        </div>
      </section>

      <!-- Sizes for Each Variant -->
      <section class="glass-variants-section">
        <h2 class="glass-section-title">Sizes - Default Variant</h2>
        <div class="glass-variants-grid glass-variants-grid-sizes">
          <div class="glass-variant-item">
            <GlassButton variant="default" size="xs">Extra Small</GlassButton>
            <span class="glass-variant-label">xs</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="default" size="sm">Small</GlassButton>
            <span class="glass-variant-label">sm</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="default" size="default">Default</GlassButton>
            <span class="glass-variant-label">default</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="default" size="lg">Large</GlassButton>
            <span class="glass-variant-label">lg</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="default" size="xl">Extra Large</GlassButton>
            <span class="glass-variant-label">xl</span>
          </div>
        </div>

        <h2 class="glass-section-title">Sizes - Outline Variant</h2>
        <div class="glass-variants-grid glass-variants-grid-sizes">
          <div class="glass-variant-item">
            <GlassButton variant="outline" size="xs">Extra Small</GlassButton>
            <span class="glass-variant-label">xs</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="outline" size="sm">Small</GlassButton>
            <span class="glass-variant-label">sm</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="outline" size="default">Default</GlassButton>
            <span class="glass-variant-label">default</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="outline" size="lg">Large</GlassButton>
            <span class="glass-variant-label">lg</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="outline" size="xl">Extra Large</GlassButton>
            <span class="glass-variant-label">xl</span>
          </div>
        </div>

        <h2 class="glass-section-title">Sizes - Destructive Variant</h2>
        <div class="glass-variants-grid glass-variants-grid-sizes">
          <div class="glass-variant-item">
            <GlassButton variant="destructive" size="xs">Extra Small</GlassButton>
            <span class="glass-variant-label">xs</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive" size="sm">Small</GlassButton>
            <span class="glass-variant-label">sm</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive" size="default">Default</GlassButton>
            <span class="glass-variant-label">default</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive" size="lg">Large</GlassButton>
            <span class="glass-variant-label">lg</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive" size="xl">Extra Large</GlassButton>
            <span class="glass-variant-label">xl</span>
          </div>
        </div>

        <h2 class="glass-section-title">Sizes - Destructive Outline Variant</h2>
        <div class="glass-variants-grid glass-variants-grid-sizes">
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline" size="xs">Extra Small</GlassButton>
            <span class="glass-variant-label">xs</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline" size="sm">Small</GlassButton>
            <span class="glass-variant-label">sm</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline" size="default">Default</GlassButton>
            <span class="glass-variant-label">default</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline" size="lg">Large</GlassButton>
            <span class="glass-variant-label">lg</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline" size="xl">Extra Large</GlassButton>
            <span class="glass-variant-label">xl</span>
          </div>
        </div>

        <h2 class="glass-section-title">Sizes - Secondary Variant</h2>
        <div class="glass-variants-grid glass-variants-grid-sizes">
          <div class="glass-variant-item">
            <GlassButton variant="secondary" size="xs">Extra Small</GlassButton>
            <span class="glass-variant-label">xs</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="secondary" size="sm">Small</GlassButton>
            <span class="glass-variant-label">sm</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="secondary" size="default">Default</GlassButton>
            <span class="glass-variant-label">default</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="secondary" size="lg">Large</GlassButton>
            <span class="glass-variant-label">lg</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="secondary" size="xl">Extra Large</GlassButton>
            <span class="glass-variant-label">xl</span>
          </div>
        </div>

        <h2 class="glass-section-title">Sizes - Ghost Variant</h2>
        <div class="glass-variants-grid glass-variants-grid-sizes">
          <div class="glass-variant-item">
            <GlassButton variant="ghost" size="xs">Extra Small</GlassButton>
            <span class="glass-variant-label">xs</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="ghost" size="sm">Small</GlassButton>
            <span class="glass-variant-label">sm</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="ghost" size="default">Default</GlassButton>
            <span class="glass-variant-label">default</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="ghost" size="lg">Large</GlassButton>
            <span class="glass-variant-label">lg</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="ghost" size="xl">Extra Large</GlassButton>
            <span class="glass-variant-label">xl</span>
          </div>
        </div>
      </section>

      <!-- Icon Sizes -->
      <section class="glass-variants-section">
        <h2 class="glass-section-title">Icon Sizes - Default Variant</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="default" size="icon-xs">
              <Plus class="size-3" />
            </GlassButton>
            <span class="glass-variant-label">icon-xs</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="default" size="icon-sm">
              <Plus class="size-4" />
            </GlassButton>
            <span class="glass-variant-label">icon-sm</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="default" size="icon">
              <Plus class="size-5" />
            </GlassButton>
            <span class="glass-variant-label">icon</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="default" size="icon-lg">
              <Plus class="size-6" />
            </GlassButton>
            <span class="glass-variant-label">icon-lg</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="default" size="icon-xl">
              <Plus class="size-7" />
            </GlassButton>
            <span class="glass-variant-label">icon-xl</span>
          </div>
        </div>

        <h2 class="glass-section-title">Icon Sizes - Outline Variant</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="outline" size="icon-xs">
              <Plus class="size-3" />
            </GlassButton>
            <span class="glass-variant-label">icon-xs</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="outline" size="icon-sm">
              <Plus class="size-4" />
            </GlassButton>
            <span class="glass-variant-label">icon-sm</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="outline" size="icon">
              <Plus class="size-5" />
            </GlassButton>
            <span class="glass-variant-label">icon</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="outline" size="icon-lg">
              <Plus class="size-6" />
            </GlassButton>
            <span class="glass-variant-label">icon-lg</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="outline" size="icon-xl">
              <Plus class="size-7" />
            </GlassButton>
            <span class="glass-variant-label">icon-xl</span>
          </div>
        </div>

        <h2 class="glass-section-title">Icon Sizes - Destructive Variant</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="destructive" size="icon-xs">
              <Trash class="size-3" />
            </GlassButton>
            <span class="glass-variant-label">icon-xs</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive" size="icon-sm">
              <Trash class="size-4" />
            </GlassButton>
            <span class="glass-variant-label">icon-sm</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive" size="icon">
              <Trash class="size-5" />
            </GlassButton>
            <span class="glass-variant-label">icon</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive" size="icon-lg">
              <Trash class="size-6" />
            </GlassButton>
            <span class="glass-variant-label">icon-lg</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive" size="icon-xl">
              <Trash class="size-7" />
            </GlassButton>
            <span class="glass-variant-label">icon-xl</span>
          </div>
        </div>

        <h2 class="glass-section-title">Icon Sizes - Destructive Outline Variant</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline" size="icon-xs">
              <Trash class="size-3" />
            </GlassButton>
            <span class="glass-variant-label">icon-xs</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline" size="icon-sm">
              <Trash class="size-4" />
            </GlassButton>
            <span class="glass-variant-label">icon-sm</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline" size="icon">
              <Trash class="size-5" />
            </GlassButton>
            <span class="glass-variant-label">icon</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline" size="icon-lg">
              <Trash class="size-6" />
            </GlassButton>
            <span class="glass-variant-label">icon-lg</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline" size="icon-xl">
              <Trash class="size-7" />
            </GlassButton>
            <span class="glass-variant-label">icon-xl</span>
          </div>
        </div>

        <h2 class="glass-section-title">Icon Sizes - Secondary Variant</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="secondary" size="icon-xs">
              <Settings class="size-3" />
            </GlassButton>
            <span class="glass-variant-label">icon-xs</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="secondary" size="icon-sm">
              <Settings class="size-4" />
            </GlassButton>
            <span class="glass-variant-label">icon-sm</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="secondary" size="icon">
              <Settings class="size-5" />
            </GlassButton>
            <span class="glass-variant-label">icon</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="secondary" size="icon-lg">
              <Settings class="size-6" />
            </GlassButton>
            <span class="glass-variant-label">icon-lg</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="secondary" size="icon-xl">
              <Settings class="size-7" />
            </GlassButton>
            <span class="glass-variant-label">icon-xl</span>
          </div>
        </div>

        <h2 class="glass-section-title">Icon Sizes - Ghost Variant</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="ghost" size="icon-xs">
              <X class="size-3" />
            </GlassButton>
            <span class="glass-variant-label">icon-xs</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="ghost" size="icon-sm">
              <X class="size-4" />
            </GlassButton>
            <span class="glass-variant-label">icon-sm</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="ghost" size="icon">
              <X class="size-5" />
            </GlassButton>
            <span class="glass-variant-label">icon</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="ghost" size="icon-lg">
              <X class="size-6" />
            </GlassButton>
            <span class="glass-variant-label">icon-lg</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="ghost" size="icon-xl">
              <X class="size-7" />
            </GlassButton>
            <span class="glass-variant-label">icon-xl</span>
          </div>
        </div>
      </section>

      <!-- States for Each Variant -->
      <section class="glass-variants-section">
        <h2 class="glass-section-title">States - Default Variant</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="default">Normal</GlassButton>
            <span class="glass-variant-label">normal</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="default" disabled>Disabled</GlassButton>
            <span class="glass-variant-label">disabled</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="default" :loading="true">Loading</GlassButton>
            <span class="glass-variant-label">loading</span>
          </div>
        </div>

        <h2 class="glass-section-title">States - Outline Variant</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="outline">Normal</GlassButton>
            <span class="glass-variant-label">normal</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="outline" disabled>Disabled</GlassButton>
            <span class="glass-variant-label">disabled</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="outline" :loading="true">Loading</GlassButton>
            <span class="glass-variant-label">loading</span>
          </div>
        </div>

        <h2 class="glass-section-title">States - Destructive Variant</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="destructive">Normal</GlassButton>
            <span class="glass-variant-label">normal</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive" disabled>Disabled</GlassButton>
            <span class="glass-variant-label">disabled</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive" :loading="true">Loading</GlassButton>
            <span class="glass-variant-label">loading</span>
          </div>
        </div>

        <h2 class="glass-section-title">States - Destructive Outline Variant</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline">Normal</GlassButton>
            <span class="glass-variant-label">normal</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline" disabled>Disabled</GlassButton>
            <span class="glass-variant-label">disabled</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline" :loading="true">Loading</GlassButton>
            <span class="glass-variant-label">loading</span>
          </div>
        </div>

        <h2 class="glass-section-title">States - Secondary Variant</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="secondary">Normal</GlassButton>
            <span class="glass-variant-label">normal</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="secondary" disabled>Disabled</GlassButton>
            <span class="glass-variant-label">disabled</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="secondary" :loading="true">Loading</GlassButton>
            <span class="glass-variant-label">loading</span>
          </div>
        </div>

        <h2 class="glass-section-title">States - Ghost Variant</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="ghost">Normal</GlassButton>
            <span class="glass-variant-label">normal</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="ghost" disabled>Disabled</GlassButton>
            <span class="glass-variant-label">disabled</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="ghost" :loading="true">Loading</GlassButton>
            <span class="glass-variant-label">loading</span>
          </div>
        </div>

        <h2 class="glass-section-title">States - Link Variant</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="link">Normal</GlassButton>
            <span class="glass-variant-label">normal</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="link" disabled>Disabled</GlassButton>
            <span class="glass-variant-label">disabled</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="link" :loading="true">Loading</GlassButton>
            <span class="glass-variant-label">loading</span>
          </div>
        </div>
      </section>

      <!-- Buttons with Icons and Text -->
      <section class="glass-variants-section">
        <h2 class="glass-section-title">With Icon and Text</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="default">
              <Plus class="size-4" />
              <span>Add Item</span>
            </GlassButton>
            <span class="glass-variant-label">default</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="outline">
              <Download class="size-4" />
              <span>Download</span>
            </GlassButton>
            <span class="glass-variant-label">outline</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive">
              <Trash class="size-4" />
              <span>Delete</span>
            </GlassButton>
            <span class="glass-variant-label">destructive</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline">
              <Trash class="size-4" />
              <span>Remove</span>
            </GlassButton>
            <span class="glass-variant-label">destructive-outline</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="secondary">
              <Settings class="size-4" />
              <span>Settings</span>
            </GlassButton>
            <span class="glass-variant-label">secondary</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="ghost">
              <Menu class="size-4" />
              <span>Menu</span>
            </GlassButton>
            <span class="glass-variant-label">ghost</span>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { GlassButton } from "@/components/ui/glass-button";
import { Plus, Trash, Settings, X, Download, Menu } from "lucide-vue-next";
</script>

<style scoped>
.glass-demo-page {
  position: relative;
  min-height: 100vh;
  width: 100%;
  overflow-x: hidden;
}

.glass-demo-content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 4rem 2rem;
  gap: 4rem;
}

/* Variants Section */
.glass-variants-section {
  width: 100%;
  max-width: 1200px;
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.glass-section-title {
  font-size: 1.25rem;
  font-weight: 600;
  text-align: center;
  margin-bottom: 0.5rem;
}

.glass-variants-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 2rem;
  padding: 2rem;
  border-radius: 1.5rem;
  border: 1px solid var(--color-border / 0.3);
}

.glass-variants-grid-sizes {
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
}

.glass-variant-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
}

.glass-variant-label {
  font-size: 0.75rem;
  font-family: ui-monospace, monospace;
  color: var(--color-muted-foreground);
}
</style>
