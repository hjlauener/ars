<script setup lang="ts">
import { onMounted, ref } from "vue";
import type { AppConfig, Question, Results, SessionInfo } from "../types.ts";
import { formatDateTime, postJson, useI18n } from "../util.ts";
import ConfirmDialog from "./ConfirmDialog.vue";
import ResultsDisplay from "./ResultsDisplay.vue";
import TypeIcon from "./TypeIcon.vue";

/**
 * Tab "Ergebnisse": the sessions (runs) of the presentation; per session
 * the results of every question, export as CSV, delete.
 */
const props = defineProps<{ config: AppConfig }>();
const { t, tn, lang } = useI18n();
const api = props.config.apiUrl ?? "";

const sessions = ref<SessionInfo[]>([]);
const retention = ref(0);
const loaded = ref(false);
const openId = ref<number | null>(null);
const items = ref<{ question: Question; results: Results }[]>([]);
const deleting = ref<SessionInfo | null>(null);
const errorText = ref("");

async function call<T>(body: Record<string, unknown>): Promise<T | null> {
  try {
    const result = await postJson<T & { error?: string }>(api, body);
    errorText.value = result.error ?? "";
    return result.error ? null : result;
  } catch {
    errorText.value = t("err_connection");
    return null;
  }
}

async function load(): Promise<void> {
  const result = await call<{ sessions: SessionInfo[]; retentionDays: number }>({ action: "sessions" });
  if (result) {
    sessions.value = result.sessions;
    retention.value = result.retentionDays;
  }
  loaded.value = true;
}

async function show(session: SessionInfo): Promise<void> {
  if (openId.value === session.id) {
    openId.value = null;
    return;
  }
  const result = await call<{ items: { question: Question; results: Results }[] }>({ action: "sessionResults", sessionId: session.id });
  if (result) {
    items.value = result.items;
    openId.value = session.id;
  }
}

async function confirmDelete(): Promise<void> {
  const session = deleting.value;
  deleting.value = null;
  if (!session) {
    return;
  }
  const result = await call<{ sessions: SessionInfo[] }>({ action: "deleteSession", sessionId: session.id });
  if (result) {
    sessions.value = result.sessions;
    if (openId.value === session.id) {
      openId.value = null;
    }
  }
}

function exportUrl(session: SessionInfo): string {
  const base = props.config.exportUrl ?? "";
  return `${base}${base.includes("?") ? "&" : "?"}session_id=${session.id}`;
}

function duration(s: SessionInfo): string {
  if (s.ended === 0) {
    return t("results_running");
  }
  const minutes = Math.max(1, Math.round((s.ended - s.started) / 60));
  return tn("minutes", minutes);
}

onMounted(load);
</script>

<template>
  <div class="uda-resultsapp">
    <p v-if="errorText" class="uda-alert" role="alert">{{ errorText }}</p>
    <p class="uda-note">
      {{ t("results_intro") }}
      <template v-if="retention > 0"> {{ tn("results_retention", retention) }}</template>
    </p>

    <p v-if="loaded && sessions.length === 0" class="uda-panel uda-empty">{{ t("results_none") }}</p>

    <ul v-else class="uda-sessions">
      <li v-for="s in sessions" :key="s.id" class="uda-session" :class="{ 'is-open': openId === s.id }">
        <div class="uda-session__row">
          <div class="uda-session__info">
            <strong class="uda-session__date">{{ formatDateTime(s.started, lang) }}</strong>
            <span class="uda-session__meta">
              {{ duration(s) }} · {{ tn("results_n_participants", s.participants) }} · {{ tn("results_n_answers", s.responses) }}
            </span>
          </div>
          <div class="uda-session__actions">
            <button type="button" class="uda-btn uda-btn--small" :aria-expanded="openId === s.id" @click="show(s)">
              {{ openId === s.id ? t("results_hide") : t("results_show") }}
            </button>
            <a class="uda-btn uda-btn--small" :href="exportUrl(s)">{{ t("results_csv") }}</a>
            <button type="button" class="uda-btn uda-btn--small uda-btn--danger-text" @click="deleting = s">{{ t("results_delete") }}</button>
          </div>
        </div>
        <div v-if="openId === s.id" class="uda-session__details">
          <article v-for="(item, i) in items" :key="item.question.id" class="uda-result-card">
            <header class="uda-result-card__head">
              <span class="uda-chip"><TypeIcon :type="item.question.type" :size="16" /> {{ i + 1 }}</span>
              <h3 class="uda-result-card__title">{{ item.question.title || t("editor_untitled") }}</h3>
              <span class="uda-result-card__total">{{ tn("present_n_answered", item.results.total) }}</span>
            </header>
            <ResultsDisplay :results="item.results" :reveal="true" :leaderboard="!items.slice(i + 1).some((x) => x.question.type === 'quiz')" />
          </article>
        </div>
      </li>
    </ul>

    <ConfirmDialog
      :open="deleting !== null"
      :title="t('results_delete_title')"
      :text="t('results_delete_text', deleting ? formatDateTime(deleting.started, lang) : '')"
      :confirm="t('results_delete')"
      @confirm="confirmDelete"
      @cancel="deleting = null"
    />
  </div>
</template>
