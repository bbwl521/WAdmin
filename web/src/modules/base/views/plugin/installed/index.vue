<!--
 - MineAdmin Installed Plugins Management
-->
<script setup lang="ts">
import { list, enable, disable, uninstall, publish } from '~/base/api/plugin'
import { ref, onMounted } from 'vue'
import type { PluginVo } from '~/base/api/plugin'
import { useMessage } from '@/hooks/useMessage.ts'
import hasAuth from '@/utils/permission/hasAuth.ts'
import useUserStore from '@/store/modules/useUserStore.ts'
import useRouteStore from '@/store/modules/useRouteStore.ts'
import { useRouter } from 'vue-router'

defineOptions({ name: 'plugin:installed' })

const msg = useMessage()
const router = useRouter()
const userStore = useUserStore()
const routeStore = useRouteStore()
const loading = ref(false)
const pluginList = ref<PluginVo[]>([])

const publishDialogVisible = ref(false)
const publishLoading = ref(false)
const publishPlugin = ref<PluginVo | null>(null)
const apiToken = ref('')

async function refreshSideMenu() {
  try {
    await userStore.refreshMenu()
    await routeStore.initRoutes(router, userStore.getMenu())
  } catch (e: any) {
    console.error('[Plugin] 菜单刷新失败:', e?.message || e)
  }
}

async function fetchList() {
  loading.value = true
  try {
    const res = await list()
    pluginList.value = (res.data ?? []) as PluginVo[]
  }
  catch {
    msg.error('获取插件列表失败')
  }
  finally {
    loading.value = false
  }
}

async function handleEnable(row: PluginVo) {
  try {
    await enable(row.code!)
    msg.success(`「${row.name}」已启用`)
  }
  catch { msg.error('启用失败'); return }
  refreshSideMenu()
  fetchList()
}

async function handleDisable(row: PluginVo) {
  try {
    await disable(row.code!)
    msg.success(`「${row.name}」已禁用`)
  }
  catch { msg.error('禁用失败'); return }
  refreshSideMenu()
  fetchList()
}

async function handleUninstall(row: PluginVo) {
  try {
    await uninstall(row.code!)
    msg.success(`「${row.name}」已卸载`)
  }
  catch { msg.error('卸载失败'); return }
  refreshSideMenu()
  fetchList()
}

function openPublishDialog(row: PluginVo) {
  publishPlugin.value = row
  apiToken.value = ''
  publishDialogVisible.value = true
}

async function handlePublish() {
  if (!apiToken.value.trim()) { msg.warning('请输入 API Token'); return }
  if (!publishPlugin.value) return
  publishLoading.value = true
  try {
    const res = await publish(publishPlugin.value.code!, apiToken.value.trim())
    if (res.data?.published) {
      msg.success(`「${publishPlugin.value.name}」已发布到市场`)
      publishDialogVisible.value = false
    } else {
      msg.error(res.data?.message || '发布失败')
    }
  }
  catch { msg.error('发布失败') }
  finally { publishLoading.value = false }
}

onMounted(() => fetchList())
</script>

<template>
  <div class="plugin-installed">
    <div class="mine-card">
      <div class="card-header">
        <h3 class="text-lg font-semibold">已安装插件</h3>
        <span class="text-xs text-gray-400">共 {{ pluginList.length }} 个</span>
      </div>

      <el-table v-loading="loading" :data="pluginList" border stripe empty-text="暂无已安装插件">
        <el-table-column prop="name" label="名称" width="180" />
        <el-table-column prop="code" label="标识" width="160">
          <template #default="{ row }">
            <el-tag size="small">{{ row.code }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="version" label="版本" width="100" />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'warning'" size="small">
              {{ row.status === 1 ? '已启用' : '已禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="安装时间" width="180" />
        <el-table-column label="操作" min-width="260">
          <template #default="{ row }">
            <div class="flex gap-2">
              <template v-if="row.status === 1">
                <el-button size="small" type="warning" @click="handleDisable(row)">禁用</el-button>
              </template>
              <template v-else>
                <el-button size="small" type="success" @click="handleEnable(row)">启用</el-button>
              </template>
              <el-button
                v-if="hasAuth(['plugin:installed:publish'])"
                size="small"
                type="primary"
                plain
                @click="openPublishDialog(row)"
              >
                发布到市场
              </el-button>
              <el-popconfirm
                title="确定要卸载该插件吗？"
                confirm-button-text="确定"
                cancel-button-text="取消"
                @confirm="handleUninstall(row)"
              >
                <template #reference>
                  <el-button size="small" type="danger">卸载</el-button>
                </template>
              </el-popconfirm>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <el-dialog v-model="publishDialogVisible" title="发布插件到市场" width="480px" :close-on-click-modal="false">
      <div v-if="publishPlugin" class="space-y-4">
        <div class="flex items-center gap-2 text-sm">
          <span class="text-gray-500">插件：</span>
          <span class="font-semibold">{{ publishPlugin.name }} ({{ publishPlugin.code }})</span>
          <el-tag size="small" type="info">v{{ publishPlugin.version }}</el-tag>
        </div>
        <el-alert type="info" :closable="false" show-icon
          title="发布到远程插件市场" description="需要配置 marketplace_url 和 API Token" />
        <el-input v-model="apiToken" type="password" placeholder="开发者 API Token" show-password clearable>
          <template #prefix><i class="ri-key-2-line" /></template>
        </el-input>
      </div>
      <template #footer>
        <el-button @click="publishDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="publishLoading" @click="handlePublish">发布</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped lang="scss">
.plugin-installed { padding: 16px; }
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 0;
}
</style>
