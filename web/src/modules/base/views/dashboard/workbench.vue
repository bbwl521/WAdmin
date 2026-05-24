<!--
 - WAdmin is committed to providing solutions for quickly building web applications
 - Please view the LICENSE file that was distributed with this source code,
 - For the full copyright and license information.
 - Thank you very much for using WAdmin.
 -
 - @Author X.Mo<admin@wadmin.local>
 - @Link   https://github.com/bbwl521/WAdmin
-->
<script setup lang="ts">
import WorkbenchFast from './components/workbench/workbench-fast.vue'
import WorkbenchLogin from '~/base/views/dashboard/components/workbench/workbench-login.vue'

defineOptions({ name: 'dashboard:workbench' })
</script>

<template>
  <div>
    <WorkbenchFast />
    <WorkbenchLogin />
  </div>
</template>

<style scoped lang="scss">
</style>
