<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * 書籍データ取得・保存バッチコマンド (book:save)
 *
 * 【主な使い方】
 * 1. 日次新刊取得（版元ドットコムRSSから今日・明日の新刊を取得して保存）:
 *    php artisan book:save --rss
 *    php artisan book:save --today
 *    php artisan book:save --tomorrow
 *
 * 2. CSVファイルからISBNを一括インポート:
 *    php artisan book:save --csv=book.csv --chunk=100
 *
 * 3. 単一のISBNを指定して取得・保存:
 *    php artisan book:save 9784000010887
 */
class SaveBookData extends Command
{
    /** 版元ドットコム「本日の新しい本」RSSフィードURL */
    private const HANMOTO_TODAY_RSS = 'https://www.hanmoto.com/ci/bd/search/sdate/today/edate/today/hdt/%E6%96%B0%E3%81%97%E3%81%84%E6%9C%AC/order/desc/vw/rss20';

    /** 版元ドットコム「明日発売の本」RSSフィードURL */
    private const HANMOTO_TOMORROW_RSS = 'https://www.hanmoto.com/ci/bd/search/sdate/day/edate/day/hdt/%E6%98%8E%E6%97%A5%E7%99%BA%E5%A3%B2%E3%81%AE%E6%9C%AC/order/desc/vw/rss20';

    protected $signature = 'book:save
                            {isbn? : 単一のISBN}
                            {--csv= : ISBNが記載されたCSVファイルパス}
                            {--rss : 版元ドットコムのRSSから今日・明日の新刊を取得して保存}
                            {--today : 本日の新刊RSSから取得して保存}
                            {--tomorrow : 明日の新刊RSSから取得して保存}
                            {--chunk=100 : 一括処理の件数}';
    protected $description = 'ISBNまたはRSS/CSVから書籍データを取得して保存する';

    public function handle(): int
    {
        $csvPath = $this->option('csv');
        $isbn = $this->argument('isbn');
        $rss = $this->option('rss');
        $today = $this->option('today');
        $tomorrow = $this->option('tomorrow');

        // 1. RSSフィードから今日・明日の新刊を取得するモード（旧 data.php 相当）
        if ($rss || $today || $tomorrow) {
            return $this->importFromRss(
                fetchToday: $rss || $today,
                fetchTomorrow: $rss || $tomorrow
            );
        }

        // 2. CSVファイルから一括インポートするモード
        if ($csvPath) {
            return $this->importFromCsv($csvPath);
        }

        // 3. 単一ISBN指定モード
        if (!$isbn) {
            $this->error('ISBN、--csv、または --rss (--today / --tomorrow) オプションを指定してください。');
            return Command::FAILURE;
        }

        return $this->saveBook($this->normalizeNumber($isbn));
    }

