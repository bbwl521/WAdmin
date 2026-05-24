/**
 * WAdmin is committed to providing solutions for quickly building web applications
 * Please view the LICENSE file that was distributed with this source code,
 * For the full copyright and license information.
 * Thank you very much for using WAdmin.
 *
 * @Author X.Mo<admin@wadmin.local>
 * @Link   https://github.com/bbwl521/WAdmin
 */
export function recursionGetKey(arr: any[], key: string): any[] {
  const keys: any[] = []
  arr.map((item: any) => {
    if (item.children && item.children.length > 0) {
      keys.push(...recursionGetKey(item.children, key))
    }
    else {
      keys.push(item[key])
    }
  })
  return keys
}
