/**
 * WAdmin is committed to providing solutions for quickly building web applications
 * Please view the LICENSE file that was distributed with this source code,
 * For the full copyright and license information.
 * Thank you very much for using WAdmin.
 *
 * @Author X.Mo<admin@wadmin.local>
 * @Link   https://github.com/bbwl521/WAdmin
 */

import type { Dictionary, ProviderService } from '#/global'
import type { App } from 'vue'

const dictionary: Record<string, Dictionary[]> = {}
async function getDictionary() {
  const data = import.meta.glob('./data/**.{ts,js}')
  const allData = { ...data }
  for (const dic in allData) {
    const d: any = await allData[dic]()
    // 修复正则表达式：提取 /data/ 和 .(ts|js) 之间的部分
    const match = dic.match(/\/data\/([^/]+)\.(ts|js)$/)
    const name = match ? match[1] : undefined
    if (name) {
      dictionary[name] = d.default
    }
  }
}

const provider: ProviderService.Provider = {
  name: 'dictionary',
  async init() {
    await getDictionary()
  },
  setProvider(app: App) {
    app.config.globalProperties.$dictionary = dictionary
  },
  getProvider(): any {
    return useGlobal().$dictionary
  },
}

export default provider as ProviderService.Provider
