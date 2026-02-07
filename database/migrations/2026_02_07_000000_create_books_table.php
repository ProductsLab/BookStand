<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            // ID (BigInt, Auto Increment, Primary Key)
            $table->id();

            // ISBN: 0落ちを防ぐため文字列型に変更。検索用にユニークインデックスを付与
            $table->string('isbn', 13)->unique()->comment('ISBN-13');
            $table->string('isbn_10', 10)->nullable()->index()->comment('ISBN-10');

            // タイトル・サブタイトル
            // 元の定義に合わせて長さを指定（デフォルト255だと足りない可能性があるため）
            $table->string('title', 300)->index();
            $table->string('subtitle', 300)->nullable();

            // 内容紹介: 2000文字制限のvarcharより、柔軟なtext型が推奨されます
            $table->text('content')->nullable();

            // 関係者・出版社（正規化せずフラットに持つ場合）
            // 検索用にインデックスを推奨
            $table->string('contributor', 1000)->nullable();
            $table->string('imprint', 1000)->nullable();
            $table->string('publisher', 1000)->nullable()->index();

            // 画像URL: 'picture' から名称変更
            $table->string('image_url', 1000)->nullable();

            // 価格: マイナスにならないため unsignedInteger を使用
            // 将来的に小数を扱う場合は decimal('price', 10, 2) などに変更
            $table->unsignedInteger('price')->nullable();

            // 出版日: int型から date型へ変更
            // 元のカラム名 'date' は予約語と紛らわしいため 'published_date' 推奨
            $table->date('published_date')->nullable()->index();

            // ターゲット・コード類
            $table->integer('audience_type')->default(99)->comment('指定なしの場合99');
            $table->integer('audience_code')->default(99)->comment('指定なしの場合99');
            
            // Cコード: 4桁固定文字列のため char型を採用
            $table->char('c_code', 4)->nullable();
            $table->string('subject_text', 250)->nullable();

            // 外部サイトURL
            $table->string('amazon_url', 1000)->nullable();
            $table->string('honto_url', 1000)->nullable();

            // 作成日時・更新日時 (created_at, updated_at)
            // 元の 'add_data' は created_at として扱われます
            $table->timestamps(); 
            
            // 論理削除 (必要であればコメントアウトを外してください)
            // $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
