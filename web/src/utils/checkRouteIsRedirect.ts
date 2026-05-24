/**
 * WAdmin is committed to providing solutions for quickly building web applications
 * Please view the LICENSE file that was distributed with this source code,
 * For the full copyright and license information.
 * Thank you very much for using WAdmin.
 *
 * @Author X.Mo<admin@wadmin.local>
 * @Link   https://github.com/bbwl521/WAdmin
 */
import type { MineRoute } from '#/global'

export default function checkRouteIsRedirect(route: MineRoute.routeRecord, type: 'redirect' | 'component' = 'redirect'): boolean {
  if (type === 'redirect' && route.redirect && route?.meta?.type === 'M') {
    return true
  }

  return !!(route.component && route.path)
}
