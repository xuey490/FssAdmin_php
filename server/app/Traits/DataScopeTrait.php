<?php

namespace App\Traits;

use App\Models\SysDept as Dept;
use App\Models\SysRole;
use App\Models\SysRoleDept as RoleDept;
use App\Models\SysUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @method static string getTableName()
 */
trait DataScopeTrait
{
    /**
     * 模型全局挂载数据权限作用域
     */
    protected static function booted(): void
    {
        static::addGlobalScope('data_scope', function (Builder $query) {
            static::buildDataScopeCondition($query);
        });
    }

    /**
     * 构建数据权限查询条件
     *
     * @param Builder<static> $query
     */
    protected static function buildDataScopeCondition(Builder $query): void
    {
        $request = \Framework\Tenant\TenantContext::getCurrentRequest();
        $loginUser = $request?->attributes->get('current_user');

        $tenantId = (int) \Framework\Tenant\TenantContext::getTenantId();

        // 未登录直接无数据
        if (!$loginUser instanceof SysUser) {
            $query->whereRaw('1 = 0');
            return;
        }

        // 1、全局强制租户隔离（多租户核心）
        $query->where(static::getTableName() . '.tenant_id', $tenantId);

        // 2、系统超级管理员：跳过业务数据权限，只保留租户隔离
        // if ($loginUser->isSuperAdmin()) {
        //     return;
        // }

        // 3、获取当前角色的数据权限范围（优先已加载的 roles 集合，其次查询关联）
        $role = self::resolveLoginRole($loginUser);
        $dataScope = $role instanceof SysRole
            ? (int) $role->data_scope
            : SysRole::DATA_SCOPE_SELF;

        // 4、当前用户基础信息
        $userId = (int) $loginUser->id;
        $deptId = (int) ($loginUser->getCurrentDeptId() ?? 0);

        // 5、按6种数据范围分支判断
        match ($dataScope) {
            // 1 全部数据：仅租户隔离，无额外限制
            SysRole::DATA_SCOPE_ALL => null,

            // 2 本部门数据
            SysRole::DATA_SCOPE_DEPT => $deptId > 0 ? $query->where('dept_id', $deptId) : $query->where('created_by', $userId),

            // 3 本部门及子部门数据
            SysRole::DATA_SCOPE_DEPT_AND_CHILD => $deptId > 0 ? static::withDeptAndChild($query, $deptId) : $query->where('created_by', $userId),

            // 4 仅本人数据
            SysRole::DATA_SCOPE_SELF => $query->where('created_by', $userId),

            // 5 本部门及子部门 + 本人数据
            SysRole::DATA_SCOPE_DEPT_AND_SELF => $deptId > 0 ? static::withDeptChildAndSelf($query, $deptId, $userId) : $query->where('created_by', $userId),

            // 6 自定义部门数据
            SysRole::DATA_SCOPE_CUSTOM => static::withCustomDept($query, $role instanceof SysRole ? (int) $role->id : null, $userId),

            // 默认降级：仅本人
            default => $query->where('created_by', $userId)
        };
    }

    private static function resolveLoginRole(SysUser $loginUser): ?SysRole
    {
        if ($loginUser->relationLoaded('roles')) {
            $roles = $loginUser->getRelation('roles');
            if ($roles instanceof Collection) {
                $role = $roles->first();
                if ($role instanceof SysRole) {
                    return $role;
                }
            }
        }

        $role = $loginUser->roles()->first();

        return $role instanceof SysRole ? $role : null;
    }

    /**
     * 本部门 + 所有子部门
     *
     * @param Builder<static> $query
     */
    protected static function withDeptAndChild(Builder $query, int $deptId): void
    {
        $deptIds = Dept::getAllChildIds($deptId);
        $query->whereIn('dept_id', $deptIds);
    }

    /**
     * 本部门+子部门 + 本人创建数据
     *
     * @param Builder<static> $query
     */
    protected static function withDeptChildAndSelf(Builder $query, int $deptId, int $userId): void
    {
        $deptIds = Dept::getAllChildIds($deptId);
        $query->where(function (Builder $q) use ($deptIds, $userId) {
            $q->whereIn('dept_id', $deptIds)
              ->orWhere('created_by', $userId);
        });
    }

    /**
     * 角色绑定的自定义部门
     *
     * @param Builder<static> $query
     */
    protected static function withCustomDept(Builder $query, ?int $roleId, int $userId): void
    {
        if (empty($roleId)) {
            $query->where('created_by', $userId);
            return;
        }

        $deptIds = RoleDept::where('role_id', $roleId)
            ->pluck('dept_id')
            ->toArray();

        if (!empty($deptIds)) {
            $query->whereIn('dept_id', $deptIds);
        } else {
            // 自定义部门无配置，降级为仅本人
            $query->where('created_by', $userId);
        }
    }
}
