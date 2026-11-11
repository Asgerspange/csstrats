<template>        
    <div class="flex items-center justify-center min-h-[60vh] w-full p-6">
      <div class="max-w-md w-full text-center">
        <div v-if="appCode === AppErrorCode.BANNED" class="error-state">
            <h2 class="text-2xl font-bold text-red-600">Account Banned</h2>
            <p class="text-gray-600">Your account has been banned. Please contact support for more information.</p>
            <p v-if="supportId" class="text-sm text-gray-500 mt-2">Support ID: {{ supportId }}</p>
        </div>
        
        <div v-if="appCode === AppErrorCode.NO_SUBSCRIPTION" class="plan-card-container">
          <h2 class="text-2xl font-bold text-yellow-600 mb-4">Subscription Required or Team Access</h2>
          <p class="text-gray-600 mb-6">You need an active subscription or team access to access this content. Upgrade now to unlock all features!</p>

          <Button as="a" href="/#pricing">
            View Pricing
          </Button>
        </div>

        <div v-else-if="appCode === AppErrorCode.NO_TEAM_ACCESS" class="error-state">
          <h2 class="text-2xl font-bold text-red-600">No Team Access</h2>
          <p class="text-gray-600">You don't have access to this team.</p>
        </div>

        <div v-else-if="appCode === AppErrorCode.NO_STRATBOOK_ACCESS" class="error-state">
          <h2 class="text-2xl font-bold text-orange-600">Restricted Stratbook</h2>
          <p class="text-gray-600">You don't have access to this stratbook.</p>
        </div>

        <div v-else-if="appCode === AppErrorCode.NO_DEMO_ACCESS" class="error-state">
          <h2 class="text-2xl font-bold text-blue-600">Private Demo</h2>
          <p class="text-gray-600">You don't have access to this Demo.</p>
        </div>

        <div v-else-if="!appCode">
          <h2 class="text-2xl font-bold">Access Denied</h2>
          <p>Something went wrong with your permissions.</p>
        </div>

        <Button as="a" href="/" severity="secondary" class="mt-2">
          Go to Homepage
        </Button>
      </div>
    </div>

</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { AppErrorCode } from '@/constants/errorCodes';
import Button from '@/components/volt/Button.vue';

const route = useRoute();
const appCode = computed(() => parseInt(route.query.code));
</script>