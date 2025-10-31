<?php

namespace SocialDept\Signals\Enums;

enum SignalCommitOperation: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
