<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function overview()
    {
        return response()->json([
            'total_resources' => Resource::validated()->count(),
            'total_pending' => Resource::pending()->count(),
            'total_users' => User::count(),
            'total_categories' => Category::count(),
        ]);
    }

    public function popularResources()
    {
        $resources = Resource::validated()
            ->withCount('consultations')
            ->orderByDesc('consultations_count')
            ->limit(10)
            ->get(['id', 'title', 'category_id']);

        return response()->json($resources);
    }

    public function resourcesByCategory()
    {
        $data = Category::withCount(['resources' => function ($query) {
            $query->where('status', 'validated');
        }])->get(['id', 'name', 'parent_id']);

        return response()->json($data);
    }

    public function activityTrend()
    {
        $trend = Resource::validated()
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($trend);
    }
}
