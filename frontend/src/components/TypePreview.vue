<script setup lang="ts">
import type { QType } from "../types.ts";
import { useI18n } from "../util.ts";

/**
 * Small decorative preview of what a question type looks like
 * (cards of the type picker). Uses the shades of the palette.
 */
defineProps<{ type: QType }>();
const { t } = useI18n();
const words = t("preview_words").split("|");
const sizes = ["w3", "w1", "w2", "w1", "w2", "w1"];
</script>

<template>
  <div class="uda-preview" :class="`uda-preview--${type}`" aria-hidden="true">
    <template v-if="type === 'choice'">
      <div v-for="(w, i) in [78, 46, 30, 58]" :key="i" class="uda-preview__row">
        <span class="uda-preview__radio" :class="{ 'is-on': i === 0 }"></span>
        <span class="uda-preview__line" :style="{ width: 30 + (i % 2) * 12 + '%' }"></span>
        <span class="uda-preview__bar" :style="{ width: w * 0.45 + '%' }"></span>
      </div>
    </template>
    <template v-else-if="type === 'cloud'">
      <div class="uda-preview__words">
        <span v-for="(word, i) in words" :key="i" :class="sizes[i % sizes.length]">{{ word }}</span>
      </div>
    </template>
    <template v-else-if="type === 'rank'">
      <div v-for="i in 4" :key="i" class="uda-preview__rank">
        <span class="uda-preview__num">{{ i }}</span>
        <span class="uda-preview__line" :style="{ width: 70 - i * 9 + '%' }"></span>
        <span class="uda-preview__handle"><i></i><i></i><i></i></span>
      </div>
    </template>
    <template v-else>
      <div class="uda-preview__quiz">
        <svg viewBox="0 0 36 36" class="uda-preview__timer">
          <circle cx="18" cy="18" r="15" class="track" />
          <circle cx="18" cy="18" r="15" class="arc" pathLength="100" stroke-dasharray="64 100" />
        </svg>
        <div class="uda-preview__quizlines">
          <span class="uda-preview__line" style="width: 85%"></span>
          <span class="uda-preview__opt is-right"></span>
          <span class="uda-preview__opt"></span>
        </div>
        <span class="uda-preview__badge">+180</span>
      </div>
    </template>
  </div>
</template>
