<script setup>
/**
 * 書籍情報カードコンポーネント (BookCard.vue)
 *
 * 検索結果一覧（Welcome.vue）、今日の本（Today.vue）、明日の本（Tomorrow.vue）で
 * 共通利用される1冊分の表示カードです。
 */
import { ref, computed } from 'vue';
import { decodeCCode } from '@/constants/cCode';

const props = defineProps({
  book: {
    type: Object,
    required: true,
  },
});

// 画像の読み込み失敗フラグ（リンク切れ時に代替画像へフォールバックするために使用）
const imageError = ref(false);

// 内容紹介文の全文展開フラグ（長文の場合の「続きを読む / 閉じる」切り替え用）
const isExpanded = ref(false);

/**
 * 表示する書影画像のURLを算出
 * DBの値が未設定・'no_image.png'・または読み込みエラー発生時はローカルの代替画像を返す
 */
const coverSrc = computed(() => {
  if (
    imageError.value ||
    !props.book.image_url ||
    props.book.image_url === 'no_image.png' ||
    props.book.image_url === 'no_image'
  ) {
    return '/images/no_image.png';
  }
  return props.book.image_url;
});

/**
 * 外部書影URL（NDLサーチ等）の読み込みに失敗した際のフォールバックハンドラ
 */
const handleImageError = () => {
  imageError.value = true;
};

/**
 * 内容紹介テキストの整形
 * バッチ保存時に付与された <br> タグを改行コード（\n）へ変換し、
 * v-html を使わず（XSSを防ぎつつ）whitespace-pre-wrap で安全に改行表示できるようにする
 */
const formattedContent = computed(() => {
  if (!props.book.content) return '';
  return props.book.content
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
});

// 紹介文が280文字を超える場合は折りたたみ対象とする
const isLongContent = computed(() => formattedContent.value.length > 280);

/**
 * 画面に表示する内容紹介テキスト（折りたたみ時は先頭280文字＋省略記号）
 */
const displayedContent = computed(() => {
  if (!isLongContent.value || isExpanded.value) {
    return formattedContent.value;
  }
  return formattedContent.value.slice(0, 280) + '…';
});

// 4桁のCコードから日本語のジャンルバッジ配列（対象・形態・内容）を取得
const cCodeLabels = computed(() => decodeCCode(props.book.c_code));

/**
 * 出版日（YYYY-MM-DD）を日本語表記（YYYY年MM月DD日）に整形
 */
const formattedDate = computed(() => {
  if (!props.book.published_date) return null;
  const match = String(props.book.published_date).match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (match) {
    return `${match[1]}年${match[2]}月${match[3]}日`;
  }
  return props.book.published_date;
});

/**
 * 価格を3桁カンマ区切り＋「円」に整形（0円または未設定時は '-'）
 */
const formattedPrice = computed(() => {
  if (!props.book.price || Number(props.book.price) <= 0) {
    return '-';
  }
  return `${Number(props.book.price).toLocaleString()}円`;
});

/**
 * 発行元出版社（ONIXのImprintNameを優先し、なければPublisherNameを表示）
 */
const primaryPublisher = computed(() => {
  return props.book.imprint || props.book.publisher || '-';
});

/**
 * 販売元出版社（発行元と販売元の両方が存在し、かつ異なる場合のみ表示）
 */
const secondaryPublisher = computed(() => {
  if (
    props.book.imprint &&
    props.book.publisher &&
    props.book.imprint !== props.book.publisher
  ) {
    return props.book.publisher;
  }
  return null;
});
</script>

<template>
  <div
    class="card sm:card-side bg-base-100 shadow-sm border border-base-300 rounded-lg overflow-hidden transition-shadow hover:shadow-md"
  >
    <!-- 左側（スマホ時は上部）：書影エリア -->
    <figure
      class="w-full sm:w-44 md:w-48 shrink-0 bg-base-200/60 border-b sm:border-b-0 sm:border-r border-base-300 flex items-center justify-center p-3 sm:p-0"
    >
      <img
        :src="coverSrc"
        :alt="book.title"
        loading="lazy"
        @error="handleImageError"
        class="object-contain sm:object-cover max-h-64 sm:max-h-none w-auto sm:w-full sm:h-full"
      />
    </figure>

    <!-- 右側（スマホ時は下部）：書誌情報エリア -->
    <div class="card-body p-5 sm:p-6 flex flex-col justify-between">
      <div>
        <!-- Cコードのジャンルバッジ & 件名キーワード -->
        <div class="flex flex-wrap items-center gap-1.5 mb-2" v-if="cCodeLabels.length > 0 || book.subject_text">
          <span
            v-for="(label, idx) in cCodeLabels"
            :key="idx"
            class="badge badge-sm badge-outline badge-primary"
          >
            {{ label }}
          </span>
          <span
            v-if="book.subject_text"
            class="badge badge-sm badge-ghost text-xs truncate max-w-xs"
            :title="book.subject_text"
          >
            {{ book.subject_text }}
          </span>
        </div>

        <!-- 書名 & サブタイトル -->
        <h2 class="text-xl font-bold text-base-content mb-1 leading-snug">
          {{ book.title }}
          <span
            v-if="book.subtitle"
            class="text-base font-normal text-base-content/80 ml-2 block sm:inline"
          >
            {{ book.subtitle }}
          </span>
        </h2>

        <!-- 著者名 & ISBN -->
        <div class="text-sm text-base-content/70 mb-4 flex flex-wrap gap-x-4 gap-y-1">
          <span v-if="book.contributor">{{ book.contributor }}</span>
          <span>ISBN: {{ book.isbn }}</span>
        </div>

        <!-- 内容紹介・目次テキスト -->
        <div v-if="formattedContent" class="mb-5">
          <p class="whitespace-pre-wrap text-sm text-base-content/90 leading-relaxed">
            {{ displayedContent }}
          </p>
          <button
            v-if="isLongContent"
            type="button"
            @click="isExpanded = !isExpanded"
            class="btn btn-link btn-xs px-0 mt-1 text-primary no-underline hover:underline"
          >
            {{ isExpanded ? '閉じる' : '続きを読む' }}
          </button>
        </div>
      </div>

      <!-- 下部：出版社・価格・出版日 & 外部購入リンクボタン -->
      <div>
        <div class="text-sm font-medium text-base-content/80 mb-4 flex flex-wrap gap-x-2 gap-y-1">
          <span>発行元出版社: {{ primaryPublisher }}</span>
          <span v-if="secondaryPublisher">/ 販売元出版社: {{ secondaryPublisher }}</span>
          <span>/ 価格: {{ formattedPrice }}</span>
          <span v-if="formattedDate">/ 出版日: {{ formattedDate }}</span>
        </div>

        <div class="card-actions flex gap-2">
          <a
            v-if="book.amazon_url"
            :href="book.amazon_url"
            target="_blank"
            rel="noopener noreferrer"
            class="btn btn-sm px-5 rounded text-white bg-[#007bff] hover:bg-[#0056b3] border-none"
          >
            Amazon
          </a>
          <a
            v-if="book.honto_url"
            :href="book.honto_url"
            target="_blank"
            rel="noopener noreferrer"
            class="btn btn-sm px-5 rounded text-white bg-[#007bff] hover:bg-[#0056b3] border-none"
          >
            honto
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
