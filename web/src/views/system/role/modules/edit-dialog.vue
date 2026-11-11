<template>
  <el-dialog
    v-model="visible"
    :title="dialogType === 'add' ? '新增角色' : '编辑角色'"
    width="600px"
    align-center
    @close="handleClose"
  >
    <el-form ref="formRef" :model="formData" :rules="rules" label-width="120px">
      <el-form-item label="角色名称" prop="name">
        <el-input v-model="formData.name" placeholder="请输入角色名称" />
      </el-form-item>
      <el-form-item label="角色标识" prop="code">
        <el-input v-model="formData.code" placeholder="请输入角色编码" />
      </el-form-item>
      <el-form-item label="角色级别" prop="level">
        <el-input-number v-model="formData.level" placeholder="角色级别" :max="99" :min="1" />
      </el-form-item>
      <div class="text-xs text-gray-400 pl-32 pb-4"
        >控制角色的权限层级, 不能操作职级高于自己的角色</div
      >


      <el-form-item label="数据权限" prop="data_scope">
        <el-select v-model="formData.data_scope" placeholder="请选择数据权限范围" style="width: 100%">
          <el-option
            v-for="item in dataScopeOptions"
            :key="item.value"
            :label="item.label"
            :value="item.value"
          />
        </el-select>
      </el-form-item>

      <el-form-item v-if="formData.data_scope === 6" label="权限部门" prop="dept_ids">
        <el-tree-select
          v-model="formData.dept_ids"
          :data="deptTreeData"
          multiple
          :check-strictly="true"
          :render-after-expand="false"
          placeholder="请选择部门"
          style="width: 100%"
          node-key="id"
          :props="deptTreeProps"
        />
      </el-form-item>


      <el-form-item label="描述" prop="remark">
        <el-input
          v-model="formData.remark"
          type="textarea"
          :rows="3"
          placeholder="请输入角色描述"
        />
      </el-form-item>
      <el-form-item label="排序" prop="sort">
        <el-input-number v-model="formData.sort" placeholder="请输入排序" />
      </el-form-item>
      <el-form-item label="启用" prop="status">
        <sa-radio v-model="formData.status" dict="data_status" />
      </el-form-item>





    </el-form>
    <template #footer>
      <el-button @click="handleClose">取消</el-button>
      <el-button type="primary" :loading="submitLoading" @click="handleSubmit">提交</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
  import api from '@/api/system/role'
  import deptApi from '@/api/system/dept'
  import { ElMessage } from 'element-plus'
  import type { FormInstance, FormRules } from 'element-plus'

  interface Props {
    modelValue: boolean
    dialogType: string
    data?: Record<string, any>
  }

  interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
  }

  const props = withDefaults(defineProps<Props>(), {
    modelValue: false,
    dialogType: 'add',
    data: undefined
  })

  const emit = defineEmits<Emits>()

  const formRef = ref<FormInstance>()
  const submitLoading = ref(false)

  /**
   * 弹窗显示状态双向绑定
   */
  const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
  })

  /**
   * 数据权限选项
   */
  const dataScopeOptions = [
    { label: '全部数据', value: 1 },
    { label: '本部门数据', value: 2 },
    { label: '本部门及子部门数据', value: 3 },
    { label: '仅本人数据', value: 4 },
    { label: '本部门及子部门 + 本人数据', value: 5 },
    { label: '自定义部门', value: 6 }
  ]

  /**
   * 部门树数据
   */
  const deptTreeData = ref<any[]>([])

  /**
   * 部门树字段映射
   *
   * ElTreeSelect 运行期支持 props.value（用于下拉选项取值），
   * 但 Element Plus 的 TreeOptionProps 类型未声明该字段，
   * 故独立声明为变量，避免模板内联对象触发多余属性校验
   */
  const deptTreeProps = {
    label: 'label',
    value: 'id',
    children: 'children'
  }

  /**
   * 表单验证规则
   */
  const rules = reactive<FormRules>({
    name: [{ required: true, message: '请输入角色名称', trigger: 'blur' }],
    code: [{ required: true, message: '请输入角色编码', trigger: 'blur' }],
    level: [{ required: true, message: '请输入角色级别', trigger: 'blur' }]
  })

  /**
   * 初始数据
   */
  const initialFormData = {
    id: null,
    level: 1,
    name: '',
    code: '',
    remark: '',
    sort: 100,
    status: 1,
    data_scope: 1,
    dept_ids: [] as number[]
  }

  /**
   * 表单数据
   */
  const formData = reactive({ ...initialFormData })

  /**
   * 监听弹窗打开，初始化表单数据
   */
  watch(
    () => props.modelValue,
    (newVal) => {
      if (newVal) {
        initPage()
      }
    }
  )

  /**
   * 获取部门树数据
   */
  const loadDeptTree = async () => {
    try {
      deptTreeData.value = await deptApi.tree()
    } catch (e) {
      console.error('获取部门树失败', e)
    }
  }

  /**
   * 初始化页面数据
   */
  const initPage = async () => {
    // 先重置为初始值
    Object.assign(formData, initialFormData)
    formData.dept_ids = []
    // 加载部门树
    await loadDeptTree()
    // 如果有数据，则填充数据
    if (props.data) {
      await nextTick()
      initForm()
    }
  }

  /**
   * 初始化表单数据
   */
  const initForm = async () => {
    if (props.data) {
      // 编辑模式：通过详情接口获取完整数据（包含 menu_ids、dept_ids 等）
      if (props.dialogType === 'edit' && props.data?.id) {
        try {
          const detail = await api.read(props.data.id)
          for (const key in formData) {
            if (detail[key] != null && detail[key] != undefined) {
              ;(formData as any)[key] = detail[key]
            }
          }
        } catch (e) {
          console.error('获取角色详情失败', e)
          // 降级：直接使用行数据填充
          for (const key in formData) {
            if (props.data[key] != null && props.data[key] != undefined) {
              ;(formData as any)[key] = props.data[key]
            }
          }
        }
      } else {
        // 新增模式：使用行数据（通常为空）
        for (const key in formData) {
          if (props.data[key] != null && props.data[key] != undefined) {
            ;(formData as any)[key] = props.data[key]
          }
        }
      }
    }
  }

  /**
   * 关闭弹窗并重置表单
   */
  const handleClose = () => {
    visible.value = false
    formRef.value?.resetFields()
  }

  /**
   * 提交表单
   */
  const handleSubmit = async () => {
    if (!formRef.value) return
    try {
      await formRef.value.validate()
      submitLoading.value = true

      const submitData = {
        ...formData,
        // 非"自定义部门"时清空 dept_ids
        dept_ids: formData.data_scope === 6 ? formData.dept_ids : []
      }

      if (props.dialogType === 'add') {
        await api.save(submitData)
        ElMessage.success('新增成功')
      } else {
        await api.update(submitData)
        ElMessage.success('修改成功')
      }
      emit('success')
      handleClose()
    } catch (error) {
      console.log('表单验证失败:', error)
    } finally {
      submitLoading.value = false
    }
  }
</script>
