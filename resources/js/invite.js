import { createApp } from 'vue'
import InvitePage from './PagesNew/Invite.vue'
import '../css/app.css'
import '../scss/app.scss';
const app = createApp(InvitePage, {
    invite: window.__INVITE__
})

app.mount('#invite-app')