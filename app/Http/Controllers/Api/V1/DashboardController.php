<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardAnomalyService;
use App\Services\Dashboard\DashboardOverviewService;
use App\Services\Dashboard\DashboardTrendsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get overview KPIs for dashboard cards.
     */
    public function overview(
        Request $request,
        DashboardOverviewService $service
    ): JsonResponse {
        $this->authorizeDashboardAccess($request);

        return response()->json($service->getOverview());
    }

    /**
     * Get trend data for charts (last N days).
     */
    public function trends(
        Request $request,
        DashboardTrendsService $service
    ): JsonResponse {
        $this->authorizeDashboardAccess($request);

        $days = $request->integer('days', 7);

        return response()->json($service->getTrends($days));
    }

    /**
     * Get anomalies and issues for review.
     */
    public function anomalies(
        Request $request,
        DashboardAnomalyService $service
    ): JsonResponse {
        $this->authorizeDashboardAccess($request);

        $limit = $request->integer('limit', 10);

        return response()->json($service->getAnomalies($limit));
    }

    /**
     * Authorize dashboard access for admins and representatives.
     */
    private function authorizeDashboardAccess(Request $request): void
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isRepresentative()) {
            abort(403, 'Unauthorized');
        }
    }
}
