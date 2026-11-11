<template>
  <div>
    <a 
      v-if="!user?.faceit_id"
      href="/auth/faceit/redirect" 
      class="faceit-connect-btn"
    >
        <img src="/images/faceit.png" class="faceit-icon"/>
      Connect Faceit
    </a>

    <div v-else class="faceit-status">
      <p>Connected as: <strong>{{ user?.faceit_nickname }}</strong></p>
    </div>
  </div>
</template>

<script setup>
// Assuming you pass the user object via props or a store (Pinia/Vuex)
const props = defineProps(['user']);
</script>

<style scoped>
.faceit-connect-btn {
  background-color: #ff5500;
  color: white;
  padding: 10px 15px;
  border-radius: 5px;
  text-decoration: none;
  display: flex;
  align-items: center;
}

.faceit-icon {
  height: 20px;
  margin-right: 10px;
  vertical-align: middle;
}
</style>