/**
 * WAdmin is committed to providing solutions for quickly building web applications
 * Please view the LICENSE file that was distributed with this source code,
 * For the full copyright and license information.
 * Thank you very much for using WAdmin.
 *
 * @Author X.Mo<admin@wadmin.local>
 * @Link   https://github.com/bbwl521/WAdmin
 */
export function uploadLocal(options: any, url?: string, key?: string) {
  const upload = (formData: FormData) => {
    return useHttp().post(url ?? '/admin/attachment/upload', formData)
  }

  return new Promise((resolve, reject) => {
    const formData = new FormData()
    formData.append(key ?? 'file', options.file)
    upload(formData).then((res: Record<string, any>) => {
      res.code === 200 ? resolve(res) : reject(res)
    }).catch((err) => {
      reject(err)
    })
  })
}
