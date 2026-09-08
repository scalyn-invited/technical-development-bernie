<?php

namespace App\Policies;

use App\Models\Skill;
use App\Models\User;

class SkillPolicy
{
    /** Both roles read the catalogue; a member needs it to read their own plan. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Skill $skill): bool
    {
        return true;
    }

    /** The catalogue is administered, not crowd-sourced. */
    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    /** Rename or deactivate. Deletion is not a permitted action at all. */
    public function update(User $user, Skill $skill): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Never. Skills are retired by deactivation because deletion would orphan
     * recorded scores. `restrictOnDelete` enforces the same rule at the database
     * level; this is the same rule stated at the authorisation level, so the
     * refusal is a 403 rather than a database error surfacing as a 500.
     */
    public function delete(User $user, Skill $skill): bool
    {
        return false;
    }
}
