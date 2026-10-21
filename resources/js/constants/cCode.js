/**
 * Cコード（日本図書コードの分類コード・4桁）マスター定義
 *
 * Cコードは4桁の数字で構成されます：
 * - 1桁目（C_CODE_TARGETS） : 販売対象（0:一般, 1:教養, 2:実用, 3:専門 ...）
 * - 2桁目（C_CODE_FORMS）   : 発行形態（0:単行本, 1:文庫, 2:新書, 9:コミック ...）
 * - 3〜4桁目（C_CODE_CONTENTS）: 内容分類（00:総記 〜 98:外国文学その他）
 *
 * 検索時のワイルドカードとして未選択時は '_'（1文字）または '__'（2文字）を使用し、
 * バックエンドの SQL LIKE 検索（例: '019_'）にそのまま渡せる構造になっています。
 */

/** 1桁目：対象（販売対象） */
export const C_CODE_TARGETS = [
  { value: '_', label: '選択しない' },
  { value: '0', label: '一般' },
  { value: '1', label: '教養' },
  { value: '2', label: '実用' },
  { value: '3', label: '専門' },
  { value: '4', label: '検定教科書等' },
  { value: '5', label: '婦人' },
  { value: '6', label: '学参Ⅰ(小中)' },
  { value: '7', label: '学参Ⅱ(高校)' },
  { value: '8', label: '児童' },
  { value: '9', label: '雑誌扱い' },
];

/** 2桁目：形態（発行形態） */
export const C_CODE_FORMS = [
  { value: '_', label: '選択しない' },
  { value: '0', label: '単行本' },
  { value: '1', label: '文庫' },
  { value: '2', label: '新書' },
  { value: '3', label: '全集・双書' },
  { value: '4', label: 'ムック・その他' },
  { value: '5', label: '事・事典' },
  { value: '6', label: '図鑑' },
  { value: '7', label: '絵本' },
  { value: '8', label: '磁気媒体等' },
  { value: '9', label: 'コミック' },
];

/**
 * 3〜4桁目：内容（内容分類）
 * '0_' や '9_' のように末尾が '_' の項目は大分類（総記全般・文学全般など）を表します。
 */
export const C_CODE_CONTENTS = [
  { value: '__', label: '選択しない' },
  { value: '0_', label: '(総記)' },
  { value: '00', label: '総記' },
  { value: '01', label: '百科事典' },
  { value: '02', label: '年鑑・雑誌' },
  { value: '04', label: '情報科学' },
  { value: '1_', label: '(哲学・宗教・心理学)' },
  { value: '10', label: '哲学' },
  { value: '11', label: '心理(学)' },
  { value: '12', label: '倫理(学)' },
  { value: '14', label: '宗教' },
  { value: '15', label: '仏教' },
  { value: '16', label: 'キリスト教' },
  { value: '2_', label: '(歴史・地理)' },
  { value: '20', label: '歴史総記' },
  { value: '21', label: '日本歴史' },
  { value: '22', label: '外国歴史' },
  { value: '23', label: '伝記' },
  { value: '25', label: '地理' },
  { value: '26', label: '旅行' },
  { value: '3_', label: '(社会科学)' },
  { value: '30', label: '社会科学総記' },
  { value: '31', label: '政治-含む国防軍事' },
  { value: '32', label: '法律' },
  { value: '33', label: '経済・財政・統計' },
  { value: '34', label: '経営' },
  { value: '36', label: '社会' },
  { value: '37', label: '教育' },
  { value: '39', label: '民族・風習' },
  { value: '4_', label: '(自然科学)' },
  { value: '40', label: '自然科学総記' },
  { value: '41', label: '数学' },
  { value: '42', label: '物理学' },
  { value: '43', label: '化学' },
  { value: '44', label: '天文・地理' },
  { value: '45', label: '生物学' },
  { value: '47', label: '医学・歯学・薬学' },
  { value: '5_', label: '(工学工業)' },
  { value: '50', label: '工学・工学総記' },
  { value: '51', label: '土木' },
  { value: '52', label: '建築' },
  { value: '53', label: '機械' },
  { value: '54', label: '電気' },
  { value: '55', label: '電子通信' },
  { value: '56', label: '海事' },
  { value: '57', label: '採掘・冶金' },
  { value: '58', label: 'その他の工業' },
  { value: '6_', label: '(産業)' },
  { value: '60', label: '産業総記' },
  { value: '61', label: '農林業' },
  { value: '62', label: '水産業' },
  { value: '63', label: '商業' },
  { value: '65', label: '交通・通信' },
  { value: '7_', label: '(芸術・生活)' },
  { value: '70', label: '芸術総記' },
  { value: '71', label: '絵画・彫刻' },
  { value: '72', label: '写真・工芸' },
  { value: '73', label: '音楽・舞踊' },
  { value: '74', label: '演劇・映画' },
  { value: '75', label: '体育・スポーツ' },
  { value: '76', label: '諸芸・娯楽' },
  { value: '77', label: '家事' },
  { value: '78', label: '日記・手帳' },
  { value: '79', label: 'コミックス・劇画' },
  { value: '8_', label: '(語学)' },
  { value: '80', label: '語学総記' },
  { value: '81', label: '日本語' },
  { value: '82', label: '英米語' },
  { value: '84', label: 'ドイツ語' },
  { value: '85', label: 'フランス語' },
  { value: '87', label: '各国語' },
  { value: '9_', label: '(文学)' },
  { value: '90', label: '文学総記' },
  { value: '91', label: '日本文学総記' },
  { value: '92', label: '日本文学詩歌' },
  { value: '93', label: '日本文学、小説・物語' },
  { value: '95', label: '日本文学、評論、随筆、その他' },
  { value: '97', label: '外国文学小説' },
  { value: '98', label: '外国文学、その他' },
];

// 高速引き当て用のルックアップマップ（ワイルドカード '_' を除外したもの）
const targetMap = Object.fromEntries(
  C_CODE_TARGETS.filter((i) => i.value !== '_').map((i) => [i.value, i.label]),
);
const formMap = Object.fromEntries(
  C_CODE_FORMS.filter((i) => i.value !== '_').map((i) => [i.value, i.label]),
);
const contentMap = Object.fromEntries(
  C_CODE_CONTENTS.filter((i) => !i.value.includes('_')).map((i) => [
    i.value,
    i.label,
  ]),
);

/**
 * 4桁のCコード文字列（例: '0193'）から日本語ラベル配列（例: ['一般', '文庫', '日本文学、小説・物語']）を返す
 * 未分類（'9999' や '____'）の場合は空配列を返します。
 *
 * @param {string|null|undefined} cCode
 * @returns {string[]}
 */
export function decodeCCode(cCode) {
  if (!cCode || typeof cCode !== 'string' || cCode.length !== 4) {
    return [];
  }
  if (cCode === '9999' || cCode === '____') {
    return [];
  }

  const labels = [];
  const t = targetMap[cCode[0]];
  const f = formMap[cCode[1]];
  const c = contentMap[cCode.slice(2, 4)];

  if (t) labels.push(t);
  if (f) labels.push(f);
  if (c) labels.push(c);

  return labels;
}
