<?php

namespace SocialDept\Signal\Enums;

enum SignalEventType: string
{
    case Commit = 'commit';
    case Identity = 'identity';
    case Account = 'account';
}
