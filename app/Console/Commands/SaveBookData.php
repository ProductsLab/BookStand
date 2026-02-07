<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SaveBookData extends Command
{
    protected $signature = 'book:save {isbn? : 単一のISBN} {--csv= : ISBNが記載されたCSVファイルパス} {--chunk=100 : 一括処理の件数}';
    protected $description = 'ISBNから書籍データを取得して保存する';

    public function handle(): int
    {
        $csvPath = $this->option('csv');
        $isbn = $this->argument('isbn');

        if ($csvPath) {
            return $this->importFromCsv($csvPath);
        }

        if (!$isbn) {
            $this->error('ISBNまたは--csvオプションを指定してください。');
            return Command::FAILURE;
        }

        return $this->saveBook($this->normalizeNumber($isbn));
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

        // 登録済みISBNを一括チェック
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
     * チャンク単位で書籍データを一括取得・保存
     */
    private function processChunk(array $isbns, int $processedSoFar, int $total): array
    {
        // OpenBD APIへ一括リクエスト
        $isbnStr = implode(',', $isbns);
        $response = Http::get("https://api.openbd.jp/v1/get?isbn={$isbnStr}");

        if ($response->failed()) {
            $count = count($isbns);
            $this->error("OpenBD APIリクエスト失敗 ({$count} 件)");
            return [0, $count];
        }

        $bookDataList = $response->json();

        // 書影URLを並列チェック
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

            // 基本情報の取得
            $bookTitle = $onix['DescriptiveDetail']['TitleDetail']['TitleElement']['TitleText']['content'] ?? null;
            $bookSubtitle = $onix['DescriptiveDetail']['TitleDetail']['TitleElement']['Subtitle']['content'] ?? null;
            $bookContributor = $onix['DescriptiveDetail']['Contributor'][0]['PersonName']['content'] ?? null;

            $bookContent = $this->buildBookContent($onix);

            // 出版情報
            $bookImprint = $onix['PublishingDetail']['Imprint']['ImprintName'] ?? null;
            $bookPublisher = $onix['PublishingDetail']['Publisher']['PublisherName'] ?? null;
            $bookPrice = $this->normalizeNumber($onix['ProductSupply']['SupplyDetail']['Price'][0]['PriceAmount'] ?? null);
            $bookDate = $this->normalizeNumber($onix['PublishingDetail']['PublishingDate'][0]['Date'] ?? null);

            // 書影
            $thumbnailResponse = $thumbnailResponses[$isbn] ?? null;
            $bookPicture = ($thumbnailResponse instanceof Response && $thumbnailResponse->status() === 200)
                ? "https://ndlsearch.ndl.go.jp/thumbnail/{$isbn}.jpg"
                : 'no_image.png';

            // 対象読者
            $audienceType = $this->normalizeNumber($onix['DescriptiveDetail']['Audience'][0]['AudienceCodeType'] ?? null) ?? 99;
            $audienceCode = $this->normalizeNumber($onix['DescriptiveDetail']['Audience'][0]['AudienceCodeValue'] ?? null) ?? 99;

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

            $publishedDate = null;
            if ($bookDate) {
                $publishedDate = $this->parsePublishingDate($bookDate);
            }

            $amazonUrl = "https://www.amazon.co.jp/dp/{$isbn10}";
            $hontoUrl = "http://honto.jp/redirect.html?bookno=" . substr($isbn, 0, -1);

            $book = Book::create([
                'isbn' => $isbn,
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
            ]);

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
        $bookPrice = $this->normalizeNumber($onix['ProductSupply']['SupplyDetail']['Price'][0]['PriceAmount'] ?? null);
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

        $book = Book::create([
            'isbn' => $isbn,
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
        ]);

        $this->info("{$prefix}保存: {$book->title} (ISBN: {$isbn})");

        return Command::SUCCESS;
    }

    /**
     * 内容紹介テキストの組み立て
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

        return strip_tags($bookContent, '<br>');
    }

    /**
     * Cコード・件名の抽出
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
            }
        }

        return [$cCode, $subjectWord];
    }

    /**
     * ISBN-13 → ISBN-10 変換
     */
    private function isbnConvert(string $isbn): string
    {
        $digits = array_map('intval', str_split($isbn));

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
     * 出版日文字列をdate型に変換
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