    /**
     * 版元ドットコムのRSSフィードから今日・明日の新刊ISBNを取得して保存
     *
     * RSS内の各記事リンク（例: https://www.hanmoto.com/bd/isbn/9784...）の末尾から
     * 13桁のISBNを抽出し、OpenBD APIで詳細情報を一括取得してDBに保存・更新します。
     */
    private function importFromRss(bool $fetchToday, bool $fetchTomorrow): int
    {
        $feeds = [];
        if ($fetchToday) {
            $feeds['今日の本'] = self::HANMOTO_TODAY_RSS;
        }
        if ($fetchTomorrow) {
            $feeds['明日の本'] = self::HANMOTO_TOMORROW_RSS;
        }

        $allIsbns = [];
        foreach ($feeds as $label => $url) {
            $this->info("{$label}のRSSフィードを取得中...");
            $isbns = $this->fetchIsbnsFromRss($url);
            $this->info("{$label}: " . count($isbns) . " 件のISBNを検出");
            $allIsbns = array_merge($allIsbns, $isbns);
        }

        // 重複や空要素を除外
        $allIsbns = array_values(array_unique(array_filter($allIsbns)));
        $totalCount = count($allIsbns);

        if ($totalCount === 0) {
            $this->info('対象のISBNが見つかりませんでした。');
            return Command::SUCCESS;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $chunks = array_chunk($allIsbns, $chunkSize);

        $successCount = 0;
        $failCount = 0;
        $processed = 0;

        foreach ($chunks as $chunk) {
            [$success, $fail] = $this->processChunk($chunk, $processed, $totalCount);
            $successCount += $success;
            $failCount += $fail;
            $processed += count($chunk);
        }

        $this->newLine();
        $this->info("RSSインポート完了: 成功 {$successCount} 件 / 失敗 {$failCount} 件 (全 {$totalCount} 件中)");

        return Command::SUCCESS;
    }

    /**
     * RSS URLからISBN-13リストを抽出
     */
    private function fetchIsbnsFromRss(string $url): array
    {
        try {
            $response = Http::withUserAgent('Mozilla/5.0 (compatible; BookStand/1.0)')
                ->timeout(20)
                ->get($url);

            if ($response->failed()) {
                $this->warn("RSSの取得に失敗しました: {$url}");
                return [];
            }

            $xml = new \SimpleXMLElement($response->body(), LIBXML_NOWARNING | LIBXML_NOERROR);
            $items = $xml->channel->item ?? [];
            $isbns = [];

            foreach ($items as $item) {
                $link = (string) ($item->link ?? '');
                $path = parse_url($link, PHP_URL_PATH);
                if (!$path) {
                    continue;
                }
                // URLパス（/bd/isbn/9784...）の末尾セグメントからISBN-13を取り出す
                $segments = explode('/', trim($path, '/'));
                $last = $this->normalizeNumber(end($segments));
                if ($last && strlen($last) === 13) {
                    $isbns[] = $last;
                }
            }

            return $isbns;
        } catch (\Throwable $e) {
            $this->warn("RSS解析エラー: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * CSVファイルからISBNを読み込んで一括保存
     */
    private function importFromCsv(string $csvPath): int
    {
        if (!file_exists($csvPath)) {
            $this->error("ファイルが見つかりません: {$csvPath}");
            return Command::FAILURE;
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $this->error("ファイルを開けません: {$csvPath}");
            return Command::FAILURE;
        }

        // ISBNを全件読み込み
        $allIsbns = [];
        while (($row = fgetcsv($handle)) !== false) {
            $isbn = $this->normalizeNumber(trim($row[0] ?? ''));
            if (!empty($isbn)) {
                $allIsbns[] = $isbn;
            }
        }
        fclose($handle);

        $totalCount = count($allIsbns);
        $this->info("CSVファイル読み込み: 全 {$totalCount} 件");

        // 登録済みISBNを一括チェックしてスキップ対象を算出（高速化のため1000件ずつチャンク処理）
        $existingIsbns = collect($allIsbns)
            ->chunk(1000)
            ->flatMap(fn($chunk) => Book::whereIn('isbn', $chunk)->pluck('isbn'))
            ->flip()
            ->all();

        $newIsbns = array_values(array_filter($allIsbns, fn($isbn) => !isset($existingIsbns[$isbn])));
        $skipCount = $totalCount - count($newIsbns);

        if ($skipCount > 0) {
            $this->info("登録済みスキップ: {$skipCount} 件");
        }

        $chunkSize = (int) $this->option('chunk');
        $chunks = array_chunk($newIsbns, $chunkSize);

        $successCount = 0;
        $failCount = 0;
        $processed = $skipCount;

        foreach ($chunks as $chunk) {
            [$success, $fail] = $this->processChunk($chunk, $processed, $totalCount);
            $successCount += $success;
            $failCount += $fail;
            $processed += count($chunk);
        }

        $this->newLine();
        $this->info("インポート完了: 成功 {$successCount} 件 / 失敗 {$failCount} 件 / スキップ {$skipCount} 件 (全 {$totalCount} 件中)");

        return Command::SUCCESS;
    }

    /**
     * チャンク単位でOpenBD APIから書籍データを一括取得・保存
     */
    private function processChunk(array $isbns, int $processedSoFar, int $total): array
    {
        // OpenBD APIへカンマ区切りで一括リクエスト
        $isbnStr = implode(',', $isbns);
        $response = Http::get("https://api.openbd.jp/v1/get?isbn={$isbnStr}");

        if ($response->failed()) {
            $count = count($isbns);
            $this->error("OpenBD APIリクエスト失敗 ({$count} 件)");
            return [0, $count];
        }

        $bookDataList = $response->json();

        // 国立国会図書館サーチの書影URLが存在するかをHTTP Poolで並列チェック
        $thumbnailResponses = Http::pool(function (Pool $pool) use ($isbns) {
            foreach ($isbns as $isbn) {
                $pool->as($isbn)->get("https://ndlsearch.ndl.go.jp/thumbnail/{$isbn}.jpg");
            }
        });

        $successCount = 0;
        $failCount = 0;

        foreach ($isbns as $index => $isbn) {
            $current = $processedSoFar + $index + 1;
            $progress = "[{$current}/{$total}]";

            $bookData = $bookDataList[$index] ?? null;
            if ($bookData === null) {
                $this->error("{$progress} 書籍情報を取得できませんでした: {$isbn}");
                $failCount++;
                continue;
            }

            $onix = $bookData['onix'] ?? null;
            if ($onix === null) {
                $this->error("{$progress} ONIXデータが存在しません: {$isbn}");
                $failCount++;
                continue;
            }

            $isbn10 = $this->isbnConvert($isbn);

            // 基本情報の取得（タイトル・サブタイトル・著者）
            $bookTitle = $onix['DescriptiveDetail']['TitleDetail']['TitleElement']['TitleText']['content'] ?? null;
            $bookSubtitle = $onix['DescriptiveDetail']['TitleDetail']['TitleElement']['Subtitle']['content'] ?? null;
            $bookContributor = $onix['DescriptiveDetail']['Contributor'][0]['PersonName']['content'] ?? null;

            // 内容紹介文の組み立て
            $bookContent = $this->buildBookContent($onix);

            // 出版情報（発行元出版社・販売元出版社・価格・出版日）
            $bookImprint = $onix['PublishingDetail']['Imprint']['ImprintName'] ?? null;
            $bookPublisher = $onix['PublishingDetail']['Publisher']['PublisherName'] ?? null;
            $bookPrice = $this->normalizeNumber($onix['ProductSupply']['SupplyDetail']['Price'][0]['PriceAmount'] ?? null) ?: 0;
            $bookDate = $this->normalizeNumber($onix['PublishingDetail']['PublishingDate'][0]['Date'] ?? null);

            // 書影 (OpenBDに書影がある場合は優先し、なければNDLサムネイルを使用)
            $openBdCover = $onix['CollateralDetail']['SupportingResource'][0]['ResourceVersion'][0]['ResourceLink'] ?? null;
            $thumbnailResponse = $thumbnailResponses[$isbn] ?? null;
            if (!empty($openBdCover)) {
                $bookPicture = $openBdCover;
            } elseif ($thumbnailResponse instanceof Response && $thumbnailResponse->status() === 200) {
                $bookPicture = "https://ndlsearch.ndl.go.jp/thumbnail/{$isbn}.jpg";
            } else {
                $bookPicture = 'no_image.png';
            }

            // 対象読者（成人指定判定等に使用）
            $audienceType = $this->normalizeNumber($onix['DescriptiveDetail']['Audience'][0]['AudienceCodeType'] ?? null) ?? 99;
            $audienceCode = $this->normalizeNumber($onix['DescriptiveDetail']['Audience'][0]['AudienceCodeValue'] ?? null) ?? 99;

            // Cコード（ジャンル）および件名キーワードの抽出
            [$cCode, $subjectWord] = $this->extractSubjectInfo($onix);

            // 未設定時のデフォルト値を補完
            if (empty($audienceType)) {
                $audienceType = 99;
            }
            if (empty($audienceCode)) {
                $audienceCode = 99;
            }
            if (empty($cCode)) {
                $cCode = '9999';
            }
            if (empty($bookTitle)) {
                $bookTitle = '名無しの本';
            }

            $publishedDate = null;
            if ($bookDate) {
                $publishedDate = $this->parsePublishingDate($bookDate);
            }

            // Amazon は ISBN-10、honto は ISBN-13 の末尾チェックデジットを除いた12桁でURLを生成
            $amazonUrl = "https://www.amazon.co.jp/dp/{$isbn10}";
            $hontoUrl = "http://honto.jp/redirect.html?bookno=" . substr($isbn, 0, -1);

            // 既存ISBNがあれば更新、なければ新規作成
            $book = Book::updateOrCreate(
                ['isbn' => $isbn],
                [
                    'isbn_10' => $isbn10,
                    'amazon_url' => $amazonUrl,
                    'honto_url' => $hontoUrl,
                    'title' => $bookTitle,
                    'subtitle' => $bookSubtitle,
                    'contributor' => $bookContributor,
                    'content' => $bookContent,
                    'imprint' => $bookImprint,
                    'publisher' => $bookPublisher,
                    'image_url' => $bookPicture,
                    'audience_type' => $audienceType,
                    'audience_code' => $audienceCode,
                    'c_code' => $cCode,
                    'published_date' => $publishedDate,
                    'price' => $bookPrice,
                    'subject_text' => $subjectWord,
                ]
            );

            $this->info("{$progress} 保存: {$book->title} (ISBN: {$isbn})");
            $successCount++;
        }

        return [$successCount, $failCount];
    }

    /**
     * 単一ISBNの書籍データを取得して保存
     */
    private function saveBook(string $isbn, string $progress = ''): int
    {
        $prefix = $progress ? "{$progress} " : '';
        $isbn10 = $this->isbnConvert($isbn);
        $amazonUrl = "https://www.amazon.co.jp/dp/{$isbn10}";
        $hontoUrl = "http://honto.jp/redirect.html?bookno=" . substr($isbn, 0, -1);
        $openBdUrl = "https://api.openbd.jp/v1/get?isbn={$isbn10}";

        $arr = $this->getBookInfo($openBdUrl);
        if ($arr === null) {
            $this->error("{$prefix}書籍情報を取得できませんでした: {$isbn}");
            return Command::FAILURE;
        }

        $onix = $arr[0]['onix'] ?? null;
        if ($onix === null) {
            $this->error("{$prefix}ONIXデータが存在しません: {$isbn}");
            return Command::FAILURE;
        }

        // 基本情報の取得
        $bookTitle = $onix['DescriptiveDetail']['TitleDetail']['TitleElement']['TitleText']['content'] ?? null;
        $bookSubtitle = $onix['DescriptiveDetail']['TitleDetail']['TitleElement']['Subtitle']['content'] ?? null;
        $bookContributor = $onix['DescriptiveDetail']['Contributor'][0]['PersonName']['content'] ?? null;

        // 内容紹介の組み立て
        $bookContent = $this->buildBookContent($onix);

        // 出版情報
        $bookImprint = $onix['PublishingDetail']['Imprint']['ImprintName'] ?? null;
        $bookPublisher = $onix['PublishingDetail']['Publisher']['PublisherName'] ?? null;
        $bookPicture = $this->getBookImage($isbn);
        $bookPrice = $this->normalizeNumber($onix['ProductSupply']['SupplyDetail']['Price'][0]['PriceAmount'] ?? null) ?: 0;
        $bookDate = $this->normalizeNumber($onix['PublishingDetail']['PublishingDate'][0]['Date'] ?? null);

        // 対象読者
        $audienceType = $this->normalizeNumber($onix['DescriptiveDetail']['Audience'][0]['AudienceCodeType'] ?? null) ?? 99;
        $audienceCode = $this->normalizeNumber($onix['DescriptiveDetail']['Audience'][0]['AudienceCodeValue'] ?? null) ?? 99;

        // Cコード・件名
        [$cCode, $subjectWord] = $this->extractSubjectInfo($onix);

        // デフォルト値の設定
        if (empty($audienceType)) {
            $audienceType = 99;
        }
        if (empty($audienceCode)) {
            $audienceCode = 99;
        }
        if (empty($cCode)) {
            $cCode = '9999';
        }
        if (empty($bookTitle)) {
            $bookTitle = '名無しの本';
        }

        // 出版日の変換 (YYYYMMDD → Y-m-d)
        $publishedDate = null;
        if ($bookDate) {
            $publishedDate = $this->parsePublishingDate($bookDate);
        }

        $book = Book::updateOrCreate(
            ['isbn' => $isbn],
            [
                'isbn_10' => $isbn10,
                'amazon_url' => $amazonUrl,
                'honto_url' => $hontoUrl,
                'title' => $bookTitle,
                'subtitle' => $bookSubtitle,
                'contributor' => $bookContributor,
                'content' => $bookContent,
                'imprint' => $bookImprint,
                'publisher' => $bookPublisher,
                'image_url' => $bookPicture,
                'audience_type' => $audienceType,
                'audience_code' => $audienceCode,
                'c_code' => $cCode,
                'published_date' => $publishedDate,
                'price' => $bookPrice,
                'subject_text' => $subjectWord,
            ]
        );

        $this->info("{$prefix}保存: {$book->title} (ISBN: {$isbn})");

        return Command::SUCCESS;
    }

    /**
     * ONIXの CollateralDetail.TextContent から内容紹介・目次テキストを組み立てる
     */
    private function buildBookContent(array $onix): string
    {
        $textContents = $onix['CollateralDetail']['TextContent'] ?? null;
        if (empty($textContents)) {
            return '';
        }

        $bookContent = '';

        if (isset($textContents[4]['Text'])) {
            $bookContent = $textContents[4]['Text'] . '<br><br>';
        }

        for ($i = 3; $i >= 0; $i--) {
            if (isset($textContents[$i]['Text'])) {
                $bookContent .= $textContents[$i]['Text'] . '<br>';
            }
        }

        $bookContent = strip_tags($bookContent, '<br>');

        // TEXT型の最大値(65,535バイト)を超える場合は切り捨て
        if (strlen($bookContent) > 65535) {
            $bookContent = mb_strcut($bookContent, 0, 65535);
        }

        return $bookContent;
    }

    /**
     * ONIXの Subject から Cコード (SchemeIdentifier=78) と 件名キーワード (SchemeIdentifier=20) を抽出
     */
    private function extractSubjectInfo(array $onix): array
    {
        $cCode = null;
        $subjectWord = null;

        $subjects = $onix['DescriptiveDetail']['Subject'] ?? null;
        if (empty($subjects)) {
            return [$cCode, $subjectWord];
        }

        for ($i = min(2, count($subjects) - 1); $i >= 0; $i--) {
            if (!isset($subjects[$i])) {
                continue;
            }

            $schemeId = $subjects[$i]['SubjectSchemeIdentifier'] ?? null;

            if ($schemeId == 78) {
                $cCode = $this->normalizeNumber($subjects[$i]['SubjectCode'] ?? null);
            }
            if ($schemeId == 20) {
                $subjectWord = $subjects[$i]['SubjectHeadingText'] ?? null;
                if ($subjectWord !== null) {
                    $subjectWord = mb_substr($subjectWord, 0, 250);
                }
            }
        }

        return [$cCode, $subjectWord];
    }

    /**
     * ISBN-13（13桁）から ISBN-10（10桁・モジュラス11ウェイト10-2）へ変換
     */
    private function isbnConvert(string $isbn): string
    {
        $digits = array_map('intval', str_split($isbn));

        // 先頭3桁(978)を除いた4〜12桁目(インデックス3〜11)からチェックデジットを計算
        $sum = 0;
        $weight = 10;
        for ($i = 3; $i <= 11; $i++) {
            $sum += $weight * $digits[$i];
            $weight--;
        }

        $checkDigit = 11 - ($sum % 11);

        if ($checkDigit == 10) {
            $checkDigit = 'X';
        } elseif ($checkDigit == 11) {
            $checkDigit = '0';
        }

        $isbn10 = '';
        for ($i = 3; $i <= 11; $i++) {
            $isbn10 .= $digits[$i];
        }
        $isbn10 .= $checkDigit;

        return $isbn10;
    }

    /**
     * OpenBD APIから書籍情報を取得
     */
    private function getBookInfo(string $openBdUrl): ?array
    {
        $response = Http::get($openBdUrl);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        if (empty($data) || $data[0] === null) {
            return null;
        }

        return $data;
    }

    /**
     * NDLサーチから書影URLを取得
     */
    private function getBookImage(string $isbn): string
    {
        $url = "https://ndlsearch.ndl.go.jp/thumbnail/{$isbn}.jpg";

        $response = Http::get($url);

        if ($response->status() === 200) {
            return $url;
        }

        return 'no_image.png';
    }

    /**
     * 全角数字を半角に変換し、数字以外の文字を除去
     */
    private function normalizeNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = mb_convert_kana($value, 'n');

        return preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * 出版日文字列（YYYYMMDD または YYYYMM）を date型（Y-m-d）に変換
     */
    private function parsePublishingDate(string $dateStr): ?string
    {
        if (strlen($dateStr) === 8) {
            $parsed = \DateTime::createFromFormat('Ymd', $dateStr);
            return $parsed ? $parsed->format('Y-m-d') : null;
        }

        if (strlen($dateStr) === 6) {
            $parsed = \DateTime::createFromFormat('Ym', $dateStr);
            return $parsed ? $parsed->format('Y-m-01') : null;
        }

        return null;
    }
}
