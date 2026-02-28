<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import qs from 'qs';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  books: {
    type: Object,
    default: () => ({ data: [], links: [] })
  },
  filters: {
    type: Object,
    default: () => ({ conditions: [] })
  },
  laravelVersion: String
});

// 検索条件の初期値
const createEmptyCondition = () => ({
  type: 'title',
  value: '',
  operator: 'AND'
});

// 検索条件のリスト（初期は空）
const searchConditions = ref([]);

// コンポーネントマウント時にフィルターが渡されていれば反映する
onMounted(() => {
  if (props.filters && props.filters.conditions && props.filters.conditions.length > 0) {
    searchConditions.value = props.filters.conditions;
  }
});

// 条件を追加
const addCondition = () => {
  searchConditions.value.push(createEmptyCondition());
};

// 条件を削除
const removeCondition = (index) => {
  searchConditions.value.splice(index, 1);
};

// 検索条件をリセット
const resetConditions = () => {
  searchConditions.value = [];
  router.get('/', {}, { preserveState: true });
};

// 検索実行
const search = () => {
  // 空の条件を除外
  const validConditions = searchConditions.value.filter(
    condition => condition.value && condition.value.trim() !== ''
  );

  // バックエンドに送信するデータを準備
  const searchData = {
    conditions: validConditions.map((condition, index) => ({
      type: condition.type,
      value: condition.value,
      operator: index < validConditions.length - 1 ? condition.operator : null
    }))
  };

  // Inertia.jsでバックエンドAPIに送信
  router.get(`/books/search?${qs.stringify(searchData)}`, {}, {
    preserveState: true,
    replace: true
  });
};

// ページネーションリンク用ヘルパー
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
  <AppLayout>
    <div class="max-w-6xl mx-auto">
      <!-- 検索セクション -->
      <div class="card bg-base-100 shadow-xl mb-8">
        <div class="card-body">
          <h1 class="card-title text-3xl mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
              stroke="currentColor" class="w-8 h-8">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            書籍検索
          </h1>

          <!-- 検索条件リスト -->
          <div class="space-y-4">
            <!-- 条件が0個の場合 -->
            <div v-if="searchConditions.length === 0" class="alert">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-info shrink-0 w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <span>検索条件が設定されていません。「条件を追加」ボタンで条件を追加するか、「検索実行」で全件検索できます。</span>
            </div>

            <div v-for="(condition, index) in searchConditions" :key="index"
              class="border border-base-300 rounded-lg p-4 bg-base-200/50">
              <div class="flex flex-col md:flex-row md:items-end gap-4">
                <!-- 検索種別 -->
                <div class="form-control flex-1">
                  <label class="label">
                    <span class="label-text font-semibold">検索種別</span>
                  </label>
                  <select v-model="condition.type" class="select select-bordered w-full">
                    <option value="title">書名</option>
                    <option value="content">内容</option>
                    <option value="author">著者名</option>
                    <option value="publisher">発行元出版社</option>
                    <option value="published_date">出版日</option>
                  </select>
                </div>

                <!-- 検索値 -->
                <div class="form-control flex-2">
                  <label class="label">
                    <span class="label-text font-semibold">検索値</span>
                  </label>
                  <input v-if="condition.type !== 'published_date'" v-model="condition.value" type="text"
                    placeholder="検索キーワードを入力" class="input input-bordered w-full" />
                  <input v-else v-model="condition.value" type="date" class="input input-bordered w-full" />
                </div>

                <!-- 演算子 (最後の条件以外) -->
                <div class="form-control" v-if="index < searchConditions.length - 1">
                  <label class="label">
                    <span class="label-text font-semibold">条件</span>
                  </label>
                  <div class="flex gap-2 items-center h-12">
                    <label class="label cursor-pointer gap-2">
                      <input type="radio" :name="'operator-' + index" value="AND" v-model="condition.operator"
                        class="radio radio-primary" />
                      <span class="label-text">AND</span>
                    </label>
                    <label class="label cursor-pointer gap-2">
                      <input type="radio" :name="'operator-' + index" value="OR" v-model="condition.operator"
                        class="radio radio-secondary" />
                      <span class="label-text">OR</span>
                    </label>
                  </div>
                </div>

                <!-- 削除ボタン -->
                <div class="form-control">
                  <label class="label opacity-0">
                    <span class="label-text">削除</span>
                  </label>
                  <button @click="removeCondition(index)" class="btn btn-error btn-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                      stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round"
                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- アクションボタン -->
          <div class="flex flex-wrap gap-4 mt-6">
            <button @click="addCondition" class="btn btn-outline btn-primary">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
              条件を追加
            </button>

            <button @click="search" class="btn btn-primary flex-1 md:flex-initial">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
              </svg>
              検索実行
            </button>

            <button @click="resetConditions" class="btn btn-ghost">
              リセット
            </button>
          </div>
        </div>
      </div>

      <!-- 検索結果エリア -->
      <div v-if="books && books.data">
        <div class="alert bg-[#e2f0e0] text-[#1a5b1a] rounded border-none mb-6 p-4 text-lg">
          検索結果 ({{ books.total }}件)
        </div>

        <div v-if="books.data.length === 0" class="alert alert-warning mb-6">
          <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
          <span>該当する書籍が見つかりませんでした。条件を変更して再検索してください。</span>
        </div>

        <div v-else class="flex flex-col gap-4">
          <div v-for="book in books.data" :key="book.id" class="card card-side bg-base-100 shadow-sm border border-base-300 rounded-none overflow-hidden">
            <figure class="w-48 shrink-0 border-r border-base-300">
              <img v-if="book.image_url && book.image_url !== 'no_image'" :src="book.image_url" :alt="book.title" class="object-cover w-full h-full" />
              <img v-else src="/images/no_image.png" :alt="book.title" class="object-cover w-full h-full" />
            </figure>
            <div class="card-body p-6">
              <h2 class="text-xl font-bold mb-1">
                {{ book.title }}
                <span v-if="book.subtitle" class="text-lg font-normal ml-2">{{ book.subtitle }}</span>
              </h2>
              <div class="text-sm text-gray-700 mb-4">
                {{ book.contributor }} <span class="ml-2">ISBN:{{ book.isbn }}</span>
              </div>
              <p class="whitespace-pre-wrap text-sm text-gray-800 mb-6">{{ book.content }}</p>

              <div class="text-sm font-semibold text-gray-800 mb-4">
                発行元出版社:{{ book.publisher }} <span v-if="book.imprint && book.imprint !== book.publisher">/ 販売元出版社:{{ book.imprint }}</span> / 価格:{{ book.price ? book.price + '円' : '-' }}
              </div>

              <div class="card-actions flex gap-2">
                <a :href="book.amazon_url" v-if="book.amazon_url" target="_blank" class="btn btn-primary btn-sm px-6 rounded text-white bg-[#007bff] hover:bg-[#0056b3] border-none">
                  Amazon
                </a>
                <a :href="book.honto_url" v-if="book.honto_url" target="_blank" class="btn btn-primary btn-sm px-6 rounded text-white bg-[#007bff] hover:bg-[#0056b3] border-none">
                  honto
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- ページネーション -->
        <div class="flex justify-center mt-6" v-if="books.links && books.links.length > 3">
          <div class="join">
            <template v-for="(link, i) in books.links" :key="i">
              <button
                v-if="link.url"
                @click="goToPage(link.url)"
                class="join-item btn"
                :class="{'btn-active': link.active}"
                v-html="link.label"
              ></button>
              <button
                v-else
                class="join-item btn btn-disabled"
                v-html="link.label"
              ></button>
            </template>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
