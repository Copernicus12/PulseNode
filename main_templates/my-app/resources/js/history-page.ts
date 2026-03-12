import { createApp } from 'vue'
import HistoryHomeView from '@/components/history/HistoryHomeView.vue'

const host = document.getElementById('history-page-root')
const payload = document.getElementById('history-page-props')

if (host && payload?.textContent) {
  try {
    const props = JSON.parse(payload.textContent)
    createApp(HistoryHomeView, props).mount(host)
  } catch (error) {
    console.error('Unable to mount history page', error)
  }
}
