/**
 * WAdmin is committed to providing solutions for quickly building web applications
 * Please view the LICENSE file that was distributed with this source code,
 * For the full copyright and license information.
 * Thank you very much for using WAdmin.
 *
 * @Author X.Mo<admin@wadmin.local>
 * @Link   https://github.com/bbwl521/WAdmin
 */
export default function getOnlyWorkAreaHeight(): number {
  return document.body.offsetHeight
    - ((document.querySelector('.mine-bars') as HTMLElement)?.offsetHeight ?? 0)
    - ((document.querySelector('.mine-header-main') as HTMLElement)?.offsetHeight ?? 0)
    - ((document.querySelector('.mine-footer') as HTMLElement)?.offsetHeight ?? 0)
    - 48
}
