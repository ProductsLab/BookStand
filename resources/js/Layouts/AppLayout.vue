<template>
  <!-- アプリケーション全体の共通レイアウト枠 -->
  <div class="min-h-screen bg-base-200 flex flex-col">
    <!-- 上部ナビゲーションバー -->
    <NavigationBar :menuItems="menuItems" />

    <!-- 各ページのメインコンテンツ挿入枠 (<slot />) -->
    <main class="container mx-auto px-4 py-8 flex-1">
      <slot />
    </main>

    <!-- 下部フッター -->
    <footer v-if="showFooter" class="footer footer-center p-10 bg-base-300 text-base-content rounded">
      <nav class="grid grid-flow-col gap-6">
        <Link
          v-for="item in menuItems"
          :key="item.name"
          :href="item.href"
          class="link link-hover"
        >
          {{ item.name }}
        </Link>
      </nav>
      <aside>
        <p>Copyright © {{ currentYear }} - BookStand. All rights reserved.</p>
      </aside>
    </footer>
  </div>
</template>

<script setup>
/**
 * 共通アプリケーションレイアウト (AppLayout.vue)
 *
 * ナビゲーションバー、メインコンテンツ領域、フッターを統一的に配置するラッパーコンポーネントです。
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import NavigationBar from '@/Components/NavigationBar.vue';

const props = defineProps({
  // ナビゲーションバーとフッターに表示するメニューリンク一覧
  menuItems: {
    type: Array,
    default: () => [
      { name: '本を探す', href: '/' },
      { name: '今日の本', href: '/today' },
      { name: '明日の本', href: '/tomorrow' }
    ]
  },
  // フッターの表示有無
  showFooter: {
    type: Boolean,
    default: true
  }
});

// コピーライト表記用の現在年
const currentYear = computed(() => new Date().getFullYear());
</script>
