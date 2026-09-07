import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { useThemeStore } from './stores/theme'

const app = createApp(App)

app.use(createPinia())
app.use(router)

// Sincroniza el store de tema con lo que ya se aplico en index.html (evita el flash)
useThemeStore().init()

app.mount('#app')
