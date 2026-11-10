<script setup lang="ts">
import { computed } from "vue";
import qrcode from "qrcode-generator";

/** QR code as SVG (dark modules in the darkest palette colour) */
const props = defineProps<{ value: string; label: string }>();

const svg = computed(() => {
  const qr = qrcode(0, "M");
  qr.addData(props.value);
  qr.make();
  const n = qr.getModuleCount();
  const quiet = 2;
  let path = "";
  for (let y = 0; y < n; y++) {
    for (let x = 0; x < n; x++) {
      if (qr.isDark(y, x)) {
        path += `M${x + quiet} ${y + quiet}h1v1h-1z`;
      }
    }
  }
  return { size: n + quiet * 2, path };
});
</script>

<template>
  <svg class="uda-qr" :viewBox="`0 0 ${svg.size} ${svg.size}`" role="img" :aria-label="label" shape-rendering="crispEdges">
    <rect :width="svg.size" :height="svg.size" fill="#fff" />
    <path :d="svg.path" class="uda-qr__modules" />
  </svg>
</template>
