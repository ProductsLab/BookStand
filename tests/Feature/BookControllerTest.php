<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 書籍検索・新刊一覧・RSSインポート機能のFeatureテスト
 */
class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * トップページ（/）で一般書籍が表示され、成人指定本が除外されることを検証
     */
    public function test_home_page_displays_books_excluding_adult_books(): void
    {
        $normalBook = Book::factory()->create([
            'title' => '一般向けの技術書',
            'audience_type' => 99,
            'audience_code' => 99,
        ]);

        $adultBook = Book::factory()->create([
            'title' => '成人指定の書籍',
            'audience_type' => 22,
            'audience_code' => 1,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('books.data', 1)
                ->where('books.data.0.id', $normalBook->id)
            );
    }

    /**
     * キーワード検索の AND / OR 結合条件が正しく動作することを検証
     */
    public function test_search_by_keyword_conditions_with_and_or_operators(): void
    {
        $bookA = Book::factory()->create([
            'title' => 'Laravel実践入門',
            'contributor' => '山田太郎',
            'imprint' => '技術評論社',
        ]);

        $bookB = Book::factory()->create([
            'title' => 'Vue.jsモダン開発',
            'contributor' => '佐藤花子',
            'imprint' => 'オライリー・ジャパン',
        ]);

        $bookC = Book::factory()->create([
            'title' => 'データベース設計',
            'contributor' => '鈴木一郎',
            'imprint' => '翔泳社',
        ]);

        // OR検索: Laravel OR Vue.js
        $response = $this->get('/books/search?' . http_build_query([
            'conditions' => [
                ['type' => 'title', 'value' => 'Laravel', 'operator' => 'OR'],
                ['type' => 'title', 'value' => 'Vue.js', 'operator' => 'AND'],
            ],
        ]));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('books.data', 2)
            );

        // AND検索: Laravel AND 山田太郎
        $responseAnd = $this->get('/books/search?' . http_build_query([
            'conditions' => [
                ['type' => 'title', 'value' => 'Laravel', 'operator' => 'AND'],
                ['type' => 'author', 'value' => '山田太郎', 'operator' => 'AND'],
            ],
        ]));

        $responseAnd->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('books.data', 1)
                ->where('books.data.0.id', $bookA->id)
            );
    }

    /**
     * Cコード（ジャンル）絞り込みと「未分類を含める」チェックの動作を検証
     */
    public function test_search_by_c_code_genre_and_unclassified_toggle(): void
    {
        $novelBook = Book::factory()->create([
            'title' => '小説サンプル',
            'c_code' => '0193', // 一般(0) + 文庫(1) + 日本文学小説(93)
            'audience_type' => 99,
            'audience_code' => 99,
        ]);

        $itBook = Book::factory()->create([
            'title' => 'IT専門書サンプル',
            'c_code' => '3055', // 専門(3) + 単行本(0) + 電子通信(55)
            'audience_type' => 99,
            'audience_code' => 99,
        ]);

        $unclassifiedBook = Book::factory()->create([
            'title' => '未分類サンプル',
            'c_code' => '9999',
            'audience_type' => 99,
            'audience_code' => 99,
        ]);

        // 文学 (9_) のみ検索（未分類含めない → 9999が誤ヒットしないことも検証）
        $response = $this->get('/books/search?' . http_build_query([
            'c_code_1' => '_',
            'c_code_2' => '_',
            'c_code_3' => '9_',
            'include_unclassified' => 0,
        ]));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('books.data', 1)
                ->where('books.data.0.id', $novelBook->id)
            );

        // 文学 (9_) + 未分類を含める
        $responseWithUnclassified = $this->get('/books/search?' . http_build_query([
            'c_code_1' => '_',
            'c_code_2' => '_',
            'c_code_3' => '9_',
            'include_unclassified' => 1,
        ]));

        $responseWithUnclassified->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('books.data', 2)
            );
    }

    /**
     * 「今日の本 (/today)」「明日の本 (/tomorrow)」が出版日でフィルタされることを検証
     */
    public function test_today_and_tomorrow_pages_filter_by_published_date(): void
    {
        $todayStr = now()->toDateString();
        $tomorrowStr = now()->addDay()->toDateString();

        $todayBook = Book::factory()->create([
            'title' => '今日発売の本',
            'published_date' => $todayStr,
            'audience_type' => 99,
            'audience_code' => 99,
        ]);

        $tomorrowBook = Book::factory()->create([
            'title' => '明日発売の本',
            'published_date' => $tomorrowStr,
            'audience_type' => 99,
            'audience_code' => 99,
        ]);

        $this->get('/today')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Today')
                ->where('targetDate', $todayStr)
                ->has('books.data', 1)
                ->where('books.data.0.id', $todayBook->id)
            );

        $this->get('/tomorrow')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tomorrow')
                ->where('targetDate', $tomorrowStr)
                ->has('books.data', 1)
                ->where('books.data.0.id', $tomorrowBook->id)
            );
    }

    /**
     * `php artisan book:save --today` が版元ドットコムRSSからISBNを抽出しOpenBD経由でDB保存することを検証
     */
    public function test_save_book_command_imports_from_rss(): void
    {
        $rssXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>版元ドットコム</title>
    <item>
      <title>テスト新刊書籍</title>
      <link>https://www.hanmoto.com/bd/isbn/9784000010887</link>
    </item>
  </channel>
</rss>
XML;

        Http::fake([
            'https://www.hanmoto.com/*' => Http::response($rssXml, 200),
            'https://api.openbd.jp/*' => Http::response([
                [
                    'onix' => [
                        'RecordReference' => '9784000010887',
                        'DescriptiveDetail' => [
                            'TitleDetail' => [
                                'TitleElement' => [
                                    'TitleText' => ['content' => 'RSSテスト書籍'],
                                    'Subtitle' => ['content' => 'サブタイトル'],
                                ],
                            ],
                            'Contributor' => [
                                ['PersonName' => ['content' => 'テスト著者']],
                            ],
                            'Subject' => [
                                ['SubjectSchemeIdentifier' => 78, 'SubjectCode' => '0093'],
                            ],
                        ],
                        'CollateralDetail' => [
                            'TextContent' => [
                                ['Text' => '内容紹介テスト'],
                            ],
                        ],
                        'PublishingDetail' => [
                            'Imprint' => ['ImprintName' => 'テスト出版'],
                            'Publisher' => ['PublisherName' => 'テスト販売'],
                            'PublishingDate' => [
                                ['Date' => '20261022'],
                            ],
                        ],
                        'ProductSupply' => [
                            'SupplyDetail' => [
                                'Price' => [
                                    ['PriceAmount' => '1800'],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'https://ndlsearch.ndl.go.jp/*' => Http::response('', 404),
        ]);

        $this->artisan('book:save --today')
            ->assertExitCode(0);

        $this->assertDatabaseHas('books', [
            'isbn' => '9784000010887',
            'title' => 'RSSテスト書籍',
            'imprint' => 'テスト出版',
            'c_code' => '0093',
            'price' => 1800,
        ]);
    }
}
