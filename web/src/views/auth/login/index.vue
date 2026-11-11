<!-- 登录页面 -->
<template>
  <div class="login-page flex w-full h-screen" :class="`login-layout-${loginLayout}`">
    <div class="login-page-logo">
      <ArtLogo class="icon" size="46" />
      <h1 class="title">{{ systemName }}</h1>
    </div>

    <AuthTopBar
      fixed-to-viewport
      show-login-layout
      v-model:login-layout="loginLayout"
    />

    <LoginLeftView class="login-bg" hide-logo :center-mode="loginLayout === 'center'" />

    <div class="login-main">
      <div class="auth-right-wrap">
        <div class="form">
          <h3 class="title">{{ $t('login.title') }}</h3>
          <p class="sub-title">{{ $t('login.subTitle') }}</p>
          <ElForm
            ref="formRef"
            :model="formData"
            :rules="rules"
            :key="formKey"
            @keyup.enter="handleSubmit"
            class="login-form"
          >
            <ElFormItem prop="username">
              <ElInput
                class="custom-height"
                :placeholder="$t('login.placeholder.username')"
                v-model.trim="formData.username"
              />
            </ElFormItem>
            <ElFormItem prop="tenant_id">
              <ElSelect
                class="w-full custom-height"
                :placeholder="tenantList.length > 0 ? '请选择租户' : '请先输入用户名'"
                v-model="formData.tenant_id"
                :loading="loadingTenants"
                :disabled="tenantList.length === 0"
              >
                <ElOption
                  v-for="tenant in tenantList"
                  :key="tenant.id"
                  :label="tenant.name"
                  :value="tenant.id"
                >
                  <span>{{ tenant.name }}</span>
                  <span v-if="tenant.is_default" style="color: var(--el-color-primary); margin-left: 8px;">(默认)</span>
                </ElOption>
              </ElSelect>
            </ElFormItem>
            <ElFormItem prop="password">
              <ElInput
                class="custom-height"
                :placeholder="$t('login.placeholder.password')"
                v-model.trim="formData.password"
                type="password"
                autocomplete="off"
                show-password
              />
            </ElFormItem>
            <ElFormItem prop="code">
              <ElInput
                class="custom-height"
                :placeholder="$t('login.placeholder.code')"
                v-model.trim="formData.code"
                type="text"
                autocomplete="off"
              >
                <template #append>
                  <img
                    :src="captcha"
                    style="height: 36px; cursor: pointer"
                    @click="refreshCaptcha"
                  />
                </template>
              </ElInput>
             
            </ElFormItem>

            <div class="flex-cb mt-2 text-sm">
              <ElCheckbox v-model="formData.rememberPassword">{{
                $t('login.rememberPwd')
              }}</ElCheckbox>
              <!-- <RouterLink class="text-theme" :to="{ name: 'ForgetPassword' }">{{
                $t('login.forgetPwd')
              }}</RouterLink> -->
            </div>

            <div class="login-submit-wrap">
              <ElButton
                class="w-full custom-height"
                type="primary"
                @click="handleSubmit"
                :loading="loading"
                v-ripple
              >
                {{ $t('login.btnText') }}
              </ElButton>
            </div>

            <!-- <div class="mt-5 text-sm text-gray-600">
              <span>{{ $t('login.noAccount') }}</span>
              <RouterLink class="text-theme" :to="{ name: 'Register' }">{{
                $t('login.register')
              }}</RouterLink>
            </div> -->
          </ElForm>
        </div>
      </div>
    </div>

    <ElDialog
      v-if="loginWindowEnabled"
      v-model="welcomeDialogVisible"
      title="FssAdmin"
      width="750px"
	  height="450px"
      align-center
      :close-on-click-modal="false"
    >
      <ElTabs v-model="activeTab" class="welcome-dialog-tabs">
        <ElTabPane label="PHP版本" name="php">
          <div class="welcome-dialog">
            <p class="welcome-dialog__intro">
              FssAdmin PHP 版本，基于自研 Fssphp 框架，一个后台适配多个前端系统。
            </p>
            <ul class="welcome-dialog__list">
              <li>
                适配 Art Design Pro：
                <a href="https://v3.phpframe.org" target="_blank" rel="noopener noreferrer">https://v3.phpframe.org</a>
                <span class="welcome-dialog__tag">（本演示，已开源）</span>
              </li>
              <li>
                适配 SoyBeanAdmin：
                <a href="https://v4.phpframe.org" target="_blank" rel="noopener noreferrer">https://v4.phpframe.org</a>
                <span class="welcome-dialog__tag">（已开源）</span>
              </li>
              <li>
                适配 Vben5-Ele：
                <a href="https://v5.phpframe.org" target="_blank" rel="noopener noreferrer">https://v5.phpframe.org</a>
              </li>
            </ul>
            <p class="welcome-dialog__footer">目前基础底座已开源，欢迎 Star：</p>
            <div class="welcome-dialog__links">
              <a href="https://gitee.com/fsscms/FssAdmin" target="_blank" rel="noopener noreferrer">Gitee</a>
              <a href="https://github.com/xuey490/FssAdmin" target="_blank" rel="noopener noreferrer">GitHub</a>
            </div>
          </div>
        </ElTabPane>
        <ElTabPane label="NestJs版本" name="nestjs">
          <div class="welcome-dialog">
            <p class="welcome-dialog__intro">
              FssAdmin(NestJs)是一款基于NestJS + TypeScript6 + TypeORM1.0等 技术栈的全功能企业级后端管理系统。
            </p>
            <ul class="welcome-dialog__list">
              <li>
                适配 Art Design Pro：
                <a href="https://nest.phpframe.org" target="_blank" rel="noopener noreferrer">https://nest.phpframe.org</a>
              </li>
			  经测试可以几乎可以无损适配php版本的SoyBeanAdmin，Vben5-Ele的前端
            </ul>
            <p class="welcome-dialog__footer">NestJs 版本已开源，欢迎关注：</p>
            <div class="welcome-dialog__links">
              <a href="https://gitee.com/FssPHP_Team/fss-admin_nest" target="_blank" rel="noopener noreferrer">Gitee</a>
              <a href="https://github.com/xuey490/FssAdmin_NestJs" target="_blank" rel="noopener noreferrer">GitHub</a>
            </div>
          </div>
        </ElTabPane>
        <ElTabPane label="Python版本" name="python">
          <div class="welcome-dialog">
            <p class="welcome-dialog__intro">
              FssAdmin Python 版本，基于 FastAPI + SQLAlchemy 异步架构，高性能 Python 全栈后台解决方案。
            </p>
            <ul class="welcome-dialog__list">
              <li>
                适配 Art Design Pro：
                <a href="https://fast.phpframe.org" target="_blank" rel="noopener noreferrer">https://fast.phpframe.org</a>
              </li>

            </ul>
            <p class="welcome-dialog__footer">Python 版本规划中，欢迎关注：</p>
            <div class="welcome-dialog__links">
				测试地址暂不可用，期待...
            </div>
          </div>
        </ElTabPane>
        <ElTabPane label="Golang版本" name="golang">
          <div class="welcome-dialog">
            <p class="welcome-dialog__intro">
              基于 GoFrame 的企业级管理后台 REST API 服务：JWT 鉴权、多租户、Redis
              缓存、RBAC、定时任务、监控与运维能力等，提供 OpenAPI（/api.json）与 Swagger（/docs）
            </p>
            <ul class="welcome-dialog__list">
              <li>
                适配 SoyAdmin：
                <a href="https://gf.phpframe.org" target="_blank" rel="noopener noreferrer"
                  >https://gf.phpframe.org</a
                >
              </li>
              <li>
                适配 Art Design Pro：
                <a href="https://goframe.phpframe.org" target="_blank" rel="noopener noreferrer"
                  >https://goframe.phpframe.org</a
                >
              </li>
            </ul>
            <p class="welcome-dialog__footer">Goframe 版本开源中，欢迎关注 &amp; Star：</p>
            <div class="welcome-dialog__links">
              <a
                href="https://gitee.com/fsscms/FssAdmin_goframe"
                target="_blank"
                rel="noopener noreferrer"
                >Gitee</a
              >
              <a
                href="https://github.com/xuey490/FssAdmin_goframe"
                target="_blank"
                rel="noopener noreferrer"
                >GitHub</a
              >
            </div>
          </div>
        </ElTabPane>
        <ElTabPane label="Java Spring Boot 4.x" name="java">
          <div class="welcome-dialog">
            <p class="welcome-dialog__intro">
              FssAdmin
              是一个多租户后台管理系统与应用开发底座。系统将认证、组织架构、菜单权限、审计、文件和消息等基础能力集中提供给业务模块，并支持将部分服务独立部署。
            </p>
            <p class="welcome-dialog__footer">技术栈：</p>
            <ul class="welcome-dialog__list">
              <li>Java 21、Maven Wrapper</li>
              <li>Spring Boot 4.1.2 / Spring Framework 7</li>
              <li>Spring Cloud 2025.1.4、Spring Cloud Alibaba</li>
            </ul>
            <p class="welcome-dialog__footer">可以单体启动，也可以微服务启动。</p>
            <ul class="welcome-dialog__list">
              <li>
                在线演示：
                <a href="https://java.phpframe.org/" target="_blank" rel="noopener noreferrer"
                  >https://java.phpframe.org/</a
                >
              </li>
              <li> 商业版联系微信：<span class="welcome-dialog__wechat">xdbyvibm6</span> </li>
            </ul>
          </div>
        </ElTabPane>
      </ElTabs>
      <template #footer>
        <ElButton type="primary" @click="welcomeDialogVisible = false">知道了</ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import AppConfig from '@/config'
  import { useUserStore } from '@/store/modules/user'
  import { useI18n } from 'vue-i18n'
  import { HttpError } from '@/utils/http/error'
  import {
    fetchCaptcha,
    fetchLogin,
    fetchGetUserInfo,
    fetchTenantsByUsername,
    fetchPublicConfigValue
  } from '@/api/auth'
  import { ElNotification, type FormInstance, type FormRules } from 'element-plus'

  defineOptions({ name: 'Login' })

  type LoginLayout = 'center' | 'left' | 'right'
  const loginLayout = ref<LoginLayout>('center')

  const { t, locale } = useI18n()
  const formKey = ref(0)

  // 监听语言切换，重置表单
  watch(locale, () => {
    formKey.value++
  })

  const userStore = useUserStore()
  const router = useRouter()

  const captcha = ref(
    'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
  )

  const systemName = ref(AppConfig.systemInfo.name)
  const formRef = ref<FormInstance>()

  const formData = reactive({
    username: 'admin',
    password: '123456',
    code: '1234',
    uuid: '',
    tenant_id: undefined as number | undefined,
    rememberPassword: true
  })

  const tenantList = ref<Api.Auth.TenantItem[]>([])
  const loadingTenants = ref(false)

  const rules = computed<FormRules>(() => ({
    username: [{ required: true, message: t('login.placeholder.username'), trigger: 'blur' }],
    password: [{ required: true, message: t('login.placeholder.password'), trigger: 'blur' }],
    code: [{ required: true, message: t('login.placeholder.code'), trigger: 'blur' }],
    tenant_id: [{ required: true, message: '请选择租户', trigger: 'change' }]
  }))

  const loading = ref(false)
  const loginWindowEnabled = ref(false)
  const welcomeDialogVisible = ref(false)
  const activeTab = ref('php')
  let welcomeDialogTimer: ReturnType<typeof setTimeout> | undefined

  const loadSystemName = async () => {
    try {
      const res = await fetchPublicConfigValue('site_name')
      const name = res?.value?.trim()
      if (name) {
        systemName.value = name
      }
    } catch (error) {
      console.error('[Login] 加载站点名称失败:', error)
    }
  }

  const loadLoginWindowConfig = async () => {
    try {
      const res = await fetchPublicConfigValue('login_window')
      loginWindowEnabled.value = res?.value === '1'
    } catch (error) {
      console.error('[Login] 加载登录弹窗配置失败:', error)
      loginWindowEnabled.value = false
    }
  }

  onMounted(() => {
    loadSystemName()
    loadLoginWindowConfig()
    refreshCaptcha()
    // 如果有默认用户名，自动加载租户列表
    if (formData.username) {
      loadTenantList()
    }
  })

  // 登录弹窗延迟显示
  watch(loginWindowEnabled, (enabled) => {
    if (enabled) {
      welcomeDialogTimer = setTimeout(() => {
        welcomeDialogVisible.value = true
      }, 2000)
    }
  })

  onUnmounted(() => {
    if (welcomeDialogTimer) {
      clearTimeout(welcomeDialogTimer)
    }
  })

  // 监听用户名变化，加载租户列表
  watch(() => formData.username, (newUsername) => {
    if (newUsername && newUsername.trim()) {
      loadTenantList()
    } else {
      tenantList.value = []
      formData.tenant_id = undefined
    }
  })

  // 加载租户列表
  const loadTenantList = async () => {
    if (!formData.username || !formData.username.trim()) {
      return
    }

    try {
      loadingTenants.value = true
      const list = await fetchTenantsByUsername(formData.username.trim())
      tenantList.value = list || []
      
      // 如果只有一个租户，自动选中
      if (tenantList.value.length === 1) {
        formData.tenant_id = tenantList.value[0].id
      } else if (tenantList.value.length > 0) {
        // 如果有默认租户，自动选中
        const defaultTenant = tenantList.value.find(t => t.is_default)
        if (defaultTenant) {
          formData.tenant_id = defaultTenant.id
        }
      }
    } catch (error) {
      console.error('[Login] 加载租户列表失败:', error)
      tenantList.value = []
    } finally {
      loadingTenants.value = false
    }
  }

  // 登录
  const handleSubmit = async () => {
    if (!formRef.value) return

    try {
      // 表单验证
      const valid = await formRef.value.validate()
      if (!valid) return

      loading.value = true

      // 登录请求
      const { access_token, refresh_token } = await fetchLogin({
        username: formData.username,
        password: formData.password,
        code: formData.code,
        uuid: formData.uuid,
        tenant_id: formData.tenant_id
      })

      // 验证token
      if (!access_token) {
        throw new Error('Login failed - no token received')
      }

      // 存储token和用户信息
      userStore.setToken(access_token, refresh_token)
      const userInfo = await fetchGetUserInfo()
      userStore.setUserInfo(userInfo)
      userStore.setLoginStatus(true)

      // 登录成功处理
      showLoginSuccessNotice()
      router.push('/')
    } catch (error) {
      // 处理 HttpError
      if (error instanceof HttpError) {
        // console.log(error.code)
      } else {
        // 处理非 HttpError
        // ElMessage.error('登录失败，请稍后重试')
        console.error('[Login] Unexpected error:', error)
      }
    } finally {
      refreshCaptcha()
      loading.value = false
    }
  }

  // 获取验证码
  const refreshCaptcha = async () => {
    fetchCaptcha().then((res) => {
      formData.uuid = res.uuid
      captcha.value = res.image
    })
  }

  // 登录成功提示
  const showLoginSuccessNotice = () => {
    setTimeout(() => {
      ElNotification({
        title: t('login.success.title'),
        type: 'success',
        duration: 2500,
        zIndex: 10000,
        //message: `${t('login.success.message')}, ${systemName}!`
        message: `${t('login.success.message')}, ${formData.username}!`
      })
    }, 150)
  }
