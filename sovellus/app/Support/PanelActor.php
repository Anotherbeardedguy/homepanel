<?php

namespace App\Support;

use App\Models\Device;
use App\Models\User;

class PanelActor
{
    public function __construct(
        public readonly ?User $user = null,
        public readonly ?Device $device = null,
    ) {}

    public function canCompleteChores(): bool
    {
        if ($this->user !== null) {
            return true;
        }

        return (bool) $this->device?->can_complete;
    }

    public function canReopenChores(): bool
    {
        return $this->user !== null;
    }
}
