<template>
  <div class="min-h-screen bg-base-200 flex flex-col">
    <!-- ナビゲーションバー -->
    <NavigationBar :menuItems="menuItems" />

    <!-- メインコンテンツ -->
    <main class="container mx-auto px-4 py-8 flex-1">
      <slot />
    </main>

    <!-- フッター（オプション） -->
    <footer v-if="showFooter" class="footer footer-center p-10 bg-base-300 text-base-content rounded">
      <nav class="grid grid-flow-col gap-4">
        <Link href="/about" class="link link-hover">About</Link>
        <Link href="/contact" class="link link-hover">Contact</Link>
        <Link href="/privacy" class="link link-hover">Privacy</Link>
        <Link href="/terms" class="link link-hover">Terms</Link>
      </nav>
      <aside>
        <p>Copyright © {{ currentYear }} - BookStand. All rights reserved.</p>
      </aside>
    </footer>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import NavigationBar from '@/Components/NavigationBar.vue';

const props = defineProps({
  menuItems: {
    type: Array,
    default: () => [
      { name: '今日の本', href: '/today' },
      { name: '明日の本', href: '/tomorrow' }
    ]
  },
  showFooter: {
    type: Boolean,
    default: true
  }
});

const currentYear = computed(() => new Date().getFullYear());
</script>
