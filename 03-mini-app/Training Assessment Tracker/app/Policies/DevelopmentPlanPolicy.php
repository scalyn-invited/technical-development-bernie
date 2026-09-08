<?php

namespace App\Policies;

use App\Models\DevelopmentPlan;
use App\Models\User;

class DevelopmentPlanPolicy
{
    /**
     * Both roles may list plans. The *scope* of that list is not an
     * authorisation question — a member's index is filtered to their own plan
     * in the query (Day 12), because returning 403 for a list is the wrong
     * answer to "show me what I am allowed to see".
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** A member reads only their own plan. An administrator reads any. */
    public function view(User $user, DevelopmentPlan $plan): bool
    {
        return $user->isAdministrator() || $user->id === $plan->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Editing the plan's own fields (key_gaps, weekly_focus) and driving its
     * status transitions. Administrators only — and never on their own plan,
     * because activating and completing a plan is part of the measurement.
     */
    public function update(User $user, DevelopmentPlan $plan): bool
    {
        return $user->isAdministrator() && ! $this->isOwnPlan($user, $plan);
    }

    public function delete(User $user, DevelopmentPlan $plan): bool
    {
        return $user->isAdministrator() && ! $this->isOwnPlan($user, $plan);
    }

    /**
     * The comparison read — baseline against final. The member is entitled to
     * see their own result; the administrator sees any.
     */
    public function viewComparison(User $user, DevelopmentPlan $plan): bool
    {
        return $this->view($user, $plan);
    }

    /**
     * The integrity rule, stated once and reused by every scoring policy.
     *
     * Nobody scores their own plan. An administrator who also holds a
     * development plan is a member for the purposes of that plan, and a score
     * they record on it is self-assessment — which invalidates the measurement
     * the tool exists to produce. This is a 403, not an omission from the UI,
     * because the UI is not the boundary.
     */
    public function isOwnPlan(User $user, DevelopmentPlan $plan): bool
    {
        return $user->id === $plan->user_id;
    }
}
