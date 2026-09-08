<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexSkillRequest;
use App\Http\Requests\StoreSkillRequest;
use App\Http\Requests\UpdateSkillRequest;
use App\Http\Resources\SkillResource;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SkillController extends Controller
{
    /**
     * The skill catalogue, paginated.
     *
     * `?active=true` filters on the indexed column; omitting the filter returns
     * retired skills too, because a retired skill still appears against every
     * score already recorded for it and a client reading a plan needs to be
     * able to name it.
     *
     * withQueryString() keeps the filter on the generated page links. Without
     * it, page 2 of a filtered list quietly becomes page 2 of everything.
     */
    public function index(IndexSkillRequest $request): AnonymousResourceCollection
    {
        $skills = Skill::query()
            ->when(
                $request->has('active'),
                fn ($query) => $query->where('is_active', $request->boolean('active'))
            )
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return SkillResource::collection($skills);
    }

    /**
     * 201, and the created row read back through the same Resource the list
     * uses — so a client never has to parse two shapes for one entity.
     */
    public function store(StoreSkillRequest $request): JsonResponse
    {
        $skill = Skill::create($request->validated() + [
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return (new SkillResource($skill))->response()->setStatusCode(201);
    }

    /**
     * Rename or deactivate. There is deliberately no destroy(): the Policy
     * refuses deletion for both roles and restrictOnDelete refuses it at the
     * database, so the route does not exist at all and DELETE returns the 405
     * envelope.
     */
    public function update(UpdateSkillRequest $request, Skill $skill): SkillResource
    {
        $skill->update($request->validated());

        return new SkillResource($skill);
    }
}
