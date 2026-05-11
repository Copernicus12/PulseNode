import { createApp } from 'vue';
import SafetyGuardFieldForm from '@/components/power-strip/SafetyGuardFieldForm.vue';
import { initializeTheme } from '@/composables/useAppearance';

initializeTheme();

const host = document.getElementById('safety-guard-field-root');

if (host) {
    const policy = host.dataset.policy ? JSON.parse(host.dataset.policy) : {};
    const saveUrl = host.dataset.saveUrl || '/power-strip/guard-policy';

    createApp(SafetyGuardFieldForm, {
        initialPolicy: policy,
        saveUrl,
    }).mount(host);
}
