import { createApp } from "vue";
import "./styles/ars.css";
import type { JoinConfig } from "./types.ts";
import { I18N, createI18n } from "./util.ts";
import JoinApp from "./join/JoinApp.vue";

/** participation page (join.php): configuration in <script id="uda-config"> */
const el = document.getElementById("uda-join");
const config = JSON.parse(document.getElementById("uda-config")?.textContent ?? "{}") as JoinConfig;
if (el) {
  el.classList.add("uda");
  createApp(JoinApp, { config }).provide(I18N, createI18n(config.texts, config.lang)).mount(el);
}
