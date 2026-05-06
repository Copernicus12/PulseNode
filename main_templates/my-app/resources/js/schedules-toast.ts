import { createApp } from 'vue';

import SchedulesSonner from '@/components/devices/SchedulesSonner.vue';
import { initializeTheme } from '@/composables/useAppearance';

initializeTheme();

const host = document.getElementById('schedules-toast-root');

if (host) {
    createApp(SchedulesSonner).mount(host);
}
