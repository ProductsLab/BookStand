<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';


// 検索条件の初期値
const createEmptyCondition = () => ({
  type: 'title',
  value: '',
  operator: 'AND'
});

// 検索条件のリスト（初期は空）
const searchConditions = ref([]);

// 検索結果フラグ
const searchResults = ref(null);

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
  searchResults.value = null;
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

  console.log('検索条件:', validConditions.length === 0 ? '全件検索' : searchData);

  // TODO: Inertia.jsでバックエンドAPIに送信
  // 条件が空の場合は全件検索
  // router.get('/books/search', searchData);

  // 仮の検索結果表示
  searchResults.value = true;
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

      <!-- 検索結果エリア (実装予定) -->
      <div v-if="searchResults" class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <h2 class="card-title text-2xl mb-4">検索結果</h2>
          <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
              class="stroke-current shrink-0 w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>検索機能は実装予定です。バックエンドAPIと連携します。</span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
