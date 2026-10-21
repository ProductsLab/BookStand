<script setup>
/**
 * 本を探すページ（トップページ & 検索画面: Welcome.vue）
 *
 * 旧リポジトリの `book_search.php` / `search.php` に相当する画面です。
 * - 複数条件を追加・削除できるキーワード検索（書名・内容・著者名・発行元出版社・出版日・ISBN）
 * - 条件間の AND / OR 論理演算子切り替え
 * - Cコード（対象・形態・内容）によるジャンル絞り込みと「未分類を含める」チェックボックス
 * - 検索結果のカード一覧表示およびページネーション
 * を提供します。
 */
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import qs from 'qs';
import AppLayout from '@/Layouts/AppLayout.vue';
import BookCard from '@/Components/BookCard.vue';
import Pagination from '@/Components/Pagination.vue';
import {
  C_CODE_TARGETS,
  C_CODE_FORMS,
  C_CODE_CONTENTS,
} from '@/constants/cCode';

const props = defineProps({
  // バックエンドの paginate() から渡される書籍データとページネーションリンク
  books: {
    type: Object,
    default: () => ({ data: [], links: [], total: 0 }),
  },
  // URLクエリパラメータから復元された現在の検索フィルター状態
  filters: {
    type: Object,
    default: () => ({
      conditions: [],
      c_code_1: '_',
      c_code_2: '_',
      c_code_3: '__',
      include_unclassified: false,
      c_code_operator: 'AND',
    }),
  },
  laravelVersion: String,
});

/**
 * 新しい空のキーワード検索条件オブジェクトを生成
 */
const createEmptyCondition = () => ({
  type: 'title',
  value: '',
  operator: 'AND',
});

/**
 * サーバーから渡された検索条件配列をフロントエンドのフォーム用に正規化
 */
const normalizeConditions = (rawConditions) => {
  if (!Array.isArray(rawConditions) || rawConditions.length === 0) {
    return [];
  }
  return rawConditions.map((c) => ({
    type: c.type || 'title',
    value: c.value || '',
    operator:
      c.operator && String(c.operator).toUpperCase() === 'OR' ? 'OR' : 'AND',
  }));
};

// 検索フォームのリアクティブステート
const searchConditions = ref(normalizeConditions(props.filters?.conditions));
const cCode1 = ref(props.filters?.c_code_1 || '_'); // Cコード1桁目（対象）
const cCode2 = ref(props.filters?.c_code_2 || '_'); // Cコード2桁目（形態）
const cCode3 = ref(props.filters?.c_code_3 || '__'); // Cコード3〜4桁目（内容）
const includeUnclassified = ref(Boolean(props.filters?.include_unclassified)); // 未分類（9999等）を含めるか
const cCodeOperator = ref(props.filters?.c_code_operator || 'AND'); // キーワード条件とCコード条件の結合演算子

// ブラウザの戻る/進む操作やURL変更時に props.filters が更新されたらフォーム状態も同期する
watch(
  () => props.filters,
  (newFilters) => {
    if (!newFilters) return;
    searchConditions.value = normalizeConditions(newFilters.conditions);
    cCode1.value = newFilters.c_code_1 || '_';
    cCode2.value = newFilters.c_code_2 || '_';
    cCode3.value = newFilters.c_code_3 || '__';
    includeUnclassified.value = Boolean(newFilters.include_unclassified);
    cCodeOperator.value = newFilters.c_code_operator || 'AND';
  },
  { deep: true },
);

/**
 * キーワード検索条件の入力行を1つ追加
 */
const addCondition = () => {
  searchConditions.value.push(createEmptyCondition());
};

/**
 * 指定インデックスのキーワード検索条件行を削除
 */
const removeCondition = (index) => {
  searchConditions.value.splice(index, 1);
};

/**
 * すべての検索条件（キーワード・Cコード）を初期状態にリセットして再取得
 */
const resetConditions = () => {
  searchConditions.value = [];
  cCode1.value = '_';
  cCode2.value = '_';
  cCode3.value = '__';
  includeUnclassified.value = false;
  cCodeOperator.value = 'AND';
  router.get('/', {}, { preserveState: true });
};

/**
 * 検索実行ハンドラ
 * 有効な検索条件のみを抽出し、qs でネスト配列をクエリ文字列化して `/books/search` へGETリクエストを送る
 */
