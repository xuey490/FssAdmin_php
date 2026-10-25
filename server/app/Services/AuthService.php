<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SysUser;
use Framework\Tenant\TenantContext;

class AuthService
{
    /**
     * 登录并初始化租户上下文
     */
    public function login(string $username, string $password, ?int $tenantId = null): ?SysUser
    {
        if ($tenantId !== null) {
            TenantContext::setTenantId($tenantId);
        }

        $user = SysUser::where('username', $username)->first();
        if (!$user instanceof SysUser || !$user->verifyPassword($password)) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            TenantContext::ignore();
        }

        return $user;
    }

    /**
     * 退出登录，清空租户上下文
     */
    public function logout(): void
    {
        TenantContext::restore();
        TenantContext::clear();
    }
}
