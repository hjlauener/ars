<script setup lang="ts">
import { ALL_TYPES, type QType } from "../types.ts";
import { useI18n } from "../util.ts";
import TypeIcon from "./TypeIcon.vue";
import TypePreview from "./TypePreview.vue";

/**
 * Choice of the question type: four cards (2 × 2, one column on phones).
 * Each card is one button; types switched off in the plugin configuration
 * are not shown.
 */
const props = defineProps<{ types: QType[]; busy?: boolean }>();
const emit = defineEmits<{ choose: [type: QType] }>();
const { t } = useI18n();

const shown = ALL_TYPES.filter((type) => props.types.includes(type));
const tags = (type: QType): string[] => t(`tags_${type}`).split("|").filter(Boolean);
</script>

<template>
  <div class="uda-picker">
    <div class="uda-picker__head">
      <h2 class="uda-picker__title">{{ t("picker_title") }}</h2>
      <p class="uda-picker__sub">{{ t("picker_subtitle") }}</p>
    </div>
    <p v-if="shown.length === 0" class="uda-note">{{ t("picker_none") }}</p>
    <div class="uda-picker__grid">
      <button
        v-for="type in shown"
        :key="type"
        type="button"
        class="uda-type-card"
        :class="`uda-type-card--${type}`"
        :disabled="busy"
        @click="emit('choose', type)"
      >
        <span class="uda-type-card__head">
          <span class="uda-type-card__icon"><TypeIcon :type="type" :size="22" /></span>
          <span class="uda-type-card__title">{{ t(`type_${type}`) }}</span>
        </span>
        <TypePreview :type="type" />
        <span class="uda-type-card__desc">{{ t(`type_${type}_desc`) }}</span>
        <span class="uda-type-card__tags">
          <span v-for="tag in tags(type)" :key="tag" class="uda-tag">{{ tag }}</span>
        </span>
        <span class="uda-type-card__action">{{ t("picker_use") }} <span aria-hidden="true">→</span></span>
      </button>
    </div>
  </div>
</template>
