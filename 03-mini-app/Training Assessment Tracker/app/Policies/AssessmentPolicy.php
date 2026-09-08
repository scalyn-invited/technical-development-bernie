<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\DevelopmentPlan;
use App\Models\User;

class AssessmentPolicy
{
    /**
     * Reading a score follows the plan it belongs to: the member sees their
     * own, the administrator sees any.
     */
    public function view(User $user, Assessment $assessment): bool
    {
        return $user->isAdministrator()
            || $user->id === $assessment->developmentPlan->user_id;
    }

    /**
     * Recording a baseline or final score.
     *
     * Authorised via Gate::authorize('create', [Assessment::class, $plan]).
     *
     * Two conditions, both required:
     *   1. Only an administrator records scores. A member never writes a score,
     *      not even their own outcome.
     *   2. Nobody records a score on their own plan — administrator included.
     *      This is the integrity rule; without it the improvement delta is
     *      self-reported and measures nothing.
     */
    public function create(User $user, DevelopmentPlan $plan): bool
    {
        return $user->isAdministrator() && $user->id !== $plan->user_id;
    }

    /**
     * Amending a score. Same two conditions. Whether the amendment is *allowed
     * at this point in the plan's life* — baselines are immutable once the plan
     * is active — is a state-machine question, not an authorisation one, and
     * belongs to the ProgrammeProgressionService on Day 13. Keeping the two
     * apart means a 403 always means "not your call" and never "wrong moment".
     */
    public function update(User $user, Assessment $assessment): bool
    {
        return $user->isAdministrator()
            && $user->id !== $assessment->developmentPlan->user_id;
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        return $this->update($user, $assessment);
    }
}
