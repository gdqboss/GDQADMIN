<script setup>
</script>

<template>
  <div class="min-h-screen flex flex-col bg-gradient-to-br from-slate-50 to-blue-50 relative">
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-[10%] -right-[10%] w-[50%] h-[50%] rounded-full bg-primary/5 blur-[100px]"></div>
      <div class="absolute -bottom-[10%] -left-[10%] w-[40%] h-[40%] rounded-full bg-primary/10 blur-[80px]"></div>
    </div>
    <main class="relative z-10 flex-grow flex items-center justify-center p-4 sm:p-6 lg:p-8">
      <router-view />
    </main>
    <footer class="relative z-10 py-6 text-center">
      <p class="text-xs text-slate-400">&copy; 2024 GDQ Trading. All rights reserved.</p>
    </footer>
  </div>
</template>
