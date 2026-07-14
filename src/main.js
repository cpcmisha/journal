import '../css/journal.scss'

import { createApp } from 'vue'
import App from './App.vue'
import router from './router.js'

const app = createApp(App)

app.config.devtools = process.env.NODE_ENV === 'development'
app.config.globalProperties.t = t
app.config.globalProperties.n = n

app.use(router)
app.mount('#vue-content')
