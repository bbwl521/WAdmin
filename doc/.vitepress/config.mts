import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'WAdmin',
  description: 'MineAdmin 后台管理系统文档',
  lang: 'zh-CN',
  base: '/doc/',
  lastUpdated: true,
  cleanUrls: true,
  
  head: [
    ['link', { rel: 'icon', href: '/doc/favicon.ico' }]
  ],

  themeConfig: {
    // https://vitepress.dev/reference/default-theme-config
    nav: [
      { text: '指南', link: '/guide/introduction' },
      { text: '架构', link: '/architecture/overview' },
      { text: '插件', link: '/plugin/guide' },
    ],

    sidebar: {
      '/guide/': [
        {
          text: '快速开始',
          items: [
            { text: '项目介绍', link: '/guide/introduction' },
            { text: '环境要求', link: '/guide/requirements' },
            { text: '安装部署', link: '/guide/installation' },
            { text: '目录结构', link: '/guide/structure' },
          ]
        },
        {
          text: '开发指南',
          items: [
            { text: '开发规范', link: '/guide/coding-standards' },
            { text: '控制器', link: '/guide/controllers' },
            { text: 'Service 层', link: '/guide/services' },
            { text: 'Repository 层', link: '/guide/repositories' },
            { text: '数据模型', link: '/guide/models' },
          ]
        }
      ],
      '/architecture/': [
        {
          text: '系统架构',
          items: [
            { text: '架构概览', link: '/architecture/overview' },
            { text: '分层设计', link: '/architecture/layers' },
            { text: '依赖注入', link: '/architecture/di' },
          ]
        }
      ],
      '/plugin/': [
        {
          text: '插件开发',
          items: [
            { text: '插件指南', link: '/plugin/guide' },
            { text: '插件结构', link: '/plugin/structure' },
            { text: '插件生命周期', link: '/plugin/lifecycle' },
          ]
        }
      ]
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com' }
    ],

    search: {
      provider: 'local'
    },

    footer: {
      message: '基于 Hyperf + MineAdmin 构建',
      copyright: 'Copyright © 2026 WAdmin'
    },

    outline: {
      level: [2, 3],
      label: '本页内容'
    },

    docFooter: {
      prev: '上一页',
      next: '下一页'
    },

    lastUpdated: {
      text: '最后更新于',
      formatOptions: {
        dateStyle: 'short',
        timeStyle: 'medium'
      }
    },

    darkModeSwitchLabel: '主题',
    sidebarMenuLabel: '菜单',
    returnToTopLabel: '回到顶部',
    langMenuLabel: '语言'
  }
})
