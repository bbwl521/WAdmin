/**
 * WAdmin is committed to providing solutions for quickly building web applications
 * Please view the LICENSE file that was distributed with this source code,
 * For the full copyright and license information.
 * Thank you very much for using WAdmin.
 *
 * @Author X.Mo<admin@wadmin.local>
 * @Link   https://github.com/bbwl521/WAdmin
 */
import type { InjectionKey } from 'vue'

export function createInjectionKey<T>(key: string): InjectionKey<T> {
  return key as any
}

export const rootMenuInjectionKey = createInjectionKey<any>('rootMenu')
