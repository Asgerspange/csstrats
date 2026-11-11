<script setup lang="ts">
import useUser from '@/composables/useUser';
const { User } = useUser();
const { data: user } = User.get();
</script>

<template>
  <div class="space-y-8 w-full">
    <section>
      <h3 class="text-orange-500 text-xs font-bold uppercase tracking-widest mb-4">Password & Access</h3>
      <div class="p-4 bg-gray-900/30 border border-gray-800 rounded-lg flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-white">Change Password</p>
          <p class="text-xs text-gray-500">Last changed: Unknown</p>
        </div>
        <button class="bg-gray-800 hover:bg-gray-700 text-white text-[10px] font-bold uppercase px-4 py-2 rounded transition-colors">
          Request Reset
        </button>
      </div>
    </section>

    <section>
      <h3 class="text-orange-500 text-xs font-bold uppercase tracking-widest mb-4">Account Identifiers</h3>
      <div class="space-y-4">
        <div v-if="user?.google_id" class="flex items-center gap-3 p-3 bg-blue-900/10 border border-blue-900/20 rounded">
          <div class="text-blue-400 text-lg"><i class="fab fa-google"></i></div>
          <div class="flex-1">
            <p class="text-xs font-bold text-blue-400 uppercase">Google Authenticated</p>
            <p class="text-[10px] text-gray-500">Account linked via Google ID: {{ user?.google_id }}</p>
          </div>
        </div>
      </div>
    </section>

    <section class="pt-6 border-t border-gray-800">
       <h3 class="text-red-500 text-xs font-bold uppercase tracking-widest mb-4">Session Management</h3>
       <div class="flex items-center justify-between p-4 bg-red-950/10 border border-red-900/20 rounded-lg">
          <p class="text-xs text-gray-400">Want to sign out of all other devices?</p>
          <button class="text-red-500 hover:text-red-400 text-[10px] font-bold uppercase tracking-tighter">Log out all sessions</button>
       </div>
    </section>
  </div>
</template>