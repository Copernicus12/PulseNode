import { createApp } from 'vue';
import SchedulesCreateDialog from '@/components/devices/SchedulesCreateDialog.vue';
import { initializeTheme } from '@/composables/useAppearance';

initializeTheme();

const host = document.getElementById('schedules-page-root');
const payload = document.getElementById('schedules-page-props');

if (host && payload?.textContent) {
    try {
        const props = JSON.parse(payload.textContent);
        createApp(SchedulesCreateDialog, props).mount(host);
    } catch (error) {
        console.error('Unable to mount schedules page', error);
    }
}
