<?php

namespace Modules\Tasks\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tasks\Services\GlobalSearchService;

class GlobalSearchController extends Controller
{
    public function __construct(
        protected GlobalSearchService $searchService
    ) {}

    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q', '');
        $limit = (int) $request->query('limit', 10);

        $results = $this->searchService->search($query, $limit);

        return response()->json(['data' => $results]);
    }
}
