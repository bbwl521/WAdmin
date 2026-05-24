/**
 * WAdmin is committed to providing solutions for quickly building web applications
 * Please view the LICENSE file that was distributed with this source code,
 * For the full copyright and license information.
 * Thank you very much for using WAdmin.
 *
 * @Author X.Mo<admin@wadmin.local>
 * @Link   https://github.com/bbwl521/WAdmin
 */

import { useI18n } from 'vue-i18n'
import type { ComposerTranslation } from 'vue-i18n'

export function useLocalTrans(key: any | null = null): string | ComposerTranslation | any {
  const { t } = useI18n({
    inheritLocale: true,
    useScope: 'local',
  })
  return key === null ? t as ComposerTranslation : t(key) as string
}
