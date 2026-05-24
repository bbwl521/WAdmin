/**
 * MineAdmin plugin API
 */
import type { PageList, ResponseStruct } from '#/global'

export interface PluginVo {
  id?: number
  code?: string
  name?: string
  version?: string
  source?: string
  status?: number
  config?: Record<string, any>
  meta?: Record<string, any>
  created_at?: string
  updated_at?: string
}

export interface MarketplacePluginVo {
  code: string
  name: string
  version: string
  description: string
  author: string
  downloads: number
  category: string
  icon: string
}

// 已安装插件
export function list(): Promise<ResponseStruct<PluginVo[]>> {
  return useHttp().get('/admin/plugin')
}

export function detail(code: string): Promise<ResponseStruct<PluginVo>> {
  return useHttp().get(`/admin/plugin/${code}`)
}

export function uninstall(code: string, keepData = false): Promise<ResponseStruct<null>> {
  return useHttp().delete(`/admin/plugin/${code}`, { data: { keep_data: keepData } })
}

export function enable(code: string): Promise<ResponseStruct<null>> {
  return useHttp().put(`/admin/plugin/${code}/enable`)
}

export function disable(code: string): Promise<ResponseStruct<null>> {
  return useHttp().put(`/admin/plugin/${code}/disable`)
}

// 插件市场
export function marketplace(params?: { search?: string, page?: number, page_size?: number }): Promise<ResponseStruct<{ items: MarketplacePluginVo[], total: number }>> {
  return useHttp().get('/admin/plugin/marketplace', { params })
}

export function marketplaceDetail(code: string): Promise<ResponseStruct<MarketplacePluginVo & { installed: boolean }>> {
  return useHttp().get(`/admin/plugin/marketplace/${code}`)
}

export function marketplaceInstall(code: string, version?: string): Promise<ResponseStruct<{ installed: boolean, plugin: PluginVo, need_refresh_menu?: boolean }>> {
  return useHttp().post('/admin/plugin/marketplace/install', { code, version })
}

// 发布到市场
export function publish(code: string, apiToken: string): Promise<ResponseStruct<{ published: boolean, message: string, url?: string }>> {
  return useHttp().post(`/admin/plugin/${code}/publish`, { api_token: apiToken })
}

export function validateForPublish(code: string): Promise<ResponseStruct<{ valid: boolean, errors: string[] }>> {
  return useHttp().get(`/admin/plugin/${code}/publish/validate`)
}
