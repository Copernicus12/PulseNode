import { createApp } from 'vue'
import PowerStripGuardNotifications from '@/components/power-strip/PowerStripGuardNotifications.vue'
import { initializeTheme } from '@/composables/useAppearance'

initializeTheme()

const host = document.getElementById('powerstrip-notifications-root')

if (host) {
  createApp(PowerStripGuardNotifications).mount(host)
}
