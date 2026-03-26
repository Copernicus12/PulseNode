import { createApp } from 'vue'
import NotificationsFilterBar from '@/components/notifications/NotificationsFilterBar.vue'

const host = document.getElementById('notifications-filter-root')
const payload = document.getElementById('notifications-filter-props')

if (host && payload?.textContent) {
    try {
        const props = JSON.parse(payload.textContent)
        createApp(NotificationsFilterBar, props).mount(host)
    } catch (error) {
        console.error('Unable to mount notifications filters', error)
    }
}