</script>

<style scoped>
  @import './style.css';
</style>

<style lang="scss" scoped>
  :deep(.el-select__wrapper) {
    height: 40px !important;
  }

  .login-form {
    margin-top: 20px;

    :deep(.el-form-item) {
      margin-bottom: 16px;
    }
  }

  .login-submit-wrap {
    margin-top: 20px;
  }

  .welcome-dialog {
    line-height: 1.7;
    font-size: 14px;
    color: var(--el-text-color-regular);

    &__intro {
      margin: 0 0 16px;
      font-weight: 500;
      color: var(--el-text-color-primary);
    }

    &__list {
      margin: 0 0 16px;
      padding-left: 20px;

      li + li {
        margin-top: 10px;
      }

      a {
        color: var(--el-color-primary);
        text-decoration: none;

        &:hover {
          text-decoration: underline;
        }
      }
    }

    &__tag {
      color: var(--el-text-color-secondary);
      font-size: 13px;
    }

    &__footer {
      margin: 0 0 10px;
      color: var(--el-text-color-primary);
    }

    &__links {
      display: flex;
      gap: 16px;

      a {
        color: var(--el-color-primary);
        font-weight: 500;
        text-decoration: none;

        &:hover {
          text-decoration: underline;
        }
      }
    }

    &__wechat {
      padding: 1px 6px;
      border-radius: 4px;
      background-color: var(--el-fill-color-light);
      color: var(--el-text-color-primary);
      font-weight: 500;
      font-family: Consolas, Monaco, monospace;
      user-select: all;
    }
  }

  // 五个 Tab 时压缩左右内边距，避免标签溢出
  .welcome-dialog-tabs {
    :deep(.el-tabs__item) {
      padding: 0 16px;
    }

    // 内容区限高滚动，避免不同版本内容长短导致弹窗高度跳动
    :deep(.el-tabs__content) {
      max-height: 340px;
      overflow-y: auto;
    }
  }
</style>
