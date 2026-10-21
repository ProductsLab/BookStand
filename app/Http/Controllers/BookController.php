<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * 書籍検索および新刊一覧（「今日の本」「明日の本」）を制御するコントローラ
 */
class BookController extends Controller
{
    /**
     * 本を探す（トップページ & 検索結果一覧）
     *
     * - 動的なキーワード検索条件（書名・内容・著者名・発行元出版社・出版日・ISBN）の AND/OR 結合
     * - 全角/半角スペース区切りによる複数ワード検索
     * - Cコード（4桁：対象・形態・内容）によるジャンル絞り込みと「未分類を含める」オプション
     * に対応しています。
     */
    public function index(Request $request)
    {
        // 成人指定本を除外したベースクエリを作成
        $query = Book::query()->excludeAdult();

        // リクエストから検索条件パラメータを取得
        $conditions = $request->input('conditions', []);
        $cCode1 = $request->input('c_code_1', '_');
        $cCode2 = $request->input('c_code_2', '_');
        $cCode3 = $request->input('c_code_3', '__');
        $includeUnclassified = filter_var($request->input('include_unclassified', false), FILTER_VALIDATE_BOOLEAN);
        $cCodeOperator = strtolower($request->input('c_code_operator', 'and'));

        // Cコード検索パターン（4文字のSQL LIKE用ワイルドカード文字列）を組み立てる
        // 例: 対象=0(一般), 形態=1(文庫), 内容=9_(文学全般) → '019_'
        // 未選択の項目は '_' または '__'（任意の1〜2文字にマッチ）となる
        $c1 = preg_match('/^[0-9]$/', (string) $cCode1) ? (string) $cCode1 : '_';
        $c2 = preg_match('/^[0-9]$/', (string) $cCode2) ? (string) $cCode2 : '_';
        $c3 = preg_match('/^[0-9][0-9_]$/', (string) $cCode3) ? (string) $cCode3 : '__';
        $cCodePattern = $c1 . $c2 . $c3;

        // すべて未選択（'____'）でない場合のみ、Cコード絞り込みを有効化する
        $hasCCodeFilter = $cCodePattern !== '____';

        // キーワード条件またはCコード条件が指定されている場合に WHERE 句を構築
        if ((!empty($conditions) && is_array($conditions)) || $hasCCodeFilter) {
            $query->where(function (Builder $q) use ($conditions, $hasCCodeFilter, $cCodePattern, $includeUnclassified, $cCodeOperator) {
                $isFirst = true;
                $currentOperator = 'and'; // 次の条件を結合する際の論理演算子（'and' または 'or'）

                // 1. キーワード検索条件のループ処理
                if (!empty($conditions) && is_array($conditions)) {
                    foreach ($conditions as $condition) {
                        $type = $condition['type'] ?? null;
                        $value = isset($condition['value']) ? trim((string) $condition['value']) : '';

                        // 種別または検索値が空の行はスキップ
                        if (!$type || $value === '') {
                            continue;
                        }

                        // 検索種別に応じて対象となるDBカラムを決定
                        $columns = [];
                        switch ($type) {
                            case 'title':
                                // 書名検索はタイトルとサブタイトルの両方を対象にする
                                $columns = ['title', 'subtitle'];
                                break;
                            case 'content':
                                $columns = ['content'];
                                break;
                            case 'author':
                                $columns = ['contributor'];
                                break;
                            case 'publisher':
                                // 出版社検索は発行元(imprint)と発売元(publisher)の両方を対象にする
                                $columns = ['imprint', 'publisher'];
                                break;
                            case 'published_date':
                                $columns = ['published_date'];
                                break;
                            case 'isbn':
                                // ISBN検索は13桁(isbn)と10桁(isbn_10)の両方を対象にする
                                $columns = ['isbn', 'isbn_10'];
                                break;
                        }

                        if (empty($columns)) {
                            continue;
                        }

                        // 出版日以外は、入力値の全角・半角スペースで単語を分割して検索可能にする
                        $keywords = ($type === 'published_date')
                            ? [$value]
                            : (preg_split('/[\s　]+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [$value]);

                        // 1行分の検索条件クロージャを生成
                        $conditionClosure = function (Builder $subQ) use ($columns, $keywords, $type) {
                            foreach ($keywords as $kwIndex => $keyword) {
                                // スペース区切りの複数単語は旧search.phpと同様にORでマッチング
                                $kwMethod = $kwIndex === 0 ? 'where' : 'orWhere';
                                $subQ->$kwMethod(function (Builder $wordQ) use ($columns, $keyword, $type) {
                                    foreach ($columns as $colIndex => $column) {
                                        $method = $colIndex === 0 ? 'where' : 'orWhere';
                                        if ($type === 'published_date') {
                                            // 'YYYY/MM/DD' 形式で入力された場合も 'YYYY-MM-DD' に正規化
                                            $normalizedDate = str_replace('/', '-', $keyword);
                                            $wordQ->$method($column, '=', $normalizedDate);
                                        } elseif ($type === 'isbn') {
                                            // ハイフン付きISBNでも検索できるようハイフンを除去
                                            $normalizedIsbn = str_replace('-', '', $keyword);
                                            $wordQ->$method($column, 'like', '%' . $normalizedIsbn . '%');
                                        } else {
                                            $wordQ->$method($column, 'like', '%' . $keyword . '%');
                                        }
                                    }
                                });
                            }
                        };

                        // 前の条件行で指定された演算子（AND / OR）に従ってメインクエリへ追加
                        if ($isFirst) {
                            $q->where($conditionClosure);
                            $isFirst = false;
                        } else {
                            if ($currentOperator === 'or') {
                                $q->orWhere($conditionClosure);
                            } else {
                                $q->where($conditionClosure);
                            }
                        }

                        // 次の条件行と結合するための演算子を更新
                        $currentOperator = strtolower($condition['operator'] ?? 'and');
                    }
                }

                // 2. ジャンル（Cコード）検索条件の追加
                if ($hasCCodeFilter) {
                    $cCodeClosure = function (Builder $subQ) use ($cCodePattern, $includeUnclassified) {
                        // DBにはCコード未設定の書籍が '9999' として保存されているため、
                        // 例として '__9_'(文学) を検索した際に '9999'(未分類) が誤ってヒットしないよう除外する
                        $subQ->where(function (Builder $innerQ) use ($cCodePattern) {
                            $innerQ->where('c_code', 'like', $cCodePattern)
                                ->where('c_code', '!=', '9999');
                        });

                        // 「未分類を含める」にチェックが入っている場合は '9999', '____', NULL も結果に含める
                        if ($includeUnclassified) {
                            $subQ->orWhereIn('c_code', ['9999', '____'])
                                ->orWhereNull('c_code');
                        }
                    };

                    // キーワード条件が存在しない場合はそのまま WHERE、存在する場合は c_code_operator (AND/OR) で結合
                    if ($isFirst) {
                        $q->where($cCodeClosure);
                    } else {
                        if ($cCodeOperator === 'or') {
                            $q->orWhere($cCodeClosure);
                        } else {
                            $q->where($cCodeClosure);
                        }
                    }
                }
            });
        }

        // 並び順：出版日の新しい順 → IDの降順
        $query->orderBy('published_date', 'desc')->orderBy('id', 'desc');

        // 1ページあたり20件でページネーション（検索クエリパラメータをページリンクに引き継ぐ）
        $books = $query->paginate(20)->withQueryString();

        return Inertia::render('Welcome', [
            'books' => $books,
            'filters' => [
                'conditions' => $request->input('conditions', []),
                'c_code_1' => $c1,
                'c_code_2' => $c2,
                'c_code_3' => $c3,
                'include_unclassified' => $includeUnclassified,
                'c_code_operator' => strtoupper($cCodeOperator) === 'OR' ? 'OR' : 'AND',
            ],
            'laravelVersion' => app()->version(),
        ]);
    }

    /**
     * 今日の本一覧（旧 today_book.php 相当）
     *
     * デフォルトでは本日（JST）が出版日の書籍を一覧表示します。
     * クエリパラメータ `?date=YYYY-MM-DD` が渡された場合は指定日の書籍を表示します。
     */
    public function today(Request $request)
    {
        $dateInput = $request->input('date');
        $targetDate = ($dateInput && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateInput))
            ? $dateInput
            : now()->toDateString();

        $books = Book::query()
            ->excludeAdult()
            ->whereDate('published_date', $targetDate)
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Today', [
            'books' => $books,
            'targetDate' => $targetDate,
        ]);
    }

    /**
     * 明日の本一覧（旧 soon_book.php 相当）
     *
     * デフォルトでは明日（JST）が出版日の書籍を一覧表示します。
     * クエリパラメータ `?date=YYYY-MM-DD` が渡された場合は指定日の書籍を表示します。
     */
    public function tomorrow(Request $request)
    {
        $dateInput = $request->input('date');
        $targetDate = ($dateInput && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateInput))
            ? $dateInput
            : now()->addDay()->toDateString();

        $books = Book::query()
            ->excludeAdult()
            ->whereDate('published_date', $targetDate)
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Tomorrow', [
            'books' => $books,
            'targetDate' => $targetDate,
        ]);
    }
}
