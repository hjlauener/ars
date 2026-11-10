<script setup lang="ts">
import type { OptionResult } from "../types.ts";
import { percent, useI18n } from "../util.ts";

/**
 * Results of a choice or quiz question as list with horizontal bars
 * (percentage of the people who answered). Quiz: the correct answer is
 * marked once `reveal` is set.
 */
defineProps<{ options: OptionResult[]; total: number; reveal?: boolean; large?: boolean }>();
const { t } = useI18n();
</script>

<template>
  <ol class="uda-bars" :class="{ 'uda-bars--large': large }">
    <li
      v-for="o in options"
      :key="o.id"
      class="uda-bars__row"
      :class="{ 'is-correct': reveal && o.correct, 'is-wrong': reveal && !o.correct }"
    >
      <div class="uda-bars__label">
        <span class="uda-bars__text">
          <span v-if="reveal && o.correct" class="uda-bars__mark" :title="t('quiz_correct_answer')">✓</span>
          {{ o.text }}
        </span>
        <span class="uda-bars__value">
          <strong>{{ percent(o.count, total) }} %</strong>
          <span class="uda-bars__count">({{ o.count }})</span>
        </span>
      </div>
      <div class="uda-bars__track">
        <div class="uda-bars__fill" :style="{ width: percent(o.count, total) + '%' }"></div>
      </div>
    </li>
  </ol>
</template>
