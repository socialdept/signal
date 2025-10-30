<?php

namespace SocialDept\Signal\Enums;

enum SignalCommitOperation: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
