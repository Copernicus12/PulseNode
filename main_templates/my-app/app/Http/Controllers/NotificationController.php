<?php

namespace App\Http\Controllers;

use App\Support\AppNotificationStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, AppNotificationStore $notifications): View
    {
        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->integer('page', 1));
        $filters = [
            'level' => $this->filterLevel($request),
            'type' => $this->filterType($request),
        ];
        $sortBy = $this->sortBy($request);

        $paginator = $notifications->paginate($perPage, $page, $filters, $sortBy)->withQueryString();
        $summary = $notifications->summary($filters);

        return view('notifications.index', [
            'notifications' => $paginator,
            'perPage' => $perPage,
            'perPageOptions' => [10, 25, 50, 100],
            'summary' => $summary,
            'filters' => $filters,
            'sortBy' => $sortBy,
            'levelOptions' => [
                'all' => 'All levels',
                'info' => 'Info',
                'success' => 'Success',
                'warning' => 'Warning',
                'error' => 'Error',
            ],
            'typeOptions' => $notifications->availableTypes(),
            'sortOptions' => [
                'newest' => 'Newest first',
                'oldest' => 'Oldest first',
                'level' => 'Level A-Z',
            ],
        ]);
    }

    public function latest(Request $request, AppNotificationStore $notifications): JsonResponse
    {
        $limit = min(10, max(1, (int) $request->integer('limit', 10)));

        return response()->json([
            'status' => 'ok',
            'notifications' => $notifications->latest($limit),
        ]);
    }

    private function perPage(Request $request): int
    {
        $value = (int) $request->integer('per_page', 10);

        return in_array($value, [10, 25, 50, 100], true) ? $value : 10;
    }

    private function filterLevel(Request $request): ?string
    {
        $value = trim((string) $request->string('filter_level', ''));

        return in_array($value, ['info', 'success', 'warning', 'error'], true) ? $value : null;
    }

    private function filterType(Request $request): ?string
    {
        $value = trim((string) $request->string('filter_type', ''));

        return $value !== '' ? $value : null;
    }

    private function sortBy(Request $request): string
    {
        $value = trim((string) $request->string('sort_by', 'newest'));

        return in_array($value, ['newest', 'oldest', 'level'], true) ? $value : 'newest';
    }
}
