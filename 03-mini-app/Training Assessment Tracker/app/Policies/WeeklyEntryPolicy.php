<?php

namespace App\Policies;

use App\Models\DevelopmentPlan;
use App\Models\User;
use App\Models\WeeklyEntry;

class WeeklyEntryPolicy
{
    /** A member reads their own weekly entries; an administrator reads any. */
    public function view(User $user, WeeklyEntry $entry): bool
    {
        return $user->isAdministrator()
            || $user->id === $entry->developmentPlan->user_id;
    }

    /**
     * Logging a week's objective.
     *
     * Authorised via Gate::authorize('create', [WeeklyEntry::class, $plan]).
     *
     * The same two conditions as a score: administrators only, and never on
     * their own plan. A weekly entry carries an outcome_score, so it is a
     * scoring action even though it reads as progress tracking — treating it
     * as anything softer would leave a hole straight through the integrity
     * rule.
     */
    public function create(User $user, DevelopmentPlan $plan): bool
    {
        return $user->isAdministrator() && $user->id !== $plan->user_id;
    }

    /** Recording evidence, an outcome, or closing the week. */
    public function update(User $user, WeeklyEntry $entry): bool
    {
        return $user->isAdministrator()
            && $user->id !== $entry->developmentPlan->user_id;
    }

    /** Closing is the consequential half of update, named for the matrix. */
    public function close(User $user, WeeklyEntry $entry): bool
    {
        return $this->update($user, $entry);
    }

    public function delete(User $user, WeeklyEntry $entry): bool
    {
        return $this->update($user, $entry);
    }
}
