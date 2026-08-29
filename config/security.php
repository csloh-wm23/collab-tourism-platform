<?php
declare(strict_types=1);

/** Return the next failed-login state without touching the database. */
function failed_login_state(int $currentAttempts,int $limit=5,int $lockMinutes=15):array
{
    $attempts=max(0,$currentAttempts)+1;
    if($attempts >= $limit)return ['attempts'=>0,'lock_minutes'=>$lockMinutes,'locked'=>true];
    return ['attempts'=>$attempts,'lock_minutes'=>0,'locked'=>false];
}
