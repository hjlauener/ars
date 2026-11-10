<script setup lang="ts">
import type { AppConfig } from "../types.ts";
import { formatCode, useI18n } from "../util.ts";
import QrCode from "./QrCode.vue";

/** tab "Teilnehmen" (people with read permission): code, QR code, link */
defineProps<{ config: AppConfig }>();
const { t } = useI18n();
</script>

<template>
  <section class="uda-panel uda-joininfo">
    <div class="uda-joininfo__text">
      <h2>{{ config.title }}</h2>
      <p>{{ t("joininfo_text") }}</p>
      <a class="uda-btn uda-btn--primary uda-btn--large" :href="config.joinUrl" target="_blank" rel="noopener">{{ t("joininfo_open") }}</a>
    </div>
    <div class="uda-start__join">
      <QrCode :value="config.joinUrl" :label="t('qr_label')" />
      <p class="uda-start__code"><span>{{ t("code") }}</span><strong>{{ formatCode(config.code) }}</strong></p>
      <p class="uda-start__url">{{ config.displayUrl }}</p>
    </div>
  </section>
</template>