const search = () => {
  // 検索値が空欄の条件行は除外
  const validConditions = searchConditions.value.filter(
    (condition) => condition.value && String(condition.value).trim() !== '',
  );

  const searchData = {};

  if (validConditions.length > 0) {
    searchData.conditions = validConditions.map((condition, index) => ({
      type: condition.type,
      value: String(condition.value).trim(),
      operator:
        index < validConditions.length - 1
          ? condition.operator || 'AND'
          : 'AND',
    }));
  }

  // Cコード（対象・形態・内容）のいずれかが選択されている場合のみクエリに含める
  const hasCCode =
    cCode1.value !== '_' || cCode2.value !== '_' || cCode3.value !== '__';

  if (hasCCode) {
    searchData.c_code_1 = cCode1.value;
    searchData.c_code_2 = cCode2.value;
    searchData.c_code_3 = cCode3.value;
    if (includeUnclassified.value) {
      searchData.include_unclassified = 1;
    }
    if (validConditions.length > 0) {
      searchData.c_code_operator = cCodeOperator.value;
    }
  }

  const queryString = qs.stringify(searchData);
  const url = queryString ? `/books/search?${queryString}` : '/books/search';

  router.get(
    url,
    {},
    {
      preserveState: true,
      replace: true,
    },
  );
};
</script>

