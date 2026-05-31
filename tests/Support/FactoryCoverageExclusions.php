<?php

use App\Models\Gamification\UserBadge;
use App\Models\Pets\PetFollow;
use App\Models\Social\Block;

return [
    UserBadge::class => 'Pivot model with no standalone factory semantics. It is managed by owner/pet relationship writes.',
    Block::class => 'Pivot model for user social blocks and belongs to relationship plumbing only.',
    PetFollow::class => 'Pivot model for pet follow relationships, generated through toggle flows.',
];
