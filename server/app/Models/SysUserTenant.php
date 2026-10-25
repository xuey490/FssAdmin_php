<?php

declare(strict_types=1);

/**
 * 用户-租户关联模型
 *
 * @package App\Models
 * @author  Genie
 * @date    2026-03-19
 
*/

namespace App\Models;

use Framework\Basic\BaseLaORMModel;
use Framework\Basic\Scopes\LaTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * SysUserTenant 用户-租户关联模型
 *
 * 多对多关联表模型，支持一个用户属于多个租户
 *
 * @property int         $id             主键ID
 * @property int         $user_id        用户ID
 * @property int         $tenant_id      租户ID
 * @property int         $is_default     是否默认租户：0=否 1=是
 * @property int         $is_super       是否租户管理员：0=否 1=是
 * @property \DateTime    $join_time      加入时间
 * @property int         $created_by     创建人ID
 * @property int         $updated_by     更新人ID
 * @property \DateTime    $created_at     创建时间
 * @property \DateTime    $updated_at     更新时间
 *
 * @property-read SysUser   $user        关联用户
 * @property-read SysTenant $tenant      关联租户
 
 * @property string $create_time
 * @property string $update_time
 * @property mixed $status
 * @property mixed $remark
 * @property mixed $delete_time
 * @property mixed $deleted_at
*/
class SysUserTenant extends BaseLaORMModel
{
    /**
     * 表名
     * @var string
     * @return mixed
     */
    protected $table = 'sa_system_user_tenant';

    /**
     * 主键
     * @var string
     * @return mixed
     */
    protected $primaryKey = 'id';
    /**
     * 自定义时间戳字段名
     */
    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';
    const DELETED_AT = 'delete_time';

    /**
     * 可填充字段
     * @var array<int, string>
     * @return mixed
     */
    protected $fillable = [
        'user_id',
        'tenant_id',
        'is_default',
        'is_super',
        'join_time',
        'created_by',
        'updated_by',
    ];

    /**
     * 类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'tenant_id' => 'integer',
        'is_default' => 'integer',
        'is_super' => 'integer',
        'join_time' => 'datetime',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    // ==================== 关联关系 ====================

    /**
     * 关联用户
     *
     * @return BelongsTo<SysUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(SysUser::class, 'user_id', 'id');
    }

    /**
     * 关联租户
     *
     * @return BelongsTo<SysTenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(SysTenant::class, 'tenant_id', 'id');
    }

    // ==================== 查询方法 ====================

    /**
     * 获取用户的所有租户关联
     *
     * @param int $userId 用户ID
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function getByUserId(int $userId)
    {
        return self::where('user_id', $userId)
            ->with('tenant')
            ->get();
    }

    /**
     * 获取租户的所有用户关联
     *
     * @param int $tenantId 租户ID
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function getByTenantId(int $tenantId)
    {
        return self::where('tenant_id', $tenantId)
            ->with('user')
            ->get();
    }

    /**
     * 获取用户的默认租户ID
     *
     * @param int $userId 用户ID
     * @return int|null 默认租户ID，无默认返回 null
     */
    public static function getDefaultTenantId(int $userId): ?int
    {
        $record = self::where('user_id', $userId)
            //->where('is_default', true)
            ->first();

        return $record ? $record->tenant_id : null;
    }

    /**
     * 获取用户的默认租户关联
     *
     * @param int $userId 用户ID
     * @return self|null
     */
    public static function getDefaultTenant(int $userId): ?self
    {
        return self::where('user_id', $userId)
            ->where('is_default', 1)
            ->with('tenant')
            ->first();
    }

    /**
     * 获取用户可访问的所有租户ID列表
     *
     * @param int $userId 用户ID
     * @return array<array-key, mixed> 租户ID数组
     */
    public static function getTenantIdsByUser(int $userId): array
    {
        return self::where('user_id', $userId)
            ->pluck('tenant_id')
            ->toArray();
    }

    /**
     * 获取用户可访问的租户列表
     *
     * @param int $userId 用户ID
     * @return array<array-key, mixed> 租户信息数组
     */
    public static function getTenantsByUser(int $userId): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, static> $records */
        $records = self::withoutTenancy()
            ->where('user_id', $userId)
            ->with(['tenant' => function (Relation $relation) {
                $relation->getQuery()->withoutGlobalScope(LaTenantScope::class);
            }])
            ->get();

