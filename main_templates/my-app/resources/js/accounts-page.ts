import { createApp } from 'vue'
import AccountsManagementView from '@/components/accounts/AccountsManagementView.vue'

const host = document.getElementById('accounts-page-root')
const payload = document.getElementById('accounts-page-props')

if (host && payload?.textContent) {
  try {
    const props = JSON.parse(payload.textContent)
    createApp(AccountsManagementView, props).mount(host)
  } catch (error) {
    console.error('Unable to mount accounts page', error)
  }
}
