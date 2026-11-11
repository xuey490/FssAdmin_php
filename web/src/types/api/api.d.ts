/**
 * API 接口类型定义模块
 *
 * 提供所有后端接口的类型定义
 *
 * ## 主要功能
 *
 * - 通用类型（分页参数、响应结构等）
 * - 认证类型（登录、用户信息等）
 * - 系统管理类型（用户、角色等）
 * - 全局命名空间声明
 *
 * ## 使用场景
 *
 * - API 请求参数类型约束
 * - API 响应数据类型定义
 * - 接口文档类型同步
 *
 * ## 注意事项
 *
 * - 在 .vue 文件使用需要在 eslint.config.mjs 中配置 globals: { Api: 'readonly' }
 * - 使用全局命名空间，无需导入即可使用
 *
 * ## 使用方式
 *
 * ```typescript
 * const params: Api.Auth.LoginParams = { userName: 'admin', password: '123456' }
 * const response: Api.Auth.UserInfo = await fetchUserInfo()
 * ```
 *
 * @module types/api/api
 * @author Art Design Pro Team
 */

declare namespace Api {
  /** 通用类型 */
  namespace Common {
    /** 分页参数 */
    interface PaginationParams {
      /** 当前页码 */
      current: number
      /** 每页条数 */
      size: number
      /** 总条数 */
      total: number
    }

    /** 通用搜索参数 */
    type CommonSearchParams = Pick<PaginationParams, 'current' | 'size'>

    type SafeRecord = Record<string, unknown>

    type ApiData = {
      [key: string]: any
    }

    type ApiPage<T = any> = {
      current_page: number
      data: T[]
      per_page: number
      total: number
    }

    /**
     * FssAdmin 后端统一分页返回结构
     *
     * 服务端 success() 返回的 data 形如 { list, total, page, limit }，
     * 与 Art Design Pro 模板自带的 ApiPage（data / current_page）不同，切勿混用。
     */
    type ApiListPage<T = any> = {
      /** 当前页数据 */
      list: T[]
      /** 总条数 */
      total: number
      /** 当前页码 */
      page: number
      /** 每页条数 */
      limit: number
    }

    /** 分页响应基础结构 */
    interface PaginatedResponse<T = any> {
      records: T[]
      current: number
      size: number
      total: number
    }

    /** 启用状态 */
    type EnableStatus = '1' | '2'
  }

  /** 认证类型 */
  namespace Auth {
    /** 验证码参数 */
    interface CaptchaResponse {
      result: number
      uuid: string
      image: string
    }

    /** 登录参数 */
    interface LoginParams {
      username: string
      password: string
      code: string
      uuid: string
      tenant_id?: number
    }

    /** 租户信息 */
    interface TenantItem {
      id: number
      name: string
      code: string
      is_default: boolean
      status: number
    }

    /** 登录响应 */
    interface LoginResponse {
      token_type: string
      expires_in: number
      access_token: string
      refresh_token: string
    }

    /** 切换租户响应 */
    interface SwitchTenantResponse {
      access_token: string
      refresh_token: string
      expires_in: number
      tenant_id?: number
      tenant_name?: string
      menus?: any[]
      permissions?: string[]
    }

    /** 用户信息 */
    interface UserInfo {
      buttons: string[]
      roles: string[]
      id: number
      username: string
      email: string
      phone: string
      avatar?: string
      realname?: string
      nickname?: string
      dashboard?: string
      gender?: string
      signed?: string
      remark?: string
      login_time?: string
      login_ip?: string
      department?: {
        id: number
        name: string
      }
      posts?: {
        id: number
        name: string
      }[]
    }

    // 基础项类型
    interface DictItem {
      id: number
      label: string
      value: string | number
      color: string
      disabled?: boolean
      [key: string]: any
    }

    // 主对象类型
    interface DictData {
      [key: string]: DictItem[]
    }
  }
}
