<?php

namespace Pirabyte\InvisionSocialite;

use Laravel\Socialite\Two\User;

/**
 * Invision Community User instance.
 *
 * Extends the base Socialite User class with Invision-specific helper methods.
 */
class InvisionUser extends User
{
    /**
     * Get the member ID.
     *
     * @return string|null
     */
    public function memberId(): ?string
    {
        return $this->getId();
    }

    /**
     * Get the avatar URL.
     *
     * @return string|null
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar;
    }

    /**
     * Get the full name of the user.
     *
     * @return string|null
     */
    public function fullName(): ?string
    {
        return $this->name;
    }

    /**
     * Get the raw user data from Invision Community.
     *
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        return $this->user;
    }
}
