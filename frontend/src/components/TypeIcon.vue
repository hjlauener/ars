<script setup lang="ts">
import type { QType } from "../types.ts";

/** line icons of the four question types (stroke = currentColor) */
defineProps<{ type: QType; size?: number }>();
</script>

<template>
  <svg
    class="uda-type-icon"
    :width="size ?? 24"
    :height="size ?? 24"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.7"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
  >
    <template v-if="type === 'choice'">
      <circle cx="5" cy="6" r="2" />
      <circle cx="5" cy="12" r="2" />
      <circle cx="5" cy="18" r="2" />
      <circle cx="5" cy="12" r="0.6" fill="currentColor" />
      <path d="M10 6h10M10 12h7M10 18h4" />
    </template>
    <template v-else-if="type === 'cloud'">
      <path d="M3 9h6M12 9h4M5 13.5h11M18.5 13.5H21M4 18h3M10 18h7" />
      <path d="M6 5h8" stroke-width="2.6" />
    </template>
    <template v-else-if="type === 'rank'">
      <path d="M4 5.5h1.5v4M4 9.5h3" />
      <path d="M10 6h10M10 12h8M10 18h6" />
      <path d="M4.2 14.5a1.6 1.6 0 1 1 2.4 1.4L4 18.5h3" />
    </template>
    <template v-else>
      <circle cx="12" cy="13" r="7.5" />
      <path d="M12 9v4l2.5 2M10 3h4M18.5 6.5l1.2-1.2" />
    </template>
  </svg>
</template>