<template>
  <Head title="本を探す" />
  <AppLayout>
    <div class="max-w-6xl mx-auto">
      <!-- 検索フォームカード -->
      <div class="card bg-base-100 shadow-xl mb-8 border border-base-300">
        <div class="card-body">
          <h1 class="card-title text-2xl sm:text-3xl mb-4">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
              class="w-7 h-7 sm:w-8 sm:h-8 text-primary"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"
              />
            </svg>
            書籍検索
          </h1>

          <form @submit.prevent="search">
            <!-- キーワード検索条件リスト -->
            <div class="space-y-4">
              <!-- 条件行が0個の場合のガイド表示 -->
              <div
                v-if="searchConditions.length === 0"
                class="alert bg-base-200/70 text-base-content border border-base-300"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  class="stroke-info shrink-0 w-6 h-6"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  ></path>
                </svg>
                <span class="text-sm">
                  キーワード検索条件が設定されていません。「条件を追加」ボタンで条件を追加するか、下のジャンル（Cコード）で絞り込み検索ができます。
                </span>
              </div>

              <!-- 動的に追加された各キーワード検索条件行 -->
              <div
                v-for="(condition, index) in searchConditions"
                :key="index"
                class="border border-base-300 rounded-lg p-4 bg-base-200/40"
              >
                <div class="flex flex-col md:flex-row md:items-end gap-4">
                  <!-- 検索種別セレクトボックス -->
                  <div class="form-control md:w-48">
                    <label class="label">
                      <span class="label-text font-semibold">検索種別</span>
                    </label>
                    <select
                      v-model="condition.type"
                      class="select select-bordered w-full"
                    >
                      <option value="title">書名</option>
                      <option value="content">内容</option>
                      <option value="author">著者名</option>
                      <option value="publisher">発行元出版社</option>
                      <option value="published_date">出版日</option>
                      <option value="isbn">ISBN</option>
                    </select>
                  </div>

                  <!-- 検索キーワードまたは日付入力欄 -->
                  <div class="form-control flex-1">
                    <label class="label">
                      <span class="label-text font-semibold">検索値</span>
                    </label>
                    <input
                      v-if="condition.type !== 'published_date'"
                      v-model="condition.value"
                      type="text"
                      placeholder="キーワードを入力（スペース区切りで複数指定可）"
                      class="input input-bordered w-full"
                    />
                    <input
                      v-else
                      v-model="condition.value"
                      type="date"
                      class="input input-bordered w-full"
                    />
                  </div>

                  <!-- 次の条件との論理演算子 (最後の条件行以外に表示) -->
                  <div
                    class="form-control"
                    v-if="index < searchConditions.length - 1"
                  >
                    <label class="label">
                      <span class="label-text font-semibold">条件結合</span>
                    </label>
                    <div class="flex gap-3 items-center h-10">
                      <label class="label cursor-pointer gap-1.5">
                        <input
                          type="radio"
                          :name="'operator-' + index"
                          value="AND"
                          v-model="condition.operator"
                          class="radio radio-primary radio-sm"
                        />
                        <span class="label-text font-medium">AND</span>
                      </label>
                      <label class="label cursor-pointer gap-1.5">
                        <input
                          type="radio"
                          :name="'operator-' + index"
                          value="OR"
                          v-model="condition.operator"
                          class="radio radio-secondary radio-sm"
                        />
                        <span class="label-text font-medium">OR</span>
                      </label>
                    </div>
                  </div>

                  <!-- 条件行の削除ボタン -->
                  <div class="form-control">
                    <button
                      type="button"
                      @click="removeCondition(index)"
                      class="btn btn-error btn-outline btn-square"
                      title="この条件を削除"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="w-5 h-5"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                        />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- ジャンル（Cコード）絞り込み検索セクション -->
            <div class="mt-6 border border-base-300 rounded-lg p-4 bg-base-200/30">
              <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h2 class="text-base font-bold flex items-center gap-2">
                  <span>ジャンル（Cコード）絞り込み</span>
                </h2>

                <!-- キーワード条件が設定されている場合のみ、結合演算子（AND/OR）を表示 -->
                <div
                  v-if="searchConditions.length > 0"
                  class="flex items-center gap-3 text-sm"
                >
                  <span class="text-base-content/70">キーワード条件との結合:</span>
                  <label class="label cursor-pointer gap-1.5 py-0">
                    <input
                      type="radio"
                      name="c_code_operator"
                      value="AND"
                      v-model="cCodeOperator"
                      class="radio radio-primary radio-xs"
                    />
                    <span class="label-text font-medium">AND</span>
                  </label>
                  <label class="label cursor-pointer gap-1.5 py-0">
                    <input
                      type="radio"
                      name="c_code_operator"
                      value="OR"
                      v-model="cCodeOperator"
                      class="radio radio-secondary radio-xs"
                    />
                    <span class="label-text font-medium">OR</span>
                  </label>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Cコード1桁目：対象 -->
                <div class="form-control">
                  <label class="label">
                    <span class="label-text font-semibold">対象</span>
                  </label>
                  <select v-model="cCode1" class="select select-bordered w-full">
                    <option
                      v-for="item in C_CODE_TARGETS"
                      :key="item.value"
                      :value="item.value"
                    >
                      {{ item.label }}
                    </option>
                  </select>
                </div>

                <!-- Cコード2桁目：形態 -->
                <div class="form-control">
                  <label class="label">
                    <span class="label-text font-semibold">形態</span>
                  </label>
                  <select v-model="cCode2" class="select select-bordered w-full">
                    <option
                      v-for="item in C_CODE_FORMS"
                      :key="item.value"
                      :value="item.value"
                    >
                      {{ item.label }}
                    </option>
                  </select>
                </div>

                <!-- Cコード3〜4桁目：内容 -->
                <div class="form-control">
                  <label class="label">
                    <span class="label-text font-semibold">内容</span>
                  </label>
                  <select v-model="cCode3" class="select select-bordered w-full">
                    <option
                      v-for="item in C_CODE_CONTENTS"
                      :key="item.value"
                      :value="item.value"
                    >
                      {{ item.label }}
                    </option>
                  </select>
                </div>
              </div>

              <!-- Cコード未登録（9999等）の書籍も検索結果に含めるかのトグル -->
              <div class="mt-3 flex items-center">
                <label class="label cursor-pointer gap-2 py-0">
                  <input
                    type="checkbox"
                    v-model="includeUnclassified"
                    class="checkbox checkbox-primary checkbox-sm"
                  />
                  <span class="label-text text-sm">未分類を含める</span>
                </label>
              </div>
            </div>

            <!-- 検索フォーム操作ボタン -->
            <div class="flex flex-wrap gap-3 mt-6">
              <button
                type="button"
                @click="addCondition"
                class="btn btn-outline btn-primary"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                  class="w-5 h-5"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 4.5v15m7.5-7.5h-15"
                  />
                </svg>
                条件を追加
              </button>

              <button
                type="submit"
                class="btn btn-primary flex-1 md:flex-initial px-8"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                  class="w-5 h-5"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"
                  />
                </svg>
                検索実行
              </button>

              <button
                type="button"
                @click="resetConditions"
                class="btn btn-ghost"
              >
                リセット
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- 検索結果表示エリア -->
      <div v-if="books && books.data">
        <!-- ヒット件数バナー -->
        <div
          class="alert alert-success alert-soft border border-success/30 mb-6 p-4 text-base sm:text-lg font-medium"
        >
          <span>検索結果 ({{ Number(books.total || 0).toLocaleString() }}件)</span>
        </div>

        <!-- 0件時のメッセージ -->
        <div v-if="books.data.length === 0" class="alert alert-warning mb-6">
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
          <span>該当する書籍が見つかりませんでした。条件を変更して再検索してください。</span>
        </div>

        <!-- 書籍カードリスト -->
        <div v-else class="flex flex-col gap-4">
          <BookCard v-for="book in books.data" :key="book.id" :book="book" />
        </div>

        <!-- ページネーション -->
        <Pagination :links="books.links" />
      </div>
    </div>
  </AppLayout>
</template>
