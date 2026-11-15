<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Fixed-window rate limiter backed by the database.
 *
 * @param string $key           e.g. 'login:ip:1.2.3.4'
 * @param array{0:int,1:int} $limit [max hits, window seconds] (see config 'rate')
 * @return bool true if the action is allowed (and counted), false if the limit is exceeded
 */
function rate_limit_hit(string $key, array $limit): bool
{
    [$max, $window] = $limit;
    $key = substr($key, 0, 190);
    $t = now();
    return (bool) db_transaction(static function () use ($key, $max, $window, $t) {
        $row = db_one('SELECT hits, window_start FROM rate_limits WHERE k = ?', [$key]);
        if ($row === null || $t - (int) $row['window_start'] >= $window) {
            db_exec('INSERT OR REPLACE INTO rate_limits (k, hits, window_start) VALUES (?,?,?)', [$key, 1, $t]);
            return true;
        }
        if ((int) $row['hits'] >= $max) {
            return false;
        }
        db_exec('UPDATE rate_limits SET hits = hits + 1 WHERE k = ?', [$key]);
        return true;
    });
}

/** Forget a counter (e.g. after a successful login). */
function rate_limit_clear(string $key): void
{
    db_exec('DELETE FROM rate_limits WHERE k = ?', [substr($key, 0, 190)]);
}

/** Remove stale counters (called opportunistically). */
function rate_limit_gc(): void
{
    db_exec('DELETE FROM rate_limits WHERE window_start < ?', [now() - 86400]);
}
