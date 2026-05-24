/**
 * WAdmin is committed to providing solutions for quickly building web applications
 * Please view the LICENSE file that was distributed with this source code,
 * For the full copyright and license information.
 * Thank you very much for using WAdmin.
 *
 * @Author X.Mo<admin@wadmin.local>
 * @Link   https://github.com/bbwl521/WAdmin
 */
import App from './App.vue'
import MineBootstrap from './bootstrap'

const app = createApp(App)

MineBootstrap(app).then(() => {
  app.mount('#app')
}).catch((err) => {
  console.error('WAdmin-UI start fail', err)
})
