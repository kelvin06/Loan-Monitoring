<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { Role } from '@loan-monitoring/shared-types'
import { ApiClientError } from '@loan-monitoring/api-client'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const role = computed<Role>(() => route.meta.role === 'admin' ? 'admin' : 'user')
const email = ref('')
const password = ref('')
const passwordVisible = ref(false)
const error = ref('')
const busy = ref(false)

async function submit() {
  busy.value = true
  error.value = ''
  try {
    const user = await auth.login(role.value, { email: email.value, password: password.value })
    await router.replace(user.role === 'admin' ? '/admin/dashboard' : '/dashboard')
  } catch (reason) {
    error.value = reason instanceof ApiClientError ? reason.message : 'Unable to sign in. Please try again.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <main class="auth-layout">
    <section class="auth-panel" :class="`auth-panel--${role}`">
      <aside class="auth-aside" aria-label="Loan Monitor">
        <RouterLink class="auth-brand" to="/login" aria-label="Loan Monitor home">
          <span class="brand-mark" aria-hidden="true">L</span>
          <span>Loan Monitor</span>
        </RouterLink>

        <div class="auth-aside-copy">
          <span class="auth-kicker">{{ role === 'admin' ? 'ADMIN WORKSPACE' : 'YOUR FINANCIAL OVERVIEW' }}</span>
          <h2>{{ role === 'admin' ? 'Keep every account in view.' : 'Stay on top of your loans.' }}</h2>
          <p>{{ role === 'admin'
            ? 'Manage accounts and keep your loan operations moving with confidence.'
            : 'A clear view of your account helps you keep each payment on track.' }}</p>
        </div>

        <div class="auth-illustration" aria-hidden="true">
          <div class="illustration-orbit illustration-orbit--outer"></div>
          <div class="illustration-orbit illustration-orbit--inner"></div>
          <div class="illustration-card">
            <span class="illustration-card-label">ACCOUNT OVERVIEW</span>
            <span class="illustration-card-line illustration-card-line--wide"></span>
            <span class="illustration-card-line"></span>
            <span class="illustration-card-footer"><i></i><i></i><i></i><i></i><i></i><i></i></span>
          </div>
          <span class="illustration-dot illustration-dot--one"></span>
          <span class="illustration-dot illustration-dot--two"></span>
        </div>

        <p class="auth-aside-foot">A simpler way to monitor what matters.</p>
      </aside>

      <div class="auth-main">
        <div class="auth-form-wrap">
          <p class="eyebrow">{{ role === 'admin' ? 'ADMINISTRATOR ACCESS' : 'WELCOME BACK' }}</p>
          <h1>{{ role === 'admin' ? 'Sign in to your workspace' : 'Sign in to your account' }}</h1>
          <p class="auth-intro">{{ role === 'admin'
            ? 'Enter your administrator credentials to continue.'
            : 'Enter your details below to access your account.' }}</p>

          <form class="stack auth-form" @submit.prevent="submit">
            <label for="login-email">Email address</label>
            <input
              id="login-email"
              v-model.trim="email"
              type="email"
              autocomplete="username"
              placeholder="you@example.com"
              required
            >

            <div class="password-label-row">
              <label for="login-password">Password</label>
            </div>
            <div class="password-field">
              <input
                id="login-password"
                v-model="password"
                :type="passwordVisible ? 'text' : 'password'"
                autocomplete="current-password"
                placeholder="Enter your password"
                required
              >
              <button
                class="password-toggle"
                type="button"
                :aria-label="passwordVisible ? 'Hide password' : 'Show password'"
                :aria-pressed="passwordVisible"
                @click="passwordVisible = !passwordVisible"
              >{{ passwordVisible ? 'Hide' : 'Show' }}</button>
            </div>

            <p v-if="error" class="error auth-error" role="alert">{{ error }}</p>
            <button class="auth-submit" type="submit" :disabled="busy">
              <span>{{ busy ? 'Signing in…' : 'Sign in' }}</span>
              <span aria-hidden="true">→</span>
            </button>
          </form>

          <p class="auth-switch">
            {{ role === 'admin' ? 'Looking for your personal account?' : 'Are you an administrator?' }}
            <RouterLink :to="role === 'admin' ? '/login' : '/admin/login'">
              {{ role === 'admin' ? 'User sign in' : 'Administrator sign in' }}
            </RouterLink>
          </p>
        </div>
        <p class="auth-legal">Secure access to your Loan Monitor account</p>
      </div>
    </section>
  </main>
</template>

<style scoped>
.auth-layout {
  display: grid;
  height: 100vh;
  height: 100dvh;
  padding: 0 clamp(16px, 3vw, 42px);
  overflow: hidden;
  background:
    radial-gradient(ellipse at 14% 10%, #deedf4 0, transparent 36%),
    #f4f7fa;
}

.auth-panel {
  display: grid;
  grid-template-columns: minmax(320px, .9fr) minmax(420px, 1.1fr);
  width: min(1120px, 100%);
  height: min(720px, 100dvh);
  min-height: 0;
  overflow: hidden;
  border: 1px solid #e4ebef;
  border-radius: 26px;
  background: #fff;
  box-shadow: 0 28px 80px #17364d14, 0 3px 10px #17364d08;
}

.auth-aside {
  position: relative;
  display: flex;
  min-height: 0;
  flex-direction: column;
  overflow: hidden;
  padding: clamp(28px, 4vw, 48px);
  background: linear-gradient(145deg, #113a58 0%, #174e70 58%, #226e82 100%);
  color: #fff;
}

.auth-aside::before,
.auth-aside::after {
  position: absolute;
  z-index: 0;
  border: 1px solid #ffffff16;
  border-radius: 50%;
  content: '';
  pointer-events: none;
}

.auth-aside::before { width: 440px; height: 440px; right: -250px; top: 160px; }
.auth-aside::after { width: 320px; height: 320px; right: -190px; top: 220px; }

.auth-brand,
.auth-aside-copy,
.auth-illustration,
.auth-aside-foot { position: relative; z-index: 1; }

.auth-brand {
  display: inline-flex;
  width: fit-content;
  align-items: center;
  gap: 11px;
  color: #fff;
  font-size: 17px;
  font-weight: 750;
  letter-spacing: -.02em;
  text-decoration: none;
}

.brand-mark {
  display: grid;
  width: 36px;
  height: 36px;
  place-items: center;
  border: 1px solid #ffffff36;
  border-radius: 11px;
  background: #ffffff18;
  color: #d7f4ed;
  font-size: 19px;
}

.auth-aside-copy { margin-top: clamp(56px, 9vh, 92px); max-width: 390px; }
.auth-kicker { color: #a8dacf; font-size: 10px; font-weight: 750; letter-spacing: .16em; }
.auth-aside-copy h2 { max-width: 360px; margin: 15px 0 12px; font-size: clamp(30px, 3.5vw, 43px); line-height: 1.12; letter-spacing: -.045em; }
.auth-aside-copy p { max-width: 340px; margin: 0; color: #d0e0e8; font-size: 15px; line-height: 1.7; }

.auth-illustration { position: relative; height: 190px; margin: auto 0 14px; }
.illustration-orbit { position: absolute; border: 1px solid #ffffff22; border-radius: 50%; }
.illustration-orbit--outer { width: 210px; height: 210px; right: 0; bottom: -84px; }
.illustration-orbit--inner { width: 150px; height: 150px; right: 30px; bottom: -54px; }
.illustration-card { position: absolute; right: 22px; bottom: 15px; width: min(270px, 82%); padding: 18px; border: 1px solid #ffffff30; border-radius: 16px; background: linear-gradient(135deg, #ffffff25, #ffffff0d); box-shadow: 0 18px 44px #092a3c42; backdrop-filter: blur(12px); transform: rotate(-4deg); }
.illustration-card-label { display: block; margin-bottom: 17px; color: #d1e8e8; font-size: 9px; font-weight: 750; letter-spacing: .14em; }
.illustration-card-line { display: block; width: 42%; height: 7px; margin-top: 9px; border-radius: 8px; background: #ffffff70; }
.illustration-card-line--wide { width: 72%; height: 11px; background: #e9f3ee; }
.illustration-card-footer { display: flex; align-items: end; gap: 7px; height: 40px; margin-top: 12px; }
.illustration-card-footer i { display: block; width: 17px; height: 18px; border-radius: 5px 5px 2px 2px; background: #a4d8c8; opacity: .9; }
.illustration-card-footer i:nth-child(2), .illustration-card-footer i:nth-child(5) { height: 27px; }
.illustration-card-footer i:nth-child(3), .illustration-card-footer i:nth-child(6) { height: 35px; background: #f1cb8d; }
.illustration-dot { position: absolute; display: block; border-radius: 50%; background: #e9c986; box-shadow: 0 0 0 8px #e9c98620; }
.illustration-dot--one { right: 72px; bottom: 154px; width: 9px; height: 9px; }
.illustration-dot--two { right: 295px; bottom: 34px; width: 6px; height: 6px; background: #a8dacf; box-shadow: 0 0 0 7px #a8dacf20; }
.auth-aside-foot { margin: 0; color: #b5d0db; font-size: 12px; }
.auth-panel--admin .auth-aside { background: linear-gradient(145deg, #263b59 0%, #385779 58%, #567b8b 100%); }
.auth-panel--admin .auth-kicker { color: #d6c99c; }

.auth-main { display: flex; flex-direction: column; justify-content: center; padding: clamp(36px, 6vw, 78px); }
.auth-form-wrap { width: min(100%, 390px); margin: 0 auto; }
.auth-main .eyebrow { margin-bottom: 12px; color: #2a766b; font-size: 10px; letter-spacing: .16em; }
.auth-main h1 { margin: 0; color: #142b3b; font-size: clamp(27px, 3vw, 34px); letter-spacing: -.04em; line-height: 1.18; }
.auth-intro { margin: 11px 0 0; color: #6a7b88; font-size: 14px; line-height: 1.6; }
.auth-form { gap: 9px; margin: 32px 0 0; }
.auth-form label { color: #2c4050; font-size: 13px; font-weight: 700; }
.auth-form > input, .password-field input { min-height: 48px; border-color: #d8e1e7; border-radius: 10px; background: #fbfcfd; padding: 12px 14px; font-size: 14px; transition: border-color .16s ease, box-shadow .16s ease, background .16s ease; }
.auth-form > input { margin-bottom: 9px; }
.auth-form input::placeholder { color: #a1adb7; }
.auth-form > input:focus, .password-field input:focus { border-color: #3b8a84; outline: 0; background: #fff; box-shadow: 0 0 0 4px #3b8a8418; }
.password-label-row { display: flex; justify-content: space-between; }
.password-field { position: relative; }
.password-field input { width: 100%; padding-right: 62px; }
.password-toggle { position: absolute; top: 50%; right: 8px; padding: 7px 8px; transform: translateY(-50%); border-radius: 7px; background: transparent; color: #527080; font-size: 12px; font-weight: 700; }
.password-toggle:hover { background: #edf4f5; color: #174e70; }
.auth-error { margin: 3px 0 0; padding: 11px 12px; border: 1px solid #f0ccca; border-radius: 9px; background: #fff5f4; font-size: 13px; line-height: 1.5; }
.auth-submit { display: flex; min-height: 50px; align-items: center; justify-content: space-between; margin-top: 13px; padding: 0 16px; border-radius: 10px; background: #17665f; box-shadow: 0 5px 12px #17665f24; font-size: 14px; }
.auth-submit:hover { background: #12564f; }
.auth-submit span:last-child { font-size: 19px; font-weight: 400; transition: transform .16s ease; }
.auth-submit:hover span:last-child { transform: translateX(3px); }
.auth-switch { margin: 24px 0 0; color: #71818b; font-size: 13px; text-align: center; }
.auth-switch a { margin-left: 4px; color: #17665f; font-weight: 700; text-decoration: none; }
.auth-switch a:hover { text-decoration: underline; }
.auth-legal { margin: 52px auto 0; color: #98a5ad; font-size: 11px; text-align: center; }

/* Keep authentication screens aligned with the shared palette. */
.auth-panel { border-color: var(--border); border-radius: 20px; }
.auth-aside, .auth-panel--admin .auth-aside { background: var(--accent); }
.auth-kicker, .auth-panel--admin .auth-kicker, .brand-mark { color: var(--accent-soft); }
.auth-main .eyebrow, .password-toggle, .auth-switch a { color: var(--accent); }
.auth-form > input, .password-field input { min-height: 42px; border-color: var(--border); border-radius: 8px; background: var(--surface); color: var(--text-primary); }
.auth-form > input:focus, .password-field input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 24%, transparent); }
.password-toggle:hover { background: var(--accent-soft); color: var(--accent); }
.auth-submit { min-height: 44px; border-radius: 8px; background: var(--accent); box-shadow: none; }
.auth-submit:hover { background: var(--accent-hover); }
.illustration-card-footer i { background: var(--accent); }
.illustration-card-footer i:nth-child(3), .illustration-card-footer i:nth-child(6) { background: var(--accent-hover); }
.illustration-dot { background: var(--accent); }
.illustration-dot--two { background: var(--accent-hover); }

/* Remove the enclosing card so the login layout sits directly on the page. */
.auth-layout { background: var(--background); }
.auth-panel { border: 0; border-radius: 0; background: transparent; box-shadow: none; }
.auth-aside { background: var(--accent); }
.auth-aside::before, .auth-aside::after { display: none; }
.auth-panel--admin .auth-aside { background: var(--accent); }
.brand-mark { border-radius: 6px; background: #ffffff12; }
.illustration-card { border-radius: 8px; background: #ffffff12; box-shadow: none; backdrop-filter: none; transform: none; }
.illustration-orbit, .illustration-dot { display: none; }
.auth-submit { border-radius: 6px; box-shadow: none; }
.auth-form > input, .password-field input { border-radius: 6px; background: #fff; }
.password-toggle { border-radius: 4px; }

@media (min-width: 761px) {
  .auth-panel { grid-template-columns: minmax(300px, .82fr) minmax(420px, 1fr); }
  .auth-aside { padding: clamp(30px, 4vw, 52px); }
  .auth-main { background: transparent; }
}

@media (max-width: 760px) {
  .auth-layout { height: 100dvh; padding: 0; }
  .auth-panel { height: 100dvh; min-height: 0; grid-template-columns: 1fr; border: 0; border-radius: 0; }
  .auth-aside { min-height: 0; padding: 18px 26px 20px; }
  .auth-brand { font-size: 15px; }
  .brand-mark { width: 32px; height: 32px; }
  .auth-aside-copy { margin-top: 22px; }
  .auth-aside-copy h2 { max-width: 420px; margin: 9px 0 7px; font-size: clamp(25px, 7vw, 34px); }
  .auth-aside-copy p { max-width: 460px; font-size: 13px; }
  .auth-illustration { position: absolute; right: 3px; bottom: 0; width: 180px; height: 130px; margin: 0; opacity: .48; }
  .illustration-card { right: 8px; bottom: 10px; width: 146px; padding: 12px; }
  .illustration-card-footer { height: 24px; }
  .illustration-card-footer i { width: 12px; height: 12px; }
  .illustration-card-footer i:nth-child(2), .illustration-card-footer i:nth-child(5) { height: 18px; }
  .illustration-card-footer i:nth-child(3), .illustration-card-footer i:nth-child(6) { height: 23px; }
  .illustration-orbit--outer { width: 130px; height: 130px; right: -20px; bottom: -36px; }
  .illustration-orbit--inner { width: 95px; height: 95px; right: -2px; bottom: -18px; }
  .illustration-dot--one { right: 44px; bottom: 101px; }
  .illustration-dot--two, .auth-aside-foot { display: none; }
  .auth-main { justify-content: center; padding: 22px 26px; }
  .auth-form-wrap { width: min(100%, 440px); }
  .auth-form { margin-top: 20px; }
  .auth-legal { margin-top: 22px; }
}

@media (max-width: 380px) {
  .auth-aside { padding-right: 18px; padding-left: 18px; }
  .auth-main { padding-right: 18px; padding-left: 18px; }
  .auth-illustration { right: -32px; opacity: .32; }
}

@media (max-height: 680px) {
  .auth-layout { padding-top: 12px; padding-bottom: 12px; }
  .auth-panel { height: calc(100dvh - 24px); }
  .auth-aside { padding-top: 24px; padding-bottom: 24px; }
  .auth-aside-copy { margin-top: 36px; }
  .auth-illustration { height: 145px; }
  .auth-legal { margin-top: 28px; }
}

@media (max-width: 760px) and (max-height: 760px) {
  .auth-aside-copy { margin-top: 12px; }
  .auth-aside-copy p { max-width: 80%; }
  .auth-illustration { opacity: .24; }
  .auth-main { padding-top: 14px; padding-bottom: 14px; }
  .auth-legal { margin-top: 14px; }
}

@media (prefers-color-scheme: dark) {
  .auth-layout { background: var(--background); color: var(--text-primary); }
  .auth-main, .auth-panel { background: var(--background); }
  .auth-aside, .auth-panel--admin .auth-aside { background: var(--surface); }
  .auth-main .eyebrow, .auth-switch a, .password-toggle { color: var(--accent); }
  .auth-main h1, .auth-form label { color: var(--text-primary); }
  .auth-intro, .auth-switch, .auth-legal { color: var(--text-secondary); }
  .auth-form > input, .password-field input { border-color: var(--border); background: var(--surface); color: var(--text-primary); }
  .auth-form input::placeholder { color: var(--text-secondary); }
  .auth-form > input:focus, .password-field input:focus { border-color: var(--accent); background: var(--surface); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 25%, transparent); }
  .auth-submit { background: var(--accent); color: #fff; }
  .auth-submit:hover { background: var(--accent-hover); }
  .password-toggle:hover { background: var(--accent-soft); color: var(--accent-hover); }
  .auth-kicker, .auth-panel--admin .auth-kicker, .brand-mark { color: var(--accent); }
  .illustration-card { border-color: var(--border); background: var(--surface); }
  .illustration-card-label, .auth-aside-copy p, .auth-aside-foot { color: var(--text-secondary); }
  .illustration-card-line, .illustration-card-line--wide,
  .illustration-card-footer i, .illustration-card-footer i:nth-child(3),
  .illustration-card-footer i:nth-child(6) { background: var(--accent); }
}

@media (max-width: 760px) {
  .auth-layout { height: auto; min-height: 100dvh; overflow-x: hidden; overflow-y: auto; align-items: start; }
  .auth-panel { height: auto; min-height: 100dvh; overflow: visible; grid-template-rows: auto 1fr; }
  .auth-aside { min-height: 210px; }
  .auth-main { padding-top: clamp(20px, 6vh, 42px); padding-bottom: clamp(20px, 6vh, 42px); }
}

@media (max-width: 760px) and (max-height: 600px) {
  .auth-aside { min-height: 175px; padding-top: 14px; padding-bottom: 14px; }
  .auth-aside-copy { margin-top: 8px; }
  .auth-aside-copy h2 { font-size: clamp(22px, 6vw, 30px); }
  .auth-illustration { display: none; }
}
</style>
