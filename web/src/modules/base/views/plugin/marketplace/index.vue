<!--
 - MineAdmin Plugin Marketplace
-->
<script setup lang="ts">
import { marketplace, marketplaceInstall, list as installedList } from '~/base/api/plugin'
import { ref, onMounted } from 'vue'
import useHttp from '@/hooks/auto-imports/useHttp.ts'
import type { MarketplacePluginVo } from '~/base/api/plugin'
import { useMessage } from '@/hooks/useMessage.ts'
import hasAuth from '@/utils/permission/hasAuth.ts'
import useUserStore from '@/store/modules/useUserStore.ts'
import useRouteStore from '@/store/modules/useRouteStore.ts'
import { useRouter } from 'vue-router'

defineOptions({ name: 'plugin:marketplace' })

const msg = useMessage()
const router = useRouter()
const userStore = useUserStore()
const routeStore = useRouteStore()

const loading = ref(false)
const searchKeyword = ref('')
const pluginList = ref<MarketplacePluginVo[]>([])
const installedCodes = ref<string[]>([])

const uploadDialogVisible = ref(false)
const uploadLoading = ref(false)
const uploadFile = ref<any>(null)

async function refreshSideMenu() {
  try {
    await userStore.refreshMenu()
    await routeStore.initRoutes(router, userStore.getMenu())
  } catch (e: any) {
    console.error('[Plugin] 菜单刷新失败:', e?.message || e)
  }
}

async function fetchMarketplace() {
  loading.value = true
  try {
    const res = await marketplace({ search: searchKeyword.value })
    pluginList.value = res.data?.items ?? []
  }
  catch {
    msg.error('获取插件市场失败')
  }
  finally {
    loading.value = false
  }
}

async function fetchInstalled() {
  try {
    const res = await installedList()
    installedCodes.value = (res.data ?? []).map((p: any) => p.code)
  }
  catch { /* ignore */ }
}

async function handleInstall(plugin: MarketplacePluginVo) {
  if (installedCodes.value.includes(plugin.code)) {
    msg.warning('该插件已安装')
    return
  }
  loading.value = true
  try {
    await marketplaceInstall(plugin.code)
    msg.success(`「${plugin.name}」安装成功`)
    installedCodes.value.push(plugin.code)
  }
  catch {
    msg.error('安装失败')
  }
  finally {
    loading.value = false
  }
  refreshSideMenu()
}

function handleSearch() {
  fetchMarketplace()
}

function handleFileChange(file: any) {
  uploadFile.value = file
}

async function handleUpload() {
  if (! uploadFile.value) {
    msg.warning('请选择插件包')
    return
  }
  uploadLoading.value = true
  try {
    const formData = new FormData()
    formData.append('file', uploadFile.value.raw)
    const res = await useHttp().post('/admin/plugin/marketplace/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    if (res.data?.uploaded) {
      msg.success('插件上传成功')
      uploadDialogVisible.value = false
      uploadFile.value = null
      fetchMarketplace()
    }
    else {
      msg.error(res.data?.message || '上传失败')
    }
  }
  catch {
    msg.error('上传失败')
  }
  finally {
    uploadLoading.value = false
  }
}

onMounted(() => {
  fetchMarketplace()
  fetchInstalled()
})
</script>

<template>
  <div class="plugin-marketplace">
    <div class="mb-4 flex items-center gap-3">
      <div class="flex-1 max-w-md">
        <el-input
          v-model="searchKeyword"
          placeholder="搜索插件..."
          clearable
          @keyup.enter="handleSearch"
          @clear="handleSearch"
        >
          <template #prefix>
            <i class="ri-search-line" />
          </template>
        </el-input>
      </div>
      <el-button type="primary" @click="handleSearch">搜索</el-button>
      <el-button v-if="hasAuth(['plugin:marketplace:upload'])" type="success" @click="uploadDialogVisible = true">
        <i class="ri-upload-line mr-1" />上传插件
      </el-button>
    </div>

    <el-row v-loading="loading" :gutter="16">
      <el-col v-for="plugin in pluginList" :key="plugin.code" :xs="24" :sm="12" :md="8" :lg="6" class="mb-4">
        <el-card shadow="hover" class="plugin-card h-full">
          <template #header>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <i :class="plugin.icon || 'ri-plug-line'" class="text-lg text-primary" />
                <span class="font-semibold text-sm">{{ plugin.name }}</span>
              </div>
              <el-tag size="small" :type="installedCodes.includes(plugin.code) ? 'success' : 'info'">
                {{ installedCodes.includes(plugin.code) ? '已安装' : `v${plugin.version}` }}
              </el-tag>
            </div>
          </template>
          <p class="text-xs text-gray-500 mb-3 line-clamp-2">{{ plugin.description }}</p>
          <div class="flex items-center justify-between mt-auto">
            <div class="flex items-center gap-1 text-xs text-gray-400">
              <i class="ri-user-line" /><span>{{ plugin.author }}</span>
              <span class="mx-1">·</span>
              <i class="ri-download-line" /><span>{{ plugin.downloads }}</span>
            </div>
            <el-button
              size="small"
              :type="installedCodes.includes(plugin.code) ? 'default' : 'primary'"
              :disabled="installedCodes.includes(plugin.code)"
              :loading="loading"
              @click="handleInstall(plugin)"
            >
              {{ installedCodes.includes(plugin.code) ? '已安装' : '安装' }}
            </el-button>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-empty v-if="!loading && pluginList.length === 0" description="暂无插件" />

    <el-dialog
      v-model="uploadDialogVisible"
      title="上传插件到市场"
      width="500px"
      :close-on-click-modal="false"
    >
      <el-upload
        ref="uploadRef"
        drag
        :auto-upload="false"
        :limit="1"
        accept=".zip"
        :on-change="handleFileChange"
      >
        <div class="py-8 text-center">
          <i class="ri-upload-cloud-2-line text-4xl text-gray-400" />
          <p class="mt-2 text-sm text-gray-500">拖拽或点击上传插件 zip 包</p>
          <p class="text-xs text-gray-400 mt-1">仅支持 .zip 格式</p>
        </div>
      </el-upload>

      <template #footer>
        <el-button @click="uploadDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="uploadLoading" @click="handleUpload">
          上传
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped lang="scss">
.plugin-marketplace { padding: 16px; }
.plugin-card {
  :deep(.el-card__body) {
    display: flex;
    flex-direction: column;
    min-height: 140px;
  }
  .line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
}
</style>