        return $records->map(function ($item) {
                if (!$item->tenant) {
                    return null;
                }
                return [
                    'id' => $item->tenant->id,
                    'name' => $item->tenant->tenant_name,
                    'code' => $item->tenant->tenant_code,
                    'is_default' => $item->is_default,
                    'status' => $item->tenant->status,
                ];
            })
            ->filter()
            ->toArray();
    }

    /**
     * 检查用户是否属于指定租户
     *
     * @param int $userId 用户ID
     * @param int $tenantId 租户ID
     * @return bool
     */
    public static function isUserInTenant(int $userId, int $tenantId): bool
    {
        return self::withoutTenancy()
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    /**
     * 检查用户是否有默认租户
     *
     * @param int $userId 用户ID
     * @return bool
     */
    public static function hasDefaultTenant(int $userId): bool
    {
        return self::where('user_id', $userId)
            ->where('is_default', 1)
            ->exists();
    }

    // ==================== 修改方法 ====================

    /**
     * 设置用户的默认租户
     *
     * @param int $userId 用户ID
     * @param int $tenantId 租户ID
     * @return bool
     */
    public static function setDefaultTenant(int $userId, int $tenantId): bool
    {
        // 检查关联是否存在
        if (!self::isUserInTenant($userId, $tenantId)) {
            return false;
        }

        // 取消其他默认
        self::withoutTenancy()
            ->where('user_id', $userId)
            ->where('is_default', 1)
            ->update(['is_default' => 0]);

        // 设置新的默认
        self::withoutTenancy()
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->update(['is_default' => 1]);

        return true;
    }

    /**
     * 添加用户到租户
     *
     * @param int $userId 用户ID
     * @param int $tenantId 租户ID
     * @param bool $isDefault 是否设为默认
     * @param int $createdBy 创建人ID
     * @return self|null
     */
    public static function addUserToTenant(
        int $userId,
        int $tenantId,
        bool $isDefault = false,
        int $createdBy = 0
    ): ?self {
        // 检查是否已存在
        if (self::isUserInTenant($userId, $tenantId)) {
            return null;
        }

        // 如果是第一个租户，自动设为默认
        if (!self::where('user_id', $userId)->exists()) {
            $isDefault = true;
        }

        return self::create([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'is_default' => $isDefault ? 1 : 0,
            'join_time' => date('Y-m-d H:i:s',time()),
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ]);
    }

    /**
     * 从租户中移除用户
     *
     * @param int $userId 用户ID
     * @param int $tenantId 租户ID
     * @return bool
     */
    public static function removeUserFromTenant(int $userId, int $tenantId): bool
    {
        /** @var self|null $record */
        $record = self::withoutTenancy()->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$record) {
            return false;
        }

        $wasDefault = $record->is_default;
        $record->delete();

        // 如果删除的是默认租户，需要重新设置默认
        if ($wasDefault) {
            $firstTenant = self::where('user_id', $userId)->first();
            if ($firstTenant) {
                $firstTenant->update(['is_default' => 1]);
            }
        }

        return true;
    }

    /**
     * 切换用户的默认租户
     *
     * @param int $userId 用户ID
     * @param int $newTenantId 新的默认租户ID
     * @return bool
     */
    public static function switchDefaultTenant(int $userId, int $newTenantId): bool
    {
        return self::setDefaultTenant($userId, $newTenantId);
    }

    /**
     * 获取租户下的用户数量
     *
     * @param int $tenantId 租户ID
     * @return int
     */
    public static function getUserCount(int $tenantId): int
    {
        return self::where('tenant_id', $tenantId)->count();
    }

    /**
     * 批量添加用户到租户
     *
     * @param int $tenantId 租户ID
     * @param array<array-key, mixed> $userIds 用户ID数组
     * @param int $createdBy 创建人ID
     * @return int 成功添加的数量
     */
    public static function batchAddUsers(int $tenantId, array $userIds, int $createdBy = 0): int
    {
        $count = 0;
        $now = date('Y-m-d H:i:s',time());

        foreach ($userIds as $userId) {
            // 使用 withoutTenancy() 检查用户是否已在租户中
            if (!self::withoutTenancy()->where('user_id', $userId)->where('tenant_id', $tenantId)->exists()) {
                // 检查用户是否有其他租户（用于判断是否设为默认）
                $isDefault = !self::withoutTenancy()->where('user_id', $userId)->exists() ? 1 : 0;

                self::create([
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                    'is_default' => $isDefault,
                    'join_time' => $now,
                    'created_by' => $createdBy,
                    'updated_by' => $createdBy,
                ]);

                $count++;
            }
        }

        return $count;
    }

    /**
     * 批量从租户中移除用户
     *
     * @param int $tenantId 租户ID
     * @param array<array-key, mixed> $userIds 用户ID数组
     * @return int 成功移除的数量
     */
    public static function batchRemoveUsers(int $tenantId, array $userIds): int
    {
        $count = 0;

        foreach ($userIds as $userId) {
            if (self::removeUserFromTenant($userId, $tenantId)) {
                $count++;
            }
        }

        return $count;
    }
}