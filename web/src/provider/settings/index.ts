/**
 * WAdmin is committed to providing solutions for quickly building web applications
 * Please view the LICENSE file that was distributed with this source code,
 * For the full copyright and license information.
 * Thank you very much for using WAdmin.
 *
 * @Author X.Mo<admin@wadmin.local>
 * @Link   https://github.com/bbwl521/WAdmin
 */
import type { ProviderService, RecursiveRequired, SystemSettings } from '#/global'
import type { App } from 'vue'
import globalConfigSettings from '@/provider/settings/settings.config.ts'
import { defaultsDeep } from 'lodash-es'

const defaultGlobalConfigSettings: RecursiveRequired<SystemSettings.all> = {
  app: {
    colorMode: 'autoMode',
    useLocale: 'zh_CN',
    whiteRoute: ['login'],
    layout: 'classic',
    pageAnimate: 'ma-slide-down',
    enableWatermark: false,
    primaryColor: '#2563EB',
    asideDark: false,
    showBreadcrumb: true,
    loadUserSetting: true,
    watermarkText: import.meta.env.VITE_APP_TITLE,
  },
  welcomePage: {
    name: 'workbench',
    path: '/dashboard/workbench',
    title: '工作台',
    icon: 'icon-park-outline:jewelry',
  },
  mainAside: {
    showIcon: true,
    showTitle: true,
    enableOpenFirstRoute: false,
  },
  subAside: {
    showIcon: true,
    showTitle: true,
    fixedAsideState: false,
    showCollapseButton: true,
  },
  tabbar: {
    enable: true,
    mode: 'rectangle',
  },
  copyright: {
    enable: true,
    dates: useDayjs().format('YYYY'),
    company: 'WAdmin Team',
    website: 'https://github.com/bbwl521/WAdmin',
    putOnRecord: '豫ICP备00000000号-1',
  },
}

const systemSetting = defaultsDeep(globalConfigSettings, defaultGlobalConfigSettings) as RecursiveRequired<SystemSettings.all>

const provider: ProviderService.Provider = {
  name: 'defaultSetting',
  setProvider(app: App): void {
    app.provide('defaultSetting', systemSetting)
  },
  getProvider() {
    return inject('defaultSetting') as SystemSettings.all
  },
}

export default provider as ProviderService.Provider
