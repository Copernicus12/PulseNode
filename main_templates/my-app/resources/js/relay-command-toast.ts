import { createApp } from 'vue'

import RelayCommandSonner from '@/components/power-strip/RelayCommandSonner.vue'

const host = document.getElementById('relay-command-toast-root')

if (host) {
    let initialGuard = {}

    if (host.dataset.initialGuard) {
        try {
            initialGuard = JSON.parse(host.dataset.initialGuard)
        } catch {
            initialGuard = {}
        }
    }

    createApp(RelayCommandSonner, {
        initialGuard,
    }).mount(host)
}
