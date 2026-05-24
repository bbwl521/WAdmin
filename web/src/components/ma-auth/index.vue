<!--
 - WAdmin is committed to providing solutions for quickly building web applications
 - Please view the LICENSE file that was distributed with this source code,
 - For the full copyright and license information.
 - Thank you very much for using WAdmin.
 -
 - @Author X.Mo<admin@wadmin.local>
 - @Link   https://github.com/bbwl521/WAdmin
-->
<script setup lang="ts" generic="T extends string | string[]">
import hasAuth from '@/utils/permission/hasAuth'

defineOptions({ name: 'MaAuth' })

withDefaults(defineProps<{
  value: string | string[]
}>(), {
  value: '',
})
</script>

<template>
  <div>
    <slot v-if="hasAuth(value)" />
    <slot v-else name="notAuth" />
  </div>
</template>
