<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduled Tasks
|--------------------------------------------------------------------------
|
| Artisan コマンドおよび定期実行バッチ（スケジューラ）の定義ファイル。
| サーバー上で `php artisan schedule:work` または cron で `schedule:run` を
| 動かすことで、下記のスケジュール処理が自動実行されます。
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 毎日朝6時に版元ドットコムのRSSフィードから「今日の本」「明日の本」の新刊ISBNを取得し、
// OpenBD API 経由で書誌情報を取得して books テーブルへ保存・更新する日次バッチ処理
Schedule::command('book:save --rss')->dailyAt('06:00');
