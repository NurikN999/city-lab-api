<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplaintRequest;
use App\Models\Complaint;
use App\Models\Sphere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComplaintController extends Controller
{
    private const FEED_HOURS = 24;

    private const FEED_LIMIT = 100;

    /** Активные жалобы за сутки — карта опрашивает раз в несколько секунд. */
    public function index(): JsonResponse
    {
        return response()->json(Complaint::with('sphere:id,key')
            ->whereIn('status', Complaint::ACTIVE)
            ->where('created_at', '>=', now()->subHours(self::FEED_HOURS))
            ->latest('id')
            ->limit(self::FEED_LIMIT)
            ->get()
            ->map->toFeed());
    }

    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $category = $request->validated('category');
        $complaint = Complaint::create([
            'district_id' => $request->validated('district_id'),
            'sphere_id' => $category === 'other' ? null : Sphere::where('key', $category)->value('id'),
            'text' => trim($request->validated('text')),
        ]);

        return response()->json($complaint->refresh()->load('sphere:id,key')->toFeed(), 201);
    }

    public function update(Request $request, Complaint $complaint): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['accepted', 'resolved', 'hidden'])]]);
        $complaint->update($data);

        return response()->json($complaint->load('sphere:id,key')->toFeed());
    }
}
