import { createApp, type Component } from "vue";
import "./styles/ars.css";
import type { AppConfig } from "./types.ts";
import { I18N, createI18n, paletteStyle } from "./util.ts";
import EditorApp from "./components/EditorApp.vue";
import JoinInfo from "./components/JoinInfo.vue";
import PresenterApp from "./components/PresenterApp.vue";
import ResultsApp from "./components/ResultsApp.vue";

/** views inside ILIAS: <div class="uda-app" data-config="{…}"> */
const VIEWS: Record<AppConfig["view"], Component> = {
  editor: EditorApp,
  presenter: PresenterApp,
  results: ResultsApp,
  join: JoinInfo,
};

for (const el of document.querySelectorAll<HTMLElement>(".uda-app")) {
  const config = JSON.parse(el.dataset.config ?? "{}") as AppConfig;
  el.classList.add("uda");
  Object.entries(paletteStyle(config.palette)).forEach(([k, v]) => el.style.setProperty(k, v));
  createApp(VIEWS[config.view], { config }).provide(I18N, createI18n(config.texts, config.lang)).mount(el);
}
