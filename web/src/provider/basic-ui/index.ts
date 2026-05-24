/**
 * WAdmin is committed to providing solutions for quickly building web applications
 * Please view the LICENSE file that was distributed with this source code,
 * For the full copyright and license information.
 * Thank you very much for using WAdmin.
 *
 * @Author X.Mo<admin@wadmin.local>
 * @Link   https://github.com/bbwl521/WAdmin
 */
import type { App } from 'vue'
import FloatingVue from 'floating-vue'
import 'floating-vue/dist/style.css'
import MInput from '../../components/mine-basic-ui/input/index.vue'
import MButton from '../../components/mine-basic-ui/button/index.vue'
import MTextarea from '../../components/mine-basic-ui/textarea/index.vue'
import MSwitch from '../../components/mine-basic-ui/switch/index.vue'
import MDrawer from '../../components/mine-basic-ui/drawer/index.vue'
import MModal from '../../components/mine-basic-ui/modal/index.vue'
import MDropdown from '../../components/mine-basic-ui/dropdown/index.vue'
import MDropdownItem from '../../components/mine-basic-ui/dropdown/item.vue'
import MDropdownDivider from '../../components/mine-basic-ui/dropdown/divider.vue'
import MTabs from '../../components/mine-basic-ui/tab/index.vue'
import MTooltip from '../../components/mine-basic-ui/tooltip/index.vue'

const provider = {
  name: 'basic-ui',
  setProvider(app: App) {
    app.use(FloatingVue, { distance: 12 })
    app.component('MInput', MInput)
    app.component('MButton', MButton)
    app.component('MTextarea', MTextarea)
    app.component('MSwitch', MSwitch)
    app.component('MDrawer', MDrawer)
    app.component('MModal', MModal)
    app.component('MDropdown', MDropdown)
    app.component('MDropdownItem', MDropdownItem)
    app.component('MDropdownDivider', MDropdownDivider)
    app.component('MTabs', MTabs)
    app.component('MTooltip', MTooltip)
  },
}

export default provider
