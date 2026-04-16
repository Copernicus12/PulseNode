import { createApp } from 'vue';
import SafetyGuardFieldForm from '@/components/power-strip/SafetyGuardFieldForm.vue';
import { initializeTheme } from '@/composables/useAppearance';

initializeTheme();

const host = document.getElementById('safety-guard-field-root');

if (host) {
    createApp(SafetyGuardFieldForm).mount(host);
}
