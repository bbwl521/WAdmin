/**
 * WAdmin is committed to providing solutions for quickly building web applications
 * Please view the LICENSE file that was distributed with this source code,
 * For the full copyright and license information.
 * Thank you very much for using WAdmin.
 *
 * @Author X.Mo<admin@wadmin.local>
 * @Link   https://github.com/bbwl521/WAdmin
 */
import type { PageList, ResponseStruct } from '#/global'

export interface PositionVo {
  id?: number
  dept_id?: number
  dept_name?: string
  name?: string
  [key: string]: any
}

export interface PositionSearchVo {
  name?: string
  [key: string]: any
}

export function page(data: PositionSearchVo | null = null): Promise<ResponseStruct<PageList<PositionVo>>> {
  return useHttp().get('/admin/position/list', { params: data })
}

export function create(data: PositionVo): Promise<ResponseStruct<null>> {
  return useHttp().post('/admin/position', data)
}

export function save(id: number, data: PositionVo): Promise<ResponseStruct<null>> {
  return useHttp().put(`/admin/position/${id}`, data)
}

export function setDataScope(id: number, data: PositionVo): Promise<ResponseStruct<null>> {
  return useHttp().put(`/admin/position/${id}/data_permission`, data)
}

export function deleteByIds(ids: number[]): Promise<ResponseStruct<null>> {
  return useHttp().delete('/admin/position', { data: ids })
}
