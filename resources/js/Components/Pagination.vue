<script setup>
/**
 * 共通ページネーションコンポーネント (Pagination.vue)
 *
 * Laravel の LengthAwarePaginator が生成する `books.links` 配列を受け取り、
 * DaisyUI の join ボタングループとしてページ番号・前へ・次へボタンを描画します。
 */
import { router } from '@inertiajs/vue3';

defineProps({
  links: {
    type: Array,
    default: () => [],
  },
});

/**
 * 指定されたページURLへ Inertia でSPA遷移する
 */
const goToPage = (url) => {
  if (url) {
    router.visit(url, {
      preserveScroll: true,
      preserveState: true,
    });
  }
};
</script>

<template>
  <!-- links が3要素以下（前へ・1ページ目・次へのみ＝全1ページ）の場合は非表示 -->
  <div class="flex justify-center mt-8" v-if="links && links.length > 3">
    <div class="join flex-wrap justify-center">
      <template v-for="(link, i) in links" :key="i">
        <button
          v-if="link.url"
          type="button"
          @click="goToPage(link.url)"
          class="join-item btn btn-sm sm:btn-md"
          :class="{ 'btn-active btn-primary': link.active }"
          v-html="link.label"
        ></button>
        <button
          v-else
          type="button"
          class="join-item btn btn-sm sm:btn-md btn-disabled"
          v-html="link.label"
        ></button>
      </template>
    </div>
  </div>
</template>
