<?php

namespace SocialDept\AtpSignals\Enums;

enum SignalCommitOperation: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
