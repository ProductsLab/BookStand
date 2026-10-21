<script setup>
/**
 * 明日の本ページ (Tomorrow.vue)
 *
 * 旧リポジトリの `soon_book.php` に相当する画面です。
 * 明日（または日付ピッカーで指定した日）に出版予定の新刊書籍を一覧表示します。
 */
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BookCard from '@/Components/BookCard.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
  // バックエンドから渡される対象日の新刊書籍データ（ページネーション付き）
  books: {
    type: Object,
    default: () => ({ data: [], links: [], total: 0 }),
  },
  // 現在表示対象となっている出版日（'YYYY-MM-DD'）
  targetDate: {
    type: String,
    default: '',
  },
});

// 日付ピッカーの選択値
const selectedDate = ref(props.targetDate);

/**
 * 対象日（YYYY-MM-DD）を画面表示用の日本語表記（YYYY年MM月DD日）に整形
 */
const formattedTargetDate = computed(() => {
  if (!props.targetDate) return '';
  const match = String(props.targetDate).match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (match) {
    return `${match[1]}年${match[2]}月${match[3]}日`;
  }
  return props.targetDate;
});

/**
 * 日付ピッカーが変更された際に、指定日の新刊一覧を再取得する
 */
const changeDate = () => {
  if (selectedDate.value) {
    router.get('/tomorrow', { date: selectedDate.value }, { preserveState: true });
  } else {
    router.get('/tomorrow');
  }
};

/**
 * 日付指定を解除して「明日」の新刊一覧に戻す
 */
const resetToTomorrow = () => {
  router.get('/tomorrow');
};
</script>

<template>
  <Head title="明日の本" />
  <AppLayout>
    <div class="max-w-6xl mx-auto">
      <!-- ページヘッダー & 日付切り替えカード -->
      <div class="card bg-base-100 shadow-md mb-6 border border-base-300">
        <div class="card-body py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="text-2xl sm:text-3xl font-bold flex items-center gap-2.5">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-7 h-7 text-primary"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"
                />
              </svg>
              明日の本
            </h1>
            <p class="text-sm text-base-content/70 mt-1" v-if="formattedTargetDate">
              対象出版日: {{ formattedTargetDate }}
            </p>
          </div>

          <!-- 任意の日付を確認できる日付ピッカー -->
          <div class="flex items-center gap-2">
            <input
              type="date"
              v-model="selectedDate"
              @change="changeDate"
              class="input input-bordered input-sm sm:input-md"
            />
            <button
              type="button"
              @click="resetToTomorrow"
              class="btn btn-outline btn-sm sm:btn-md"
            >
              明日に戻す
            </button>
          </div>
        </div>
      </div>

      <!-- 出版予定件数メッセージ（旧 soon_book.php のアラート表示を踏襲） -->
      <div
        v-if="books.total > 0"
        class="alert alert-success alert-soft border border-success/30 mb-6 p-4 text-base sm:text-lg font-medium"
      >
        <span>
          明日（{{ formattedTargetDate }}）出版予定の本は
          {{ Number(books.total).toLocaleString() }} 件あります。
        </span>
      </div>

      <div v-else class="alert alert-error alert-soft border border-error/30 mb-6 p-4 text-base">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="stroke-current shrink-0 h-6 w-6"
          fill="none"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
          />
        </svg>
        <span>明日（{{ formattedTargetDate }}）出版予定の本はありません。</span>
      </div>

      <!-- 書籍カードリスト -->
      <div v-if="books.data && books.data.length > 0" class="flex flex-col gap-4">
        <BookCard v-for="book in books.data" :key="book.id" :book="book" />
      </div>

      <!-- ページネーション -->
      <Pagination :links="books.links" />
    </div>
  </AppLayout>
</template>
