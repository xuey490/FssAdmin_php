<?php

declare(strict_types=1);

namespace App\Models;

use Framework\Basic\BaseLaORMModel;

/**
 * SysAccessLog 访问日志模型（对应 sa_system_access_log 表）
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string|null $user_name
 * @property string      $ip
 * @property string      $method
 * @property string      $path
 * @property string|null $query_string
 * @property int         $status_code
 * @property int         $duration_ms
 * @property string|null $user_agent
 * @property string|null $referer
 * @property string|null $request_body
 * @property \DateTime   $created_at
 * @property mixed $created_by
 * @property mixed $updated_by
 * @property mixed $updated_at
 * @property mixed $deleted_at
 * @property mixed $create_time
 * @property mixed $update_time
 * @property mixed $delete_time
 * @property mixed $tenant_id
 */
class SysAccessLog extends BaseLaORMModel
{
    /**
     * @var string
     */
    protected $table = 'sa_system_access_log';

    /**
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * @var bool
     */
    public $incrementing = true;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'user_name',
        'ip',
        'method',
        'path',
        'query_string',
        'status_code',
        'duration_ms',
        'user_agent',
        'referer',
        'request_body',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * @param array<array-key, mixed> $data
     */
    public static function record(array $data): static
    {
        return self::create(array_merge([
            'created_at' => date('Y-m-d H:i:s'),
        ], $data));
    }
}
