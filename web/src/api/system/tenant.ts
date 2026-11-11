import request from '@/utils/http'

/**
 * 租户API
 */
export default {
  /**
   * 获取租户列表
   */
  list(params: Record<string, any>) {
    return request.get<Api.Common.ApiPage>({
      url: '/api/system/tenant/list',
      params
    })
  },

  /**
   * 获取租户详情
   */
  read(id: number | string) {
    return request.get<Api.Common.ApiData>({
      url: '/api/system/tenant/detail/' + id
    })
  },

  /**
   * 新增租户
   */
  save(params: Record<string, any>) {
    return request.post<any>({
      url: '/api/system/tenant/create',
      data: params
    })
  },

  /**
   * 更新租户
   */
  update(params: Record<string, any>) {
    return request.put<any>({
      url: '/api/system/tenant/update/' + params.id,
      data: params
    })
  },

  /**
   * 删除租户
   */
  delete(id: number | string) {
    return request.del<any>({
      url: '/api/system/tenant/delete/' + id
    })
  },

  /**
   * 更新租户状态
   */
  updateStatus(id: number | string, status: number) {
    return request.put<any>({
      url: '/api/system/tenant/status/' + id,
      data: { status }
    })
  },

  /**
   * 获取租户关联用户
   */
  users(tenantId: number | string, params: Record<string, any>) {
    return request.get<Api.Common.ApiListPage>({
      url: '/api/system/tenant/users/' + tenantId,
      params
    })
  },

  /**
   * 获取可添加用户
   */
  availableUsers(tenantId: number | string, params: Record<string, any>) {
    return request.get<Api.Common.ApiListPage>({
      url: '/api/system/tenant/available-users/' + tenantId,
      params
    })
  },

  /**
   * 批量添加用户到租户
   */
  addUsers(tenantId: number | string, userIds: number[]) {
    return request.post<any>({
      url: '/api/system/tenant/add-users/' + tenantId,
      data: { user_ids: userIds }
    })
  },

  /**
   * 移除租户用户
   */
  removeUser(tenantId: number | string, userId: number | string) {
    return request.del<any>({
      url: '/api/system/tenant/remove-user/' + tenantId + '/' + userId
    })
  },

  /**
   * 设置租户管理员
   */
  setTenantAdmin(tenantId: number | string, userId: number | string, isSuper: number) {
    return request.put<any>({
      url: '/api/system/tenant/set-admin/' + tenantId + '/' + userId,
      data: { is_super: isSuper }
    })
  },

  /**
   * 设为默认租户
   */
  setDefaultTenant(tenantId: number | string, userId: number | string, isDefault: number) {
    return request.put<any>({
      url: '/api/system/tenant/set-default/' + tenantId + '/' + userId,
      data: { is_default: isDefault }
    })
  }
}
