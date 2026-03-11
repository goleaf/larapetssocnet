<?php

namespace App\Enums;

enum FollowAbility
{
    case Follow;

    case Unfollow;

    case ViewFollowers;

    case ViewFollowing;

    case ManageRequests;

    case RemoveFollower;
}
