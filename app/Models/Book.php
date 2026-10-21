<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 書籍モデル (books テーブル)
 *
 * OpenBD API (ONIX形式) および 国立国会図書館サーチ (書影) から取得した
 * 書誌データを保持します。
 */
class Book extends Model
{
    use HasFactory;

    /**
     * 一括代入（Mass Assignment）を許可するカラム一覧
     */
    protected $fillable = [
        'isbn',           // ISBN-13（13桁・ハイフンなし・ユニーク）
        'isbn_10',        // ISBN-10（10桁・Amazonリンク生成等に使用）
        'title',          // 書名（タイトル）
        'subtitle',       // サブタイトル（副題）
        'content',        // 内容紹介・目次テキスト（改行や<br>を含む）
        'contributor',    // 著者・編者・訳者名
        'imprint',        // 発行元出版社（ONIXのImprintName）
        'publisher',      // 発売元・販売元出版社（ONIXのPublisherName）
        'image_url',      // 書影画像URL（未取得時は 'no_image.png'）
        'price',          // 本体価格（円・整数）
        'published_date', // 出版日（YYYY-MM-DD）
        'audience_type',  // 読者対象コード種別（ONIX AudienceCodeType / 未指定時は99）
        'audience_code',  // 読者対象コード値（ONIX AudienceCodeValue / 未指定時は99）
        'c_code',         // Cコード（4桁の図書分類コード：対象1桁+形態1桁+内容2桁 / 未指定時は'9999'）
        'subject_text',   // 件名・キーワード
        'amazon_url',     // Amazon商品ページURL
        'honto_url',      // hontoリダイレクトURL
    ];

    /**
     * 属性の型キャスト定義
     * JSONシリアライズ時（InertiaでVueへ渡す際）のフォーマットもここで制御します。
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'published_date' => 'date:Y-m-d', // Vue側で扱いやすいよう 'YYYY-MM-DD' 文字列に統一
            'audience_type' => 'integer',
            'audience_code' => 'integer',
        ];
    }

    /**
     * 成人指定本を検索対象から除外するローカルスコープ
     *
     * ONIX規格（日本書籍出版協会）において：
     * - audience_type = 22（成人指定区分）
     * - audience_code = 01（成人指定 / 18歳未満禁止等）, 02, 03
     * に該当する書籍を一覧・検索結果から除外します。
     *
     * 使用例: Book::query()->excludeAdult()->get();
     */
    public function scopeExcludeAdult(Builder $query): Builder
    {
        return $query->whereNot(function (Builder $q) {
            $q->where('audience_type', 22)
                ->whereIn('audience_code', [1, 2, 3]);
        });
    }
}
