<?php

namespace SocialDept\Signals\Enums;

enum SignalEventType: string
{
    case Commit = 'commit';
    case Identity = 'identity';
    case Account = 'account';
}
