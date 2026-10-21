<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| BookStand の画面ルーティング定義。
| Inertia.js を使用して Vue コンポーネント（resources/js/Pages/配下）を描画します。
|
*/

// トップページ（書籍検索画面の初期表示）
Route::get('/', [BookController::class, 'index'])->name('home');

// 書籍検索実行用エンドポイント（クエリパラメータで検索条件を受け取る）
Route::get('/books/search', [BookController::class, 'index'])->name('books.search');

// 今日の本一覧ページ（本日出版予定の新刊を表示、?date=YYYY-MM-DD で日付指定も可）
Route::get('/today', [BookController::class, 'today'])->name('books.today');

// 明日の本一覧ページ（明日出版予定の新刊を表示、?date=YYYY-MM-DD で日付指定も可）
Route::get('/tomorrow', [BookController::class, 'tomorrow'])->name('books.tomorrow');
