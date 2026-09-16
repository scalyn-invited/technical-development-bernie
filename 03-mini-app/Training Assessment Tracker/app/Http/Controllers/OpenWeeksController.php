<?php

namespace App\Http\Controllers;

use App\Http\Resources\WeeklyEntryResource;
use App\Models\WeeklyEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OpenWeeksController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'status' => ['sometimes', 'in:planned,evidenced'],
        ]);

        return WeeklyEntryResource::collection(
            WeeklyEntry::query()->open()
                ->with(['skill', 'developmentPlan.member'])
                ->when(! $request->user()->isAdministrator(), fn ($query) => $query
                    ->whereHas('developmentPlan', fn ($plans) => $plans->where('user_id', $request->user()->id)))
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
                ->orderBy('week_number')->orderBy('id')
                ->paginate($request->integer('per_page', 15))->withQueryString()
        );
    }
}
