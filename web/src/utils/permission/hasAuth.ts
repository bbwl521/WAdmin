/**
 * WAdmin is committed to providing solutions for quickly building web applications
 * Please view the LICENSE file that was distributed with this source code,
 * For the full copyright and license information.
 * Thank you very much for using WAdmin.
 *
 * @Author X.Mo<admin@wadmin.local>
 * @Link   https://github.com/bbwl521/WAdmin
 */
import hasIncludesByArray from '../hasIncludesByArray'

export default function hasAuth(value: string | string[], whetherCheckRouteMeta: boolean = false): boolean {
  if (!value) {
    return false
  }

  const auths = useUserStore().getPermissions()

  if (!auths) {
    return false
  }

  if (auths[0] === '*') {
    return true
  }

  let values: string[]
  if (whetherCheckRouteMeta) {
    const meta = (useRoute()?.meta?.auth ?? []) as string[]
    values = (Array.isArray(value) ? value.push(...meta) : [value, ...meta]) as string[]
  }
  else {
    values = Array.isArray(value) ? value : [value]
  }

  return hasIncludesByArray(auths, values)
}
