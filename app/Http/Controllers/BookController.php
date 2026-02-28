<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Builder;

class BookController extends Controller
{
    public function index(Request $request)
    {
        $query = Book::query();

        // 検索条件の取得
        $conditions = $request->input('conditions', []);

        if (!empty($conditions) && is_array($conditions)) {
            $query->where(function (Builder $q) use ($conditions) {
                // ループで条件を組み立てる
                $isFirst = true;
                $currentOperator = 'and'; // The operator linking to the next condition

                foreach ($conditions as $index => $condition) {
                    $type = $condition['type'] ?? null;
                    $value = $condition['value'] ?? null;

                    if (!$type || !$value) {
                        continue;
                    }

                    // 検索カラムの決定
                    $columns = [];
                    switch ($type) {
                        case 'title':
                            $columns = ['title', 'subtitle'];
                            break;
                        case 'content':
                            $columns = ['content'];
                            break;
                        case 'author':
                            $columns = ['contributor'];
                            break;
                        case 'publisher':
                            $columns = ['publisher', 'imprint'];
                            break;
                        case 'published_date':
                            $columns = ['published_date'];
                            break;
                    }

                    if (empty($columns)) {
                        continue;
                    }

                    // Build the condition for the current field
                    $conditionClosure = function (Builder $subQ) use ($columns, $value, $type) {
                        foreach ($columns as $colIndex => $column) {
                            $method = $colIndex === 0 ? 'where' : 'orWhere';
                            if ($type === 'published_date') {
                                $subQ->$method($column, '=', $value);
                            } else {
                                $subQ->$method($column, 'like', '%' . $value . '%');
                            }
                        }
                    };

                    // Add condition to main query using previously determined operator
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

                    // Update operator for the NEXT condition in the loop
                    $currentOperator = strtolower($condition['operator'] ?? 'and');
                }
            });
        }

        // 並び順などの指定があればここで追加（とりあえず新着順）
        $query->orderBy('published_date', 'desc')->orderBy('id', 'desc');

        // ページネーション (Inertia には Paginator インスタンスをそのまま渡せる)
        $books = $query->paginate(20)->withQueryString();

        return Inertia::render('Welcome', [
            'books' => $books,
            'filters' => $request->only(['conditions']),
            'laravelVersion' => app()->version(),
        ]);
    }
}
